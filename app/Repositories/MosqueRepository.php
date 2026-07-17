<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Helpers\Arabic;
use PDO;

/**
 * Data access for the mosques table.
 *
 * Every query preserves the columns, joins, ordering, and parameter
 * binding of the legacy page it was extracted from.
 */
final class MosqueRepository
{
    public const LIST_SORT_COLUMNS = ['registration_number', 'mosque_name', 'national_code', 'construction_date'];

    public function __construct(private readonly Database $db)
    {
    }

    // ── Mosque list page (legacy mosques.php) ────────────────────────────

    /**
     * Build the shared WHERE clause + params for the list filters.
     *
     * @param array<string, mixed> $filters raw GET filters
     * @return array{where: string, params: list<mixed>}
     */
    private function listConditions(array $filters): array
    {
        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($filters['national_code'])) {
            $code = (string) $filters['national_code'];
            if (isset($filters['from_map']) && $filters['from_map'] == $code) {
                // Exact search for map links
                $where .= ' AND national_code = ?';
                $params[] = $code;
            } else {
                // Like search for user input
                $where .= ' AND national_code LIKE ?';
                $params[] = "%{$code}%";
            }
        }

        if (!empty($filters['query'])) {
            $query = (string) $filters['query'];
            $searchTerm = "%{$query}%";

            if (preg_match('/^\d+$/', $query)) {
                $where .= ' AND (registration_number = ? OR mosque_name LIKE ? OR imam_name LIKE ? OR preacher_name LIKE ? OR muezzin_name LIKE ?)';
                $params = array_merge($params, [$query, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            } else {
                $where .= ' AND (mosque_name LIKE ? OR imam_name LIKE ? OR preacher_name LIKE ? OR muezzin_name LIKE ?)';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
        }

        if (!empty($filters['imam_registration'])) {
            $where .= ' AND imam_registration LIKE ?';
            $params[] = "%{$filters['imam_registration']}%";
        }

        if (!empty($filters['community'])) {
            $where .= ' AND community = ?';
            $params[] = $filters['community'];
        }

        if (!empty($filters['status'])) {
            $where .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['friday_prayer'])) {
            $where .= ' AND friday_prayer = ?';
            $params[] = $filters['friday_prayer'];
        }

        if (!empty($filters['guide_imam'])) {
            $guideFilter = trim((string) $filters['guide_imam']);
            if (is_numeric($guideFilter)) {
                $where .= ' AND m.guide_imam_id = ?';
                $params[] = (int) $guideFilter;
            } else {
                // Name string with optional "(count)" suffix — backward
                // compatible with pre-guide_imams URLs.
                $guideName = (string) preg_replace('/\s*\(\d+\)$/', '', $guideFilter);
                $where .= ' AND m.guide_imam_id IN (SELECT id FROM guide_imams WHERE display_name_normalized LIKE ?)';
                $params[] = '%' . Arabic::normalize($guideName) . '%';
            }
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countForList(array $filters): int
    {
        ['where' => $where, 'params' => $params] = $this->listConditions($filters);

        $stmt = $this->db->pdo()->prepare("SELECT COUNT(*) FROM mosques m {$where}");
        $this->bindPositional($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function searchForList(array $filters, string $sort, string $order, int $start, int $limit): array
    {
        $sort = in_array($sort, self::LIST_SORT_COLUMNS, true) ? $sort : 'registration_number';
        $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        ['where' => $where, 'params' => $params] = $this->listConditions($filters);

        $sql = "SELECT m.*, gi.display_name AS guide_imam_display
                FROM mosques m
                LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
                {$where}";

        if ($sort === 'construction_date') {
            $sql .= " ORDER BY YEAR(construction_date) {$order}";
        } else {
            $sql .= " ORDER BY {$sort} {$order}";
        }

        $sql .= ' LIMIT ?, ?';
        $params[] = $start;
        $params[] = $limit;

        $stmt = $this->db->pdo()->prepare($sql);
        $this->bindPositional($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Live search endpoint (legacy ajax/search_mosques.php) ────────────

    /**
     * @return array{conditions: list<string>, params: list<mixed>}
     */
    private function liveSearchConditions(
        string $searchTerm,
        string $community,
        string $status,
        string $fridayPrayer,
        string $guideImam,
    ): array
    {
        $baseConditions = [];
        $params = [];

        if ($searchTerm !== '') {
            $searchConditions = [
                'mosque_name LIKE ?',
                'imam_name LIKE ?',
                'preacher_name LIKE ?',
                'muezzin_name LIKE ?',
                'gi.display_name LIKE ?',
                'gi.display_name_normalized LIKE ?',
                'm.guide_imam LIKE ?',
                'national_code LIKE ?',
                'imam_registration LIKE ?',
                'address LIKE ?',
            ];
            $params = array_fill(0, count($searchConditions), "%{$searchTerm}%");
            $baseConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        if ($community !== '') {
            $baseConditions[] = 'community = ?';
            $params[] = $community;
        }

        if ($status !== '') {
            $baseConditions[] = 'status = ?';
            $params[] = $status;
        }

        if ($fridayPrayer !== '') {
            $baseConditions[] = 'friday_prayer = ?';
            $params[] = $fridayPrayer;
        }

        if ($guideImam !== '') {
            if (ctype_digit($guideImam)) {
                $baseConditions[] = 'm.guide_imam_id = ?';
                $params[] = (int) $guideImam;
            } else {
                $guideName = (string) preg_replace('/\s*\(\d+\)$/', '', $guideImam);
                $baseConditions[] = 'm.guide_imam_id IN (SELECT id FROM guide_imams WHERE display_name_normalized LIKE ?)';
                $params[] = '%' . Arabic::normalize($guideName) . '%';
            }
        }

        return ['conditions' => $baseConditions, 'params' => $params];
    }

    /**
     * Raw fetchColumn() value, uncast — the legacy endpoint put it into the
     * JSON payload as-is, so the JSON number/string type must match.
     */
    public function countForLiveSearch(
        string $searchTerm,
        string $community,
        string $status,
        string $fridayPrayer,
        string $guideImam,
    ): mixed
    {
        ['conditions' => $conditions, 'params' => $params] =
            $this->liveSearchConditions($searchTerm, $community, $status, $fridayPrayer, $guideImam);

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->db->pdo()->prepare(
            "SELECT COUNT(*) FROM mosques m LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id {$whereClause}"
        );
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function liveSearch(
        string $searchTerm,
        string $community,
        string $status,
        string $fridayPrayer,
        string $guideImam,
        int $start,
        int $limit,
    ): array
    {
        ['conditions' => $conditions, 'params' => $params] =
            $this->liveSearchConditions($searchTerm, $community, $status, $fridayPrayer, $guideImam);

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT m.*, COALESCE(gi.display_name, m.guide_imam) AS guide_imam
                FROM mosques m
                LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
                {$whereClause}
                ORDER BY m.registration_number DESC
                LIMIT ?, ?";
        $params[] = $start;
        $params[] = $limit;

        $stmt = $this->db->pdo()->prepare($sql);
        $this->bindPositional($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Details endpoint (legacy ajax/get_mosque_details.php) ────────────

    /**
     * Find a mosque by national code or registration number.
     *
     * @return array<string, mixed>|null
     */
    public function findForDetails(string $id): ?array
    {
        $stmt = $this->db->pdo()->prepare('
            SELECT
                m.registration_number,
                m.national_code,
                m.mosque_name,
                m.address,
                m.admin_type,
                m.pashalik,
                m.circle,
                m.leadership,
                m.community,
                m.construction_date,
                m.status,
                m.friday_prayer,
                m.funding_source,
                m.imam_name,
                m.imam_registration,
                m.imam_phone,
                m.preacher_name,
                m.preacher_registration,
                m.preacher_phone,
                m.muezzin_name,
                m.muezzin_registration,
                m.muezzin_phone,
                m.quran_memorization,
                m.literacy_program,
                m.guidance_program,
                COALESCE(gi.display_name, m.guide_imam) AS guide_imam,
                m.notes,
                m.administrative_attachment,
                m.main_image
            FROM mosques m
            LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
            WHERE m.national_code = ? OR m.registration_number = ?
        ');
        $stmt->execute([$id, $id]);
        $mosque = $stmt->fetch(PDO::FETCH_ASSOC);

        return $mosque === false ? null : $mosque;
    }

    // ── Aggregates (list page cards + stats endpoint) ────────────────────

    public function countAll(): int
    {
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM mosques')->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM mosques WHERE status = ?');
        $stmt->execute([$status]);

        return (int) $stmt->fetchColumn();
    }

    public function countFridayMosques(): int
    {
        return (int) $this->db->pdo()
            ->query("SELECT COUNT(*) FROM mosques WHERE friday_prayer = 'نعم'")
            ->fetchColumn();
    }

    public function countDistinctCommunities(): int
    {
        return (int) $this->db->pdo()
            ->query('SELECT COUNT(DISTINCT community) FROM mosques WHERE community IS NOT NULL')
            ->fetchColumn();
    }

    /** @return list<string> */
    public function distinctCommunities(): array
    {
        return $this->db->pdo()
            ->query("SELECT DISTINCT community FROM mosques WHERE community IS NOT NULL AND community != '' ORDER BY community")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function distinctStatuses(): array
    {
        return $this->db->pdo()
            ->query("SELECT DISTINCT status FROM mosques WHERE status IS NOT NULL AND status != '' ORDER BY status")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function distinctFridayOptions(): array
    {
        return $this->db->pdo()
            ->query("SELECT DISTINCT friday_prayer FROM mosques WHERE friday_prayer IS NOT NULL AND friday_prayer != '' ORDER BY friday_prayer")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function countByStatusNot(string $status): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM mosques WHERE status != ?');
        $stmt->execute([$status]);

        return (int) $stmt->fetchColumn();
    }

    public function countByAdminType(string $adminType): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM mosques WHERE admin_type = ?');
        $stmt->execute([$adminType]);

        return (int) $stmt->fetchColumn();
    }

    public function countGuidanceMosques(): int
    {
        return (int) $this->db->pdo()
            ->query("SELECT COUNT(*) FROM mosques WHERE guidance_program = 'نعم'")
            ->fetchColumn();
    }

    /**
     * Latest mosques for the dashboard table (legacy index.php query).
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 5): array
    {
        return $this->db->pdo()->query("
            SELECT m.*, YEAR(m.construction_date) AS construction_year_only, gi.display_name AS guide_imam_display
            FROM mosques m
            LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
            ORDER BY m.registration_number DESC
            LIMIT {$limit}
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function statusStats(): array
    {
        return $this->db->pdo()
            ->query('SELECT status, COUNT(*) as count FROM mosques GROUP BY status')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function fridayStats(): array
    {
        return $this->db->pdo()
            ->query('SELECT friday_prayer, COUNT(*) as count FROM mosques GROUP BY friday_prayer')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function communityStats(): array
    {
        return $this->db->pdo()->query("
            SELECT community, COUNT(*) as count
            FROM mosques
            WHERE community IS NOT NULL AND community != ''
            GROUP BY community
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }


    /** @return array<string, int> */
    public function dataQualitySummary(): array
    {
        return [
            'missing_coordinates' => $this->countWhere("latitude IS NULL OR longitude IS NULL OR latitude = '' OR longitude = ''"),
            'missing_imam_phone' => $this->countWhere("imam_phone IS NULL OR imam_phone = ''"),
            'incomplete_addresses' => $this->countWhere("address IS NULL OR TRIM(address) = ''"),
            'invalid_years' => $this->countWhere("construction_date IS NOT NULL AND (YEAR(construction_date) < 1000 OR YEAR(construction_date) > YEAR(CURDATE()) + 1)"),
            'duplicate_national_codes' => count($this->duplicateNationalCodes(50)),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function dataQualitySamples(string $issue, int $limit = 8): array
    {
        $conditions = [
            'missing_coordinates' => "m.latitude IS NULL OR m.longitude IS NULL OR m.latitude = '' OR m.longitude = ''",
            'missing_imam_phone' => "m.imam_phone IS NULL OR m.imam_phone = ''",
            'incomplete_addresses' => "m.address IS NULL OR TRIM(m.address) = ''",
            'invalid_years' => "m.construction_date IS NOT NULL AND (YEAR(m.construction_date) < 1000 OR YEAR(m.construction_date) > YEAR(CURDATE()) + 1)",
        ];

        if ($issue === 'duplicate_national_codes') {
            return $this->duplicateNationalCodes($limit);
        }

        $condition = $conditions[$issue] ?? $conditions['missing_coordinates'];
        $stmt = $this->db->pdo()->prepare("\n            SELECT m.registration_number, m.national_code, m.mosque_name, m.community, m.address, m.imam_name, m.imam_phone, m.latitude, m.longitude, m.construction_date\n            FROM mosques m\n            WHERE {$condition}\n            ORDER BY m.registration_number DESC\n            LIMIT ?\n        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function duplicateNationalCodes(int $limit = 10): array
    {
        $stmt = $this->db->pdo()->prepare("\n            SELECT national_code, COUNT(*) AS duplicate_count, GROUP_CONCAT(registration_number ORDER BY registration_number) AS registration_numbers\n            FROM mosques\n            WHERE national_code IS NOT NULL AND national_code != ''\n            GROUP BY national_code\n            HAVING COUNT(*) > 1\n            ORDER BY duplicate_count DESC, national_code\n            LIMIT ?\n        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countQuranProgramCoverage(): int
    {
        return (int) $this->db->pdo()->query("\n            SELECT COUNT(DISTINCT m.registration_number)\n            FROM mosques m\n            INNER JOIN quran_mosques q ON q.mosque_registration_number = m.national_code\n        ")->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function recentlyUpdated(int $limit = 8): array
    {
        $stmt = $this->db->pdo()->prepare("\n            SELECT m.registration_number, m.national_code, m.mosque_name, m.community, m.status\n            FROM mosques m\n            ORDER BY m.registration_number DESC\n            LIMIT ?\n        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<mixed> $nationalCodes */
    public function deleteByNationalCodes(array $nationalCodes): int
    {
        if ($nationalCodes === []) {
            return 0;
        }

        $placeholders = rtrim(str_repeat('?,', count($nationalCodes)), ',');
        $stmt = $this->db->pdo()->prepare("DELETE FROM mosques WHERE national_code IN ({$placeholders})");
        $stmt->execute(array_values($nationalCodes));

        return $stmt->rowCount();
    }

    private function countWhere(string $condition): int
    {
        return (int) $this->db->pdo()->query("SELECT COUNT(*) FROM mosques WHERE {$condition}")->fetchColumn();
    }
    // ── CRUD (legacy add/edit/delete pages) ──────────────────────────────

    /**
     * Column order shared by insert and update — identical to the legacy
     * INSERT/UPDATE statements (and to the key order produced by
     * MosqueFormService::processFormData()).
     */
    private const WRITE_COLUMNS = [
        'mosque_name', 'address', 'construction_date', 'national_code', 'status',
        'friday_prayer', 'community', 'funding_source', 'imam_name', 'imam_registration',
        'imam_phone', 'preacher_name', 'preacher_registration', 'preacher_phone',
        'muezzin_name', 'muezzin_registration', 'muezzin_phone', 'quran_memorization',
        'literacy_program', 'guidance_program', 'guide_imam', 'notes',
        'administrative_attachment', 'admin_type', 'pashalik', 'circle', 'leadership',
        'main_image', 'latitude', 'longitude', 'guide_imam_id',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $registrationNumber): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM mosques WHERE registration_number = ?');
        $stmt->execute([$registrationNumber]);
        $mosque = $stmt->fetch(PDO::FETCH_ASSOC);

        return $mosque === false ? null : $mosque;
    }

    public function nationalCodeExists(string $nationalCode): bool
    {
        $stmt = $this->db->pdo()->prepare('SELECT registration_number FROM mosques WHERE national_code = ?');
        $stmt->execute([$nationalCode]);

        return $stmt->fetch() !== false;
    }

    /**
     * @param array<string, mixed> $data output of MosqueFormService::processFormData()
     */
    public function insert(array $data): void
    {
        $columns = implode(', ', self::WRITE_COLUMNS);
        $placeholders = rtrim(str_repeat('?, ', count(self::WRITE_COLUMNS)), ', ');

        $stmt = $this->db->pdo()->prepare("INSERT INTO mosques ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($this->writeParams($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $registrationNumber, array $data): void
    {
        $set = implode(",\n                    ", array_map(fn (string $c): string => "{$c} = ?", self::WRITE_COLUMNS));

        $stmt = $this->db->pdo()->prepare("UPDATE mosques SET {$set} WHERE registration_number = ?");

        $params = $this->writeParams($data);
        $params[] = $registrationNumber;
        $stmt->execute($params);
    }

    /**
     * @param list<mixed> $registrationNumbers
     */
    public function deleteByRegistrationNumbers(array $registrationNumbers): int
    {
        $placeholders = rtrim(str_repeat('?,', count($registrationNumbers)), ',');
        $stmt = $this->db->pdo()->prepare("DELETE FROM mosques WHERE registration_number IN ({$placeholders})");
        $stmt->execute(array_values($registrationNumbers));

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private function writeParams(array $data): array
    {
        return array_map(static fn (string $column) => $data[$column] ?? null, self::WRITE_COLUMNS);
    }

    // ── Map page + endpoint (legacy mosque_maps.php, get_mosques_for_map) ─

    public function countWithCoordinates(): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM mosques WHERE latitude IS NOT NULL AND longitude IS NOT NULL');
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function withCoordinatesPaginated(int $start, int $limit): array
    {
        $stmt = $this->db->pdo()->prepare('
        SELECT m.registration_number, m.mosque_name, m.national_code, m.address, m.imam_name, m.status,
               m.friday_prayer, m.community, COALESCE(gi.display_name, m.guide_imam) AS guide_imam, m.latitude, m.longitude
        FROM mosques m
        LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
        WHERE m.latitude IS NOT NULL AND m.longitude IS NOT NULL
        ORDER BY m.mosque_name
        LIMIT ?, ?
    ');
        $stmt->bindParam(1, $start, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All coordinate-bearing mosques for map search (legacy full payload).
     *
     * @return list<array<string, mixed>>
     */
    public function allWithCoordinates(): array
    {
        $stmt = $this->db->pdo()->prepare('
        SELECT m.registration_number, m.mosque_name, m.national_code, m.address, m.imam_name, m.status,
               m.friday_prayer, m.community, COALESCE(gi.display_name, m.guide_imam) AS guide_imam, m.latitude, m.longitude
        FROM mosques m
        LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
        WHERE m.latitude IS NOT NULL AND m.longitude IS NOT NULL
        ORDER BY m.mosque_name
    ');
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Map filter dropdown variant: also excludes the placeholder value.
     *
     * @return list<string>
     */
    public function distinctCommunitiesForMap(): array
    {
        $stmt = $this->db->pdo()->prepare("
        SELECT DISTINCT community
        FROM mosques
        WHERE community IS NOT NULL AND community != '' AND community != 'غير محدد'
        ORDER BY community
    ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Payload of the map AJAX endpoint (no join — legacy shape).
     *
     * @return list<array<string, mixed>>
     */
    public function coordinatesForMapEndpoint(): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT registration_number, mosque_name, address, imam_name, status, friday_prayer, latitude, longitude
            FROM mosques
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL');
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Import/Export (legacy import_export.php) ─────────────────────────

    /**
     * Rows for Excel/Word export with the legacy filter set.
     *
     * @param array<string, mixed> $query raw GET parameters
     * @return list<array<string, mixed>>
     */
    public function findForExport(array $query): array
    {
        $where = [];
        $params = [];

        $filters = [
            'status',
            'friday_prayer',
            'community',
            'literacy_program',
            'guidance_program',
            'guide_imam',
            'quran_memorization',
        ];

        foreach ($filters as $filter) {
            if (!empty($query[$filter])) {
                // Special handling for guide_imam to match either id or name
                if ($filter == 'guide_imam') {
                    $guideFilter = trim((string) $query['guide_imam']);
                    if (is_numeric($guideFilter)) {
                        $where[] = 'm.guide_imam_id = ?';
                        $params[] = (int) $guideFilter;
                    } else {
                        // fallback to name string matching for backward compatibility
                        $guideName = (string) preg_replace('/\s*\(\d+\)$/', '', $guideFilter);
                        $where[] = 'm.guide_imam_id IN (SELECT id FROM guide_imams WHERE display_name_normalized LIKE ?)';
                        $params[] = '%' . Arabic::normalize($guideName) . '%';
                    }
                } else {
                    $where[] = "m.{$filter} = ?";
                    $params[] = $query[$filter];
                }
            }
        }

        // Filter for undetermined location
        if (isset($query['no_location']) && $query['no_location'] == '1') {
            $where[] = "(m.latitude IS NULL OR m.longitude IS NULL OR m.latitude = '' OR m.longitude = '')";
        }

        $orderBy = 'm.registration_number';
        if (isset($query['group_by_guide']) && $query['group_by_guide'] == '1') {
            $where[] = 'm.guide_imam_id IS NOT NULL';
            $orderBy = 'gi.display_name_normalized, m.registration_number';
        }

        $sql = 'SELECT m.*, gi.display_name AS guide_imam_display FROM mosques m LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $orderBy;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Distinct column values for the export filter dropdowns
     * (legacy variant: excludes NULL but keeps empty strings).
     *
     * @return list<string>
     */
    public function distinctColumnForExport(string $column): array
    {
        $allowed = ['status', 'friday_prayer', 'community', 'literacy_program', 'guidance_program'];
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Column not allowed: {$column}");
        }

        return $this->db->pdo()
            ->query("SELECT DISTINCT {$column} FROM mosques WHERE {$column} IS NOT NULL ORDER BY {$column}")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Insert one imported spreadsheet row
     * (legacy named-parameter INSERT, same column set).
     *
     * @param array<string, mixed> $data
     */
    public function insertFromImport(array $data): void
    {
        $stmt = $this->db->pdo()->prepare('INSERT INTO mosques (
            mosque_name, address, construction_date,
            national_code, status, friday_prayer, community, funding_source,
            imam_name, imam_registration, imam_phone, preacher_name,
            preacher_registration, preacher_phone, muezzin_name,
            muezzin_registration, muezzin_phone, quran_memorization,
            literacy_program, guidance_program, guide_imam, notes,
            admin_type, pashalik, administrative_attachment, circle, leadership,
            latitude, longitude
        ) VALUES (
            :mosque_name, :address, :construction_date,
            :national_code, :status, :friday_prayer, :community, :funding_source,
            :imam_name, :imam_registration, :imam_phone, :preacher_name,
            :preacher_registration, :preacher_phone, :muezzin_name,
            :muezzin_registration, :muezzin_phone, :quran_memorization,
            :literacy_program, :guidance_program, :guide_imam, :notes,
            :admin_type, :pashalik, :administrative_attachment, :circle, :leadership,
            :latitude, :longitude
        )');

        $stmt->execute($data);
    }

    // ── Shared ────────────────────────────────────────────────────────────

    /**
     * Bind positional params typed like the legacy pages did:
     * PARAM_INT for PHP ints, PARAM_STR otherwise.
     *
     * @param list<mixed> $params
     */
    private function bindPositional(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
