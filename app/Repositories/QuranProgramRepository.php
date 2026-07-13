<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Data access for quran_memorization_programs and
 * quran_program_responsibles.
 *
 * Queries preserve the legacy pages' SQL exactly, including the
 * per-row lookups used by the list renderer.
 */
final class QuranProgramRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function countAll(): int
    {
        return (int) $this->db->pdo()
            ->query('SELECT COUNT(*) FROM quran_memorization_programs')
            ->fetchColumn();
    }

    // ── List page (legacy quran_mosques.php) ─────────────────────────────

    /**
     * @param array<string, mixed> $filters
     * @return array{where: string, params: list<mixed>, types: list<int>}
     */
    private function listConditions(array $filters): array
    {
        $where = '';
        $params = [];
        $types = [];

        if (isset($filters['query']) && !empty($filters['query'])) {
            $searchTerm = "%{$filters['query']}%";
            $where .= ' AND (m.mosque_name LIKE ? OR m.national_code LIKE ?)';
            $params = array_merge($params, [$searchTerm, $searchTerm]);
            $types = array_merge($types, [PDO::PARAM_STR, PDO::PARAM_STR]);
        }

        if (isset($filters['national_code']) && !empty($filters['national_code'])) {
            $where .= ' AND m.national_code LIKE ?';
            $params[] = "%{$filters['national_code']}%";
            $types[] = PDO::PARAM_STR;
        }

        if (isset($filters['community']) && !empty($filters['community'])) {
            $where .= ' AND m.community = ?';
            $params[] = $filters['community'];
            $types[] = PDO::PARAM_STR;
        }

        if (isset($filters['has_quran_school']) && !empty($filters['has_quran_school'])) {
            $subquery = ' AND q.id IN (
        SELECT program_id FROM quran_program_responsibles
        WHERE has_work_program = ?
    )';
            $where .= $subquery;
            $params[] = $filters['has_quran_school'];
            $types[] = PDO::PARAM_STR;
        }

        return ['where' => $where, 'params' => $params, 'types' => $types];
    }

    private const LIST_BASE = 'FROM quran_memorization_programs q
            JOIN mosques m ON q.mosque_registration_number = m.national_code
            LEFT JOIN quran_program_responsibles r ON q.id = r.program_id
            WHERE 1=1';

    /**
     * @param array<string, mixed> $filters
     */
    public function countForList(array $filters): int
    {
        ['where' => $where, 'params' => $params, 'types' => $types] = $this->listConditions($filters);

        $stmt = $this->db->pdo()->prepare('SELECT COUNT(DISTINCT q.id) ' . self::LIST_BASE . $where);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key + 1, $value, $types[$key]);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function searchForList(array $filters, string $sort, string $order, int $start, int $limit): array
    {
        ['where' => $where, 'params' => $params, 'types' => $types] = $this->listConditions($filters);

        $sql = 'SELECT q.*, m.mosque_name, m.community, m.national_code,
               COALESCE(SUM(r.male_students), 0) as total_male_students,
               COALESCE(SUM(r.female_students), 0) as total_female_students,
               COALESCE(SUM(r.weekly_sessions), 0) as total_weekly_sessions,
               COUNT(r.id) as responsible_count
        ' . self::LIST_BASE . $where . ' GROUP BY q.id';

        // Legacy double allowlist: request sort is first narrowed to
        // id/mosque_name/responsible_name, then checked against this list.
        $allowedSortColumns = ['id', 'mosque_name', 'national_code', 'community', 'total_weekly_sessions', 'total_male_students', 'total_female_students'];
        if (in_array($sort, $allowedSortColumns, true)) {
            $sql .= " ORDER BY {$sort} {$order}";
        } else {
            $sql .= " ORDER BY q.id {$order}";
        }

        $sql .= ' LIMIT ?, ?';
        $params[] = $start;
        $params[] = $limit;
        $types[] = PDO::PARAM_INT;
        $types[] = PDO::PARAM_INT;

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key + 1, $value, $types[$key]);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── List page aggregates ─────────────────────────────────────────────

    public function countWithSchool(): int
    {
        return (int) $this->db->pdo()->query("
                        SELECT COUNT(DISTINCT q.id)
                        FROM quran_memorization_programs q
                        JOIN quran_program_responsibles r ON q.id = r.program_id
                        WHERE r.has_work_program != 'لا'
                    ")->fetchColumn();
    }

    public function countWithAccommodation(): int
    {
        return (int) $this->db->pdo()->query("
                            SELECT COUNT(DISTINCT q.id)
                            FROM quran_memorization_programs q
                            WHERE q.has_accommodation = 'نعم'
                        ")->fetchColumn();
    }

    public function countCenters(): int
    {
        return (int) $this->db->pdo()->query("
                        SELECT COUNT(*)
                        FROM quran_memorization_programs
                        WHERE has_quran_school = 'مركز تحفيظ'
                    ")->fetchColumn();
    }

    public function totalStudents(): int
    {
        return (int) $this->db->pdo()->query('
                            SELECT COALESCE(SUM(r.male_students) + SUM(r.female_students), 0)
                            FROM quran_program_responsibles r
                        ')->fetchColumn();
    }

    // ── Per-row lookups (legacy renderQuranMosqueRow, N+1 preserved) ─────

    /**
     * Most common has_work_program value among a program's responsibles.
     *
     * @return array<string, mixed>|null
     */
    public function topWorkProgramStatus(int|string $programId): ?array
    {
        $stmt = $this->db->pdo()->prepare('
                                        SELECT has_work_program, COUNT(*) as count
                                        FROM quran_program_responsibles
                                        WHERE program_id = ?
                                        GROUP BY has_work_program
                                        ORDER BY count DESC
                                        LIMIT 1
                                    ');
        $stmt->execute([$programId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @return list<string>
     */
    public function firstResponsibleNames(int|string $programId, int $limit = 3): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT responsible_name FROM quran_program_responsibles WHERE program_id = ? LIMIT {$limit}"
        );
        $stmt->execute([$programId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── Details endpoints ────────────────────────────────────────────────

    /**
     * Program + mosque columns for the Quran details modal.
     *
     * @return array<string, mixed>|null
     */
    public function findForDetails(int|string $programId): ?array
    {
        $stmt = $this->db->pdo()->prepare('
        SELECT q.*, m.mosque_name, m.community, m.national_code
        FROM quran_memorization_programs q
        JOIN mosques m ON q.mosque_registration_number = m.national_code
        WHERE q.id = ?
    ');
        $stmt->execute([$programId]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);

        return $program === false ? null : $program;
    }

    /**
     * Program + mosque columns for the edit form.
     *
     * @return array<string, mixed>|null
     */
    public function findForEdit(int|string $programId): ?array
    {
        $stmt = $this->db->pdo()->prepare('
    SELECT q.*, m.mosque_name, m.pashalik, m.circle, m.leadership, m.community, m.administrative_attachment
    FROM quran_memorization_programs q
    JOIN mosques m ON q.mosque_registration_number = m.national_code
    WHERE q.id = ?
');
        $stmt->execute([$programId]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);

        return $program === false ? null : $program;
    }

    /**
     * Responsibles ordered by id (details modal endpoint).
     *
     * @return list<array<string, mixed>>
     */
    public function responsiblesOrderedById(int|string $programId): array
    {
        $stmt = $this->db->pdo()->prepare('
        SELECT * FROM quran_program_responsibles
        WHERE program_id = ?
        ORDER BY id
    ');
        $stmt->execute([$programId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Responsibles in insertion order (edit form).
     *
     * @return list<array<string, mixed>>
     */
    public function responsiblesRaw(int|string $programId): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM quran_program_responsibles WHERE program_id = ?');
        $stmt->execute([$programId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Programs linked to a mosque (by national code), as returned by the
     * legacy mosque-details endpoint.
     *
     * @return list<array<string, mixed>>
     */
    public function programsForMosque(string $nationalCode): array
    {
        $stmt = $this->db->pdo()->prepare('
            SELECT
                q.id,
                q.has_quran_school,
                q.has_accommodation,
                q.created_at,
                q.updated_at
            FROM quran_memorization_programs q
            WHERE q.mosque_registration_number = ?
        ');
        $stmt->execute([$nationalCode]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function responsiblesForProgram(int|string $programId): array
    {
        $stmt = $this->db->pdo()->prepare('
            SELECT
                id,
                responsible_name,
                responsible_position,
                responsible_national_id,
                memorization_schedule,
                has_work_program,
                weekly_sessions,
                session_hours,
                female_students,
                male_students,
                total_students,
                regular_attendance,
                challenges,
                notes_suggestions,
                created_at,
                updated_at
            FROM quran_program_responsibles
            WHERE program_id = ?
            ORDER BY created_at
        ');
        $stmt->execute([$programId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Form data sources (legacy add/edit pages) ────────────────────────

    /**
     * Enum options of memorization_schedule from INFORMATION_SCHEMA
     * (legacy dropdown source).
     *
     * @return list<string>
     */
    public function scheduleEnumOptions(): array
    {
        $stmt = $this->db->pdo()->query("
    SELECT
        COLUMN_NAME,
        COLUMN_TYPE
    FROM
        INFORMATION_SCHEMA.COLUMNS
    WHERE
        TABLE_NAME = 'quran_program_responsibles'
        AND COLUMN_NAME IN ('memorization_schedule')
");

        $enumValues = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            preg_match("/^enum\('(.*)'\)$/", (string) $row['COLUMN_TYPE'], $matches);
            $enumValues[$row['COLUMN_NAME']] = explode("','", $matches[1]);
        }

        return $enumValues['memorization_schedule'] ?? [];
    }

    /**
     * Mosques without a Quran program (add form dropdown).
     *
     * @return list<array<string, mixed>>
     */
    public function mosquesWithoutProgram(): array
    {
        return $this->db->pdo()->query('
    SELECT
        m.national_code AS id,
        m.mosque_name,
        m.pashalik,
        m.circle,
        m.leadership,
        m.community,
        m.administrative_attachment
    FROM mosques m
    LEFT JOIN quran_memorization_programs qmp ON m.national_code = qmp.mosque_registration_number
    WHERE qmp.mosque_registration_number IS NULL
    ORDER BY m.mosque_name, m.pashalik, m.circle, m.community
')->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All mosques (edit form dropdown).
     *
     * @return list<array<string, mixed>>
     */
    public function allMosquesForDropdown(): array
    {
        return $this->db->pdo()->query('
    SELECT
        national_code AS id,
        mosque_name,
        pashalik,
        circle,
        leadership,
        community,
        administrative_attachment
    FROM mosques
    ORDER BY mosque_name, pashalik, circle, community
')->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Writes (used inside QuranProgramService transactions) ────────────

    /**
     * @param array<string, mixed> $data
     */
    public function insertProgram(array $data): string
    {
        $stmt = $this->db->pdo()->prepare('
            INSERT INTO quran_memorization_programs
            (mosque_registration_number, has_quran_school, has_accommodation)
            VALUES (:mosque_registration_number, :has_quran_school, :has_accommodation)
        ');
        $stmt->execute($data);

        return (string) $this->db->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data includes 'id'
     */
    public function updateProgram(array $data): void
    {
        $stmt = $this->db->pdo()->prepare('
            UPDATE quran_memorization_programs
            SET mosque_registration_number = :mosque_registration_number,
                has_quran_school = :has_quran_school,
                has_accommodation = :has_accommodation
            WHERE id = :id
        ');
        $stmt->execute($data);
    }

    /**
     * @param list<mixed> $params 13 positional values (legacy column order)
     */
    public function insertResponsible(array $params): void
    {
        $stmt = $this->db->pdo()->prepare('
                INSERT INTO quran_program_responsibles
                (program_id, responsible_name, responsible_position, responsible_national_id,
                 has_work_program, memorization_schedule, weekly_sessions, session_hours,
                 male_students, female_students, regular_attendance, challenges, notes_suggestions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
        $stmt->execute($params);
    }

    public function deleteResponsiblesForProgram(int|string $programId): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM quran_program_responsibles WHERE program_id = ?');
        $stmt->execute([$programId]);
    }

    /**
     * @param list<mixed> $programIds
     */
    public function deletePrograms(array $programIds): void
    {
        $placeholders = rtrim(str_repeat('?,', count($programIds)), ',');

        $stmt = $this->db->pdo()->prepare("DELETE FROM quran_program_responsibles WHERE program_id IN ({$placeholders})");
        $stmt->execute($programIds);

        $stmt = $this->db->pdo()->prepare("DELETE FROM quran_memorization_programs WHERE id IN ({$placeholders})");
        $stmt->execute($programIds);
    }
}
