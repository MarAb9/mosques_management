<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\MosqueRepository;
use App\Services\AuditLogger;
use App\Services\AuditLogService;
use App\Services\BackupService;
use App\Services\DeletedMosqueService;

final class AdministrationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueRepository $mosques,
        private readonly AuditLogService $auditLog,
        private readonly AuditLogger $audit,
        private readonly DeletedMosqueService $deletedMosques,
        private readonly BackupService $backup,
    ) {
        parent::__construct($view, $session);
    }

    public function dataQuality(Request $request): Response
    {
        $issue = (string) $request->query('issue', 'missing_coordinates');

        return $this->render('admin.data_quality', [
            'summary' => $this->mosques->dataQualitySummary(),
            'issue' => $issue,
            'samples' => $this->mosques->dataQualitySamples($issue, 30),
        ]);
    }

    public function audit(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        return $this->render('admin.audit', [
            'events' => $this->auditLog->recent(80, $q !== '' ? $q : null),
            'q' => $q,
        ]);
    }

    public function trash(Request $request): Response
    {
        return $this->render('admin.trash', [
            'deletedMosques' => $this->deletedMosques->recent(80),
            'successMessage' => $this->session->pullFlash('success'),
            'errorMessage' => $this->session->pullFlash('error'),
        ]);
    }

    public function restore(Request $request): Response
    {
        $registrationNumber = trim((string) $request->post('registration_number', ''));
        if ($registrationNumber === '') {
            return $this->redirectWithFlash('trash.php', 'error', 'طلب استعادة غير صالح');
        }

        if ($this->deletedMosques->restore($registrationNumber)) {
            $this->audit->record('mosque.restore', 'success', $request, ['registration_number' => $registrationNumber]);
            return $this->redirectWithFlash('trash.php', 'success', 'تمت استعادة المسجد بنجاح');
        }

        $this->audit->record('mosque.restore', 'failed', $request, ['registration_number' => $registrationNumber]);
        return $this->redirectWithFlash('trash.php', 'error', 'تعذرت الاستعادة. قد يكون الرمز الوطني موجودا حاليا.');
    }

    public function backup(Request $request): Response
    {
        $this->audit->record('backup.download', 'success', $request);
        $payload = $this->backup->applicationData();
        $filename = 'mosques-backup-' . gmdate('Ymd-His') . '.json';

        return Response::stream(static function () use ($payload): void {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
        }, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }
}