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
use App\Repositories\GuideImamRepository;
use App\Repositories\MosqueRepository;
use App\Services\MosqueExportService;
use App\Services\MosqueImportService;

final class ImportExportController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueImportService $importer,
        private readonly MosqueExportService $exporter,
        private readonly MosqueRepository $mosques,
        private readonly GuideImamRepository $guideImams,
        private readonly VerifyCsrf $csrf,
        private readonly ErrorHandler $errors,
    ) {
        parent::__construct($view, $session);
    }

    /** GET import_export.php — page, or export when ?export is present. */
    public function handle(Request $request): Response
    {
        if ($request->query('export') !== null) {
            return $this->export($request);
        }

        return $this->page();
    }

    /** POST import_export.php — Excel import (legacy branch order kept). */
    public function import(Request $request): Response
    {
        $file = $request->file('import_file');

        // Legacy: a POST without an uploaded file just renders the page.
        if ($file === null) {
            return $this->page();
        }

        // Legacy order: permission first, then CSRF.
        if ($this->session->role() !== 'admin') {
            throw new HttpException(403, 'غير مصرح باستيراد البيانات');
        }

        $this->csrf->assertValid($request);

        try {
            $result = $this->importer->import((string) $file['tmp_name']);
            $this->session->flash('success', $this->importer->successMessage($result));
        } catch (\Exception $e) {
            $this->errors->log($e);
            $this->session->flash('error', 'حدث خطأ أثناء استيراد البيانات. يرجى التحقق من الملف والمحاولة لاحقاً');
        }

        return $this->redirect('import_export.php');
    }

    private function export(Request $request): Response
    {
        try {
            $query = (array) $request->query();
            $mosques = $this->exporter->fetchRows($query);

            if (($query['format'] ?? '') == 'word') {
                $phpWord = $this->exporter->buildWordDocument($mosques);
                $filename = 'مساجد_غير_محددة_الموقع.docx';

                return Response::stream($this->exporter->wordWriter($phpWord), [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'Content-Disposition' => 'attachment;filename="' . $filename . '"',
                    'Cache-Control' => 'max-age=0',
                ]);
            }

            $isNoLocation = isset($query['no_location']) && $query['no_location'] == '1';
            $spreadsheet = $this->exporter->buildSpreadsheet($mosques, $isNoLocation);

            $filename = $isNoLocation ? 'مساجد_غير_محددة_الموقع.xlsx' : 'مساجد_إقليم_بركان.xlsx';

            return Response::stream($this->exporter->spreadsheetWriter($spreadsheet), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment;filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            $this->errors->log($e);

            return $this->redirectWithFlash('import_export.php', 'error', 'حدث خطأ أثناء تصدير البيانات. يرجى المحاولة لاحقاً');
        }
    }

    private function page(): Response
    {
        return $this->render('import_export.index', [
            'successMessage' => $this->session->pullFlash('success'),
            'errorMessage' => $this->session->pullFlash('error'),
            'isAdmin' => $this->session->role() === 'admin',
            'statuses' => $this->mosques->distinctColumnForExport('status'),
            'fridayPrayers' => $this->mosques->distinctColumnForExport('friday_prayer'),
            'communities' => $this->mosques->distinctColumnForExport('community'),
            'literacyPrograms' => $this->mosques->distinctColumnForExport('literacy_program'),
            'guidancePrograms' => $this->mosques->distinctColumnForExport('guidance_program'),
            'guideImams' => $this->guideImams->allWithMosqueCounts(),
        ]);
    }
}
