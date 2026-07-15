<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\MosqueRepository;
use App\Repositories\QuranProgramRepository;
use InvalidArgumentException;

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
        $requestedPage = max(1, isset($query['page']) ? (int) $query['page'] : 1);

        // Legacy first allowlist narrows the sort key before the SQL-side one.
        $sort = isset($query['sort']) && in_array($query['sort'], ['id', 'mosque_name', 'responsible_name'], true)
            ? (string) $query['sort']
            : 'id';
        $order = isset($query['order']) && strtolower((string) $query['order']) === 'asc' ? 'ASC' : 'DESC';

        $total = $this->programs->countForList($query);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($requestedPage, $pages);
        $start = ($page - 1) * self::PAGE_SIZE;
        $rows = $this->programs->searchForList($query, $sort, $order, $start, self::PAGE_SIZE);

        foreach ($rows as &$row) {
            $status = (string) ($row['top_work_program_status'] ?? '');
            $row['top_work_program'] = $status === '' ? null : ['has_work_program' => $status];
            $names = (string) ($row['responsible_names_aggregated'] ?? '');
            $row['responsible_names'] = $names === '' ? [] : array_slice(explode('|||', $names), 0, 3);
            unset($row['top_work_program_status'], $row['responsible_names_aggregated']);
        }
        unset($row);

        return [
            'programs' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
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
        $this->validatePayload($post);
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
        $this->validatePayload($post);
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
                (int) ($responsible['weekly_sessions'] ?? 0),
                (float) ($responsible['session_hours'] ?? 0),
                (int) ($responsible['male_students'] ?? 0),
                (int) ($responsible['female_students'] ?? 0),
                $responsible['regular_attendance'] ?? 'لا',
                $this->sanitize($responsible['challenges'] ?? ''),
                $this->sanitize($responsible['notes_suggestions'] ?? ''),
            ]);
        }
    }

    /** Normalize text for storage; views and JSON consumers encode at output. */
    private function sanitize(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /** @param array<string, mixed> $post */
    private function validatePayload(array $post): void
    {
        $mosqueCode = $this->sanitize($post['mosque_registration_number'] ?? '');
        if (!preg_match('/^\d{9}$/', $mosqueCode) || !$this->mosques->nationalCodeExists($mosqueCode)) {
            throw new InvalidArgumentException('المسجد المحدد غير صالح.');
        }

        if (!in_array((string) ($post['has_quran_school'] ?? ''), ['نعم', 'لا', 'مركز تحفيظ'], true)) {
            throw new InvalidArgumentException('نوع برنامج التحفيظ غير صالح.');
        }
        if (!in_array((string) ($post['has_accommodation'] ?? ''), ['نعم', 'لا'], true)) {
            throw new InvalidArgumentException('قيمة الإقامة غير صالحة.');
        }

        $responsibles = $post['responsibles'] ?? null;
        if (!is_array($responsibles) || $responsibles === [] || count($responsibles) > 50) {
            throw new InvalidArgumentException('يجب إدخال مسؤول واحد على الأقل وبحد أقصى 50 مسؤولاً.');
        }

        foreach ($responsibles as $responsible) {
            if (!is_array($responsible)) {
                throw new InvalidArgumentException('بيانات المسؤول غير صالحة.');
            }
            $name = $this->sanitize($responsible['name'] ?? '');
            if ($name === '' || mb_strlen($name) > 255) {
                throw new InvalidArgumentException('اسم المسؤول مطلوب ويجب ألا يتجاوز 255 حرفاً.');
            }
            foreach (['position' => 255, 'national_id' => 50] as $field => $maximum) {
                if (mb_strlen($this->sanitize($responsible[$field] ?? '')) > $maximum) {
                    throw new InvalidArgumentException('أحد حقول المسؤول يتجاوز الطول المسموح.');
                }
            }
            if (!in_array((string) ($responsible['has_work_program'] ?? 'لا'), ['نعم', 'لا'], true)
                || !in_array((string) ($responsible['regular_attendance'] ?? 'لا'), ['نعم', 'لا'], true)
            ) {
                throw new InvalidArgumentException('إحدى قيم نعم/لا الخاصة بالمسؤول غير صالحة.');
            }
            if (!in_array($this->sanitize($responsible['memorization_schedule'] ?? ''), ['باستمرار', 'بصفة منقطعة'], true)) {
                throw new InvalidArgumentException('جدول الحفظ غير صالح.');
            }

            $this->assertNumberInRange($responsible['weekly_sessions'] ?? 0, 0, 21, true, 'عدد الجلسات الأسبوعية');
            $this->assertNumberInRange($responsible['session_hours'] ?? 0, 0, 24, false, 'مدة الجلسة');
            $this->assertNumberInRange($responsible['male_students'] ?? 0, 0, 5000, true, 'عدد الطلاب');
            $this->assertNumberInRange($responsible['female_students'] ?? 0, 0, 5000, true, 'عدد الطالبات');

            if (mb_strlen($this->sanitize($responsible['challenges'] ?? '')) > 5000
                || mb_strlen($this->sanitize($responsible['notes_suggestions'] ?? '')) > 5000
            ) {
                throw new InvalidArgumentException('الملاحظات أو التحديات تتجاوز 5000 حرف.');
            }
        }
    }

    private function assertNumberInRange(mixed $value, float $minimum, float $maximum, bool $integer, string $label): void
    {
        $options = $integer ? FILTER_VALIDATE_INT : FILTER_VALIDATE_FLOAT;
        $validated = filter_var($value, $options);
        if ($validated === false || $validated < $minimum || $validated > $maximum) {
            throw new InvalidArgumentException("{$label} غير صالح.");
        }
    }
}
