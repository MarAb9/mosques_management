<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\MosqueRepository;
use App\Repositories\QuranProgramRepository;

/**
 * Quran program business logic — list assembly and transactional
 * create/update/delete flows (legacy quran pages, behavior preserved
 * including the per-row status/responsible lookups).
 */
final class QuranProgramService
{
    private const PAGE_SIZE = 10;

    public function __construct(
        private readonly Database $db,
        private readonly QuranProgramRepository $programs,
        private readonly MosqueRepository $mosques,
    ) {
    }

    /**
     * @param array<string, mixed> $query raw GET parameters
     * @return array<string, mixed>
     */
    public function listPage(array $query): array
    {
        $page = isset($query['page']) ? (int) $query['page'] : 1;
        $start = ($page - 1) * self::PAGE_SIZE;

        // Legacy first allowlist narrows the sort key before the SQL-side one.
        $sort = isset($query['sort']) && in_array($query['sort'], ['id', 'mosque_name', 'responsible_name'], true)
            ? (string) $query['sort']
            : 'id';
        $order = isset($query['order']) && strtolower((string) $query['order']) === 'asc' ? 'ASC' : 'DESC';

        $total = $this->programs->countForList($query);
        $rows = $this->programs->searchForList($query, $sort, $order, $start, self::PAGE_SIZE);

        // Per-row lookups the legacy renderer did while printing each row.
        foreach ($rows as &$row) {
            $row['top_work_program'] = $this->programs->topWorkProgramStatus($row['id']);
            $row['responsible_names'] = $this->programs->firstResponsibleNames($row['id'], 3);
        }
        unset($row);

        return [
            'programs' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / self::PAGE_SIZE),
            'schoolCount' => $this->programs->countWithSchool(),
            'accomCount' => $this->programs->countWithAccommodation(),
            'centerCount' => $this->programs->countCenters(),
            'studentsCount' => $this->programs->totalStudents(),
            'communities' => $this->mosques->distinctCommunities(),
        ];
    }

    /**
     * Create a program with its responsibles (single transaction).
     *
     * @param array<string, mixed> $post
     */
    public function create(array $post): void
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        try {
            $programId = $this->programs->insertProgram([
                'mosque_registration_number' => $this->sanitize($post['mosque_registration_number'] ?? null),
                'has_quran_school' => $post['has_quran_school'] ?? 'لا',
                'has_accommodation' => $post['has_accommodation'] ?? 'لا',
            ]);

            $this->insertResponsibles($programId, $post);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update a program: replace its responsibles (single transaction).
     *
     * @param array<string, mixed> $post
     */
    public function update(int|string $programId, array $post): void
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        try {
            $this->programs->updateProgram([
                'mosque_registration_number' => $this->sanitize($post['mosque_registration_number'] ?? null),
                'has_quran_school' => $post['has_quran_school'] ?? 'لا',
                'has_accommodation' => $post['has_accommodation'] ?? 'لا',
                'id' => $programId,
            ]);

            $this->programs->deleteResponsiblesForProgram($programId);
            $this->insertResponsibles($programId, $post);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete programs + their responsibles (single transaction).
     *
     * @param list<mixed> $programIds
     */
    public function delete(array $programIds): int
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        try {
            $this->programs->deletePrograms($programIds);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return count($programIds);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function insertResponsibles(int|string $programId, array $post): void
    {
        if (empty($post['responsibles'])) {
            return;
        }

        foreach ($post['responsibles'] as $responsible) {
            $this->programs->insertResponsible([
                $programId,
                $this->sanitize($responsible['name']),
                $this->sanitize($responsible['position'] ?? ''),
                $this->sanitize($responsible['national_id'] ?? ''),
                $responsible['has_work_program'] ?? 'لا',
                $this->sanitize($responsible['memorization_schedule'] ?? ''),
                filter_var($responsible['weekly_sessions'] ?? 0, FILTER_SANITIZE_NUMBER_INT),
                filter_var($responsible['session_hours'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                filter_var($responsible['male_students'] ?? 0, FILTER_SANITIZE_NUMBER_INT),
                filter_var($responsible['female_students'] ?? 0, FILTER_SANITIZE_NUMBER_INT),
                $responsible['regular_attendance'] ?? 'لا',
                $this->sanitize($responsible['challenges'] ?? ''),
                $this->sanitize($responsible['notes_suggestions'] ?? ''),
            ]);
        }
    }

    /** Legacy FILTER_SANITIZE_FULL_SPECIAL_CHARS treatment. */
    private function sanitize(mixed $value): string
    {
        return (string) filter_var((string) ($value ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}
