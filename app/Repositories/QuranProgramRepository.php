<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Data access for quran_memorization_programs and
 * quran_program_responsibles.
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
}
