<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\GuideImamRepository;
use App\Repositories\MosqueRepository;
use App\Services\MosqueSearchService;
use App\Services\MosqueWriteService;
use PDOException;

final class MosqueController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueSearchService $search,
        private readonly MosqueWriteService $writer,
        private readonly MosqueRepository $mosques,
        private readonly GuideImamRepository $guideImams,
        private readonly ErrorHandler $errors,
    ) {
        parent::__construct($view, $session);
    }

    // ── List (legacy mosques.php) ─────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = (array) $request->query();
        $data = $this->search->listPage($query);

        // One-shot row highlight (legacy behavior: cleared only when the
        // highlighted mosque is on the rendered page).
        $highlight = $this->session->get('highlight_mosque_national_code');
        if ($highlight !== null) {
            foreach ($data['mosques'] as $row) {
                if ($row['national_code'] == $highlight) {
                    $this->session->remove('highlight_mosque_national_code');
                    break;
                }
            }
        }

        $data += [
            'queryParams' => $query,
            'isAdmin' => $this->session->role() === 'admin',
            'csrfToken' => $this->session->csrfToken(),
            'highlightNationalCode' => $highlight,
        ];

        return $this->render('mosques.index', $data);
    }

    // ── Create (legacy add_mosque.php) ────────────────────────────────────

    public function create(Request $request): Response
    {
        return $this->createForm([], []);
    }

    public function store(Request $request): Response
    {
        $result = $this->writer->create((array) $request->post(), $this->files($request));

        if ($result['success']) {
            return $this->redirectWithFlash('mosques.php', 'success', 'تمت إضافة المسجد بنجاح');
        }

        return $this->createForm($result['formData'], $result['errors']);
    }

    // ── Edit (legacy edit_mosque.php) ─────────────────────────────────────

    public function edit(Request $request): Response
    {
        $mosque = $this->findMosqueOrNull($request);
        if ($mosque === null) {
            return $this->redirect('mosques.php');
        }

        $formData = $mosque;
        $formData['construction_year'] = !empty($mosque['construction_date'])
            ? date('Y', strtotime((string) $mosque['construction_date']))
            : '';

        return $this->editForm($formData, []);
    }

    public function update(Request $request): Response
    {
        $mosque = $this->findMosqueOrNull($request);
        if ($mosque === null) {
            return $this->redirect('mosques.php');
        }

        $result = $this->writer->update($mosque, (array) $request->post(), $this->files($request));

        if ($result['success']) {
            return $this->redirectWithFlash('mosques.php', 'success', 'تم تحديث بيانات المسجد بنجاح');
        }

        return $this->editForm($result['formData'], $result['errors']);
    }

    // ── Delete (legacy delete_mosque.php) ─────────────────────────────────

    public function destroy(Request $request): Response
    {
        try {
            if ($request->post('selected_mosques') !== null) {
                $selected = array_filter((array) $request->post('selected_mosques'));

                if (empty($selected)) {
                    $this->session->flash('error', 'لم يتم تحديد مسجد للحذف');
                } else {
                    $count = $this->writer->delete(array_values($selected));
                    $this->session->flash('success', "تم حذف {$count} مسجد(اً) بنجاح");
                }
            } elseif ($request->post('id') !== null) {
                $this->writer->delete([$request->post('id')]);
                $this->session->flash('success', 'تم حذف المسجد بنجاح');
            } else {
                $this->session->flash('error', 'لم يتم تحديد مسجد للحذف');
            }
        } catch (PDOException $e) {
            $this->errors->log($e);
            $this->session->flash('error', 'حدث خطأ أثناء حذف المسجد. يرجى المحاولة لاحقاً');
        }

        return $this->redirect('mosques.php');
    }

    /** Legacy: GET requests to the delete endpoint bounce with an error. */
    public function destroyMethodNotAllowed(Request $request): Response
    {
        return $this->redirectWithFlash('mosques.php', 'error', 'طلب حذف غير صالح');
    }

    // ── Bulk delete (legacy bulk_delete_mosques.php) ──────────────────────

    public function bulkDestroy(Request $request): Response
    {
        if ($request->post('selected_mosques') === null) {
            return $this->redirectWithFlash('mosques.php', 'error', 'طلب غير صالح');
        }

        $selected = array_filter((array) $request->post('selected_mosques'));

        if (empty($selected)) {
            return $this->redirectWithFlash('mosques.php', 'error', 'لم يتم تحديد أي مساجد للحذف');
        }

        try {
            $count = $this->writer->delete(array_values($selected));
            $this->session->flash('success', "تم حذف {$count} مسجد(اً) بنجاح");
        } catch (PDOException $e) {
            $this->errors->log($e);
            $this->session->flash('error', 'حدث خطأ أثناء الحذف الجماعي. يرجى المحاولة لاحقاً');
        }

        return $this->redirect('mosques.php');
    }

    public function bulkDestroyMethodNotAllowed(Request $request): Response
    {
        return $this->redirectWithFlash('mosques.php', 'error', 'طلب غير صالح');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $formData
     * @param array<string, string> $errors
     */
    private function createForm(array $formData, array $errors): Response
    {
        return $this->render('mosques.create', [
            'formData' => $formData,
            'errors' => $errors,
            'guideImams' => $this->guideImams->all(),
        ]);
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, string> $errors
     */
    private function editForm(array $formData, array $errors): Response
    {
        return $this->render('mosques.edit', [
            'formData' => $formData,
            'errors' => $errors,
            'guideImams' => $this->guideImams->all(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMosqueOrNull(Request $request): ?array
    {
        $id = $request->query('id');
        if ($id === null) {
            return null;
        }

        return $this->mosques->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function files(Request $request): array
    {
        $files = [];
        foreach (['main_image'] as $key) {
            $file = $request->file($key);
            if ($file !== null) {
                $files[$key] = $file;
            }
        }

        return $files;
    }
}
