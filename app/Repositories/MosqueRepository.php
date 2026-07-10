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
    private function liveSearchConditions(string $searchTerm, string $community, string $status, string $fridayPrayer): array
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

        return ['conditions' => $baseConditions, 'params' => $params];
    }

    /**
     * Raw fetchColumn() value, uncast — the legacy endpoint put it into the
     * JSON payload as-is, so the JSON number/string type must match.
     */
    public function countForLiveSearch(string $searchTerm, string $community, string $status, string $fridayPrayer): mixed
    {
        ['conditions' => $conditions, 'params' => $params] =
            $this->liveSearchConditions($searchTerm, $community, $status, $fridayPrayer);

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
    public function liveSearch(string $searchTerm, string $community, string $status, string $fridayPrayer, int $start, int $limit): array
    {
        ['conditions' => $conditions, 'params' => $params] =
            $this->liveSearchConditions($searchTerm, $community, $status, $fridayPrayer);

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
