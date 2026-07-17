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
use App\Services\BackupService;

final class AdministrationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueRepository $mosques,
        private readonly AuditLogger $audit,
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
