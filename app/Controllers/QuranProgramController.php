<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\HttpException;
use App\Middleware\VerifyCsrf;
use App\Repositories\QuranProgramRepository;
use App\Services\QuranProgramService;
use App\Services\AuditLogger;
use PDOException;
use InvalidArgumentException;

final class QuranProgramController extends Controller
{
    /** Legacy hardcoded position dropdown options. */
    private const POSITION_OPTIONS = ['مدرر(ة)', 'مرشد(ة)', 'إمام', 'متطوع(ة)', 'رسمي(ة)'];

    public function __construct(
        View $view,
        Session $session,
        private readonly QuranProgramService $service,
        private readonly QuranProgramRepository $programs,
        private readonly VerifyCsrf $csrf,
        private readonly ErrorHandler $errors,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session);
    }

    // ── List (legacy quran_mosques.php) ───────────────────────────────────

    public function index(Request $request): Response
    {
        $query = (array) $request->query();
        $data = $this->service->listPage($query);

        $data += [
            'queryParams' => $query,
            'isAdmin' => $this->session->role() === 'admin',
            'csrfToken' => $this->session->csrfToken(),
        ];

        return $this->render('quran.index', $data);
    }

    // ── Create (legacy add_quran_mosque.php) ──────────────────────────────

    public function create(Request $request): Response
    {
        return $this->createForm();
    }

    public function store(Request $request): Response
    {
        try {
            $this->service->create((array) $request->post());
            $this->audit->record('quran_program.create', 'success', $request, [
                'mosque_registration_number' => $request->post('mosque_registration_number'),
            ]);

            return $this->redirectWithFlash('quran_mosques.php', 'success', 'تم إضافة مسجد التحفيظ بنجاح');
        } catch (InvalidArgumentException $e) {
            $this->audit->record('quran_program.create', 'rejected', $request, ['reason' => $e->getMessage()]);
            $this->session->flash('error', $e->getMessage());
            return $this->createForm();
        } catch (\Exception $e) {
            $this->errors->log($e);
            $this->audit->record('quran_program.create', 'failed', $request, ['error_type' => $e::class]);
            $this->session->flash('error', 'حدث خطأ أثناء إضافة مسجد التحفيظ. يرجى المحاولة لاحقاً');

            // Legacy fell through to re-render the add form.
            return $this->createForm();
        }
    }

    // ── Edit (legacy edit_quran_mosque.php) ───────────────────────────────

    public function edit(Request $request): Response
    {
        $found = $this->findProgramOr($request);
        if ($found instanceof Response) {
            return $found;
        }

        return $this->editForm($found);
    }

    public function update(Request $request): Response
    {
        $found = $this->findProgramOr($request);
        if ($found instanceof Response) {
            return $found;
        }

        $programId = $found['id'];

        // Legacy: verify_csrf_token("edit_quran_mosque.php?id=N") — dynamic
        // failure redirect, so the check lives here rather than in middleware.
        try {
            $this->csrf->assertValid($request);
        } catch (HttpException $e) {
            throw new HttpException(403, 'طلب غير صالح', 'edit_quran_mosque.php?id=' . urlencode((string) $programId));
        }

        try {
            $this->service->update($programId, (array) $request->post());
            $this->audit->record('quran_program.update', 'success', $request, ['program_id' => $programId]);

            return $this->redirectWithFlash('quran_mosques.php', 'success', 'تم تحديث مسجد التحفيظ بنجاح');
        } catch (InvalidArgumentException $e) {
            $this->audit->record('quran_program.update', 'rejected', $request, [
                'program_id' => $programId,
                'reason' => $e->getMessage(),
            ]);
            $this->session->flash('error', $e->getMessage());
            return $this->editForm($found);
        } catch (\Exception $e) {
            $this->errors->log($e);
            $this->audit->record('quran_program.update', 'failed', $request, [
                'program_id' => $programId,
                'error_type' => $e::class,
            ]);
            $this->session->flash('error', 'حدث خطأ أثناء تحديث مسجد التحفيظ. يرجى المحاولة لاحقاً');

            // Legacy fell through to re-render the edit form (fresh data).
            $found = $this->findProgramOr($request);

            return $found instanceof Response ? $found : $this->editForm($found);
        }
    }

    // ── Delete (legacy delete_quran_mosque.php) ───────────────────────────

    public function destroy(Request $request): Response
    {
        try {
            if ($request->post('selected_mosques') !== null) {
                $selected = array_filter((array) $request->post('selected_mosques'));

                if (empty($selected)) {
                    $this->session->flash('error', 'لم يتم تحديد مسجد تحفيظ للحذف');
                } else {
                    $count = $this->service->delete(array_values($selected));
                    $this->audit->record('quran_program.delete', 'success', $request, [
                        'program_ids' => array_values($selected),
                        'deleted_count' => $count,
                    ]);
                    $this->session->flash('success', "تم حذف {$count} مسجد تحفيظ بنجاح");
                }
            } elseif ($request->post('id') !== null) {
                $this->service->delete([$request->post('id')]);
                $this->audit->record('quran_program.delete', 'success', $request, [
                    'program_ids' => [$request->post('id')],
                    'deleted_count' => 1,
                ]);
                $this->session->flash('success', 'تم حذف مسجد التحفيظ بنجاح');
            } else {
                $this->session->flash('error', 'لم يتم تحديد مسجد تحفيظ للحذف');
            }
        } catch (PDOException $e) {
            $this->errors->log($e);
            $this->audit->record('quran_program.delete', 'failed', $request, ['error_type' => $e::class]);
            $this->session->flash('error', 'حدث خطأ أثناء حذف مسجد التحفيظ. يرجى المحاولة لاحقاً');
        }

        return $this->redirect('quran_mosques.php');
    }

    public function destroyMethodNotAllowed(Request $request): Response
    {
        return $this->redirectWithFlash('quran_mosques.php', 'error', 'طلب حذف غير صالح');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createForm(): Response
    {
        return $this->render('quran.create', [
            'errorMessage' => $this->session->pullFlash('error'),
            'mosques' => $this->programs->mosquesWithoutProgram(),
            'scheduleOptions' => $this->programs->scheduleEnumOptions(),
            'positionOptions' => self::POSITION_OPTIONS,
        ]);
    }

    /**
     * @param array<string, mixed> $program
     */
    private function editForm(array $program): Response
    {
        return $this->render('quran.edit', [
            'errorMessage' => $this->session->pullFlash('error'),
            'program' => $program,
            'programId' => $program['id'],
            'responsibles' => $this->programs->responsiblesRaw($program['id']),
            'mosques' => $this->programs->allMosquesForDropdown(),
            'scheduleOptions' => $this->programs->scheduleEnumOptions(),
            'positionOptions' => self::POSITION_OPTIONS,
        ]);
    }

    /**
     * Legacy edit-page preamble: missing id redirects silently; unknown id
     * flashes an error.
     *
     * @return array<string, mixed>|Response
     */
    private function findProgramOr(Request $request): array|Response
    {
        $id = $request->query('id');
        if ($id === null) {
            return $this->redirect('quran_mosques.php');
        }

        $program = $this->programs->findForEdit($id);
        if ($program === null) {
            return $this->redirectWithFlash('quran_mosques.php', 'error', 'مسجد التحفيظ غير موجود');
        }

        return $program;
    }
}
