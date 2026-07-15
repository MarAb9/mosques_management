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
use App\Services\AuditLogger;

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
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session);
    }

    /** GET import_export.php — page, or export when ?export is present. */
    public function handle(Request $request): Response
    {
        if ($request->query('import_error_report') !== null) {
            return $this->errorReport($request);
        }

        if ($request->query('export') !== null) {
            return $this->export($request);
        }

        return $this->page();
    }

    /** POST import_export.php — preview, confirm, rollback, or direct Excel import. */
    public function import(Request $request): Response
    {
        $file = $request->file('import_file');

        if (!$this->session->canImportData()) {
            throw new HttpException(403, 'غير مصرح باستيراد البيانات');
        }

        if ($file === null && $request->post('import_token') === null && $request->post('rollback_last_import') === null) {
            return $this->page();
        }

        $this->csrf->assertValid($request);

        if ($request->post('rollback_last_import') !== null) {
            $result = $this->importer->rollbackLastImport();
            $this->audit->record('mosque.import.rollback', 'success', $request, $result);
            $this->session->flash('success', 'تم التراجع عن آخر استيراد وحذف ' . $result['deleted'] . ' سجل.');

            return $this->redirect('import_export.php');
        }

        if ($request->post('import_token') !== null) {
            try {
                $result = $this->importer->importCached((string) $request->post('import_token'));
                $this->audit->record('mosque.import.confirmed', 'success', $request, $result);
                $this->session->flash('success', $this->importer->successMessage($result));
            } catch (\Exception $e) {
                $this->errors->log($e);
                $this->audit->record('mosque.import.confirmed', 'failed', $request, ['error_type' => $e::class]);
                $this->session->flash('error', 'تعذر تنفيذ الاستيراد المؤكد. يرجى إعادة رفع الملف.');
            }

            return $this->redirect('import_export.php');
        }

        $uploadErrors = $this->importer->validateUpload($file);
        if ($uploadErrors !== []) {
            $this->audit->record('mosque.import', 'rejected', $request, ['reasons' => $uploadErrors]);
            $this->session->flash('error', implode(' ', $uploadErrors));

            return $this->redirect('import_export.php');
        }

        if ($request->post('preview_import') !== null) {
            try {
                $preview = $this->importer->storePreview($file);
                $this->audit->record('mosque.import.preview', 'success', $request, [
                    'total_rows' => $preview['total_rows'] ?? 0,
                    'valid_rows' => $preview['valid_rows'] ?? 0,
                    'duplicate_rows' => $preview['duplicate_rows'] ?? 0,
                ]);

                return $this->page($preview);
            } catch (\Exception $e) {
                $this->errors->log($e);
                $this->audit->record('mosque.import.preview', 'failed', $request, ['error_type' => $e::class]);
                $this->session->flash('error', 'تعذر إنشاء معاينة الاستيراد. يرجى التحقق من الملف.');

                return $this->redirect('import_export.php');
            }
        }

        try {
            $result = $this->importer->import((string) $file['tmp_name'], (string) $file['name']);
            $this->audit->record('mosque.import', 'success', $request, $result);
            $this->session->flash('success', $this->importer->successMessage($result));
        } catch (\Exception $e) {
            $this->errors->log($e);
            $this->audit->record('mosque.import', 'failed', $request, ['error_type' => $e::class]);
            $this->session->flash('error', 'حدث خطأ أثناء استيراد البيانات. يرجى التحقق من الملف والمحاولة لاحقا');
        }

        return $this->redirect('import_export.php');
    }
    private function export(Request $request): Response
    {
        try {
            $query = (array) $request->query();
            $mosques = $this->exporter->fetchRows($query);
            $this->audit->record('mosque.export', 'success', $request, [
                'format' => ($query['format'] ?? '') === 'word' ? 'word' : 'excel',
                'row_count' => count($mosques),
                'filters' => $query,
            ]);

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
            $this->audit->record('mosque.export', 'failed', $request, ['error_type' => $e::class]);

            return $this->redirectWithFlash('import_export.php', 'error', 'حدث خطأ أثناء تصدير البيانات. يرجى المحاولة لاحقاً');
        }
    }


    private function errorReport(Request $request): Response
    {
        try {
            $token = (string) $request->query('import_error_report', '');
            $csv = $this->importer->errorReportCsv($token);

            return Response::stream(static function () use ($csv): void {
                echo $csv;
            }, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment;filename="import_errors.csv"',
                'Cache-Control' => 'no-store',
            ]);
        } catch (\Exception $e) {
            $this->errors->log($e);
            return $this->redirectWithFlash('import_export.php', 'error', 'تعذر تنزيل تقرير أخطاء الاستيراد.');
        }
    }
    private function page(?array $importPreview = null): Response
    {
        return $this->render('import_export.index', [
            'successMessage' => $this->session->pullFlash('success'),
            'errorMessage' => $this->session->pullFlash('error'),
            'isAdmin' => $this->session->role() === 'admin',
            'canImport' => $this->session->canImportData(),
            'importPreview' => $importPreview,
            'lastImport' => $this->importer->lastImportSummary(),
            'statuses' => $this->mosques->distinctColumnForExport('status'),
            'fridayPrayers' => $this->mosques->distinctColumnForExport('friday_prayer'),
            'communities' => $this->mosques->distinctColumnForExport('community'),
            'literacyPrograms' => $this->mosques->distinctColumnForExport('literacy_program'),
            'guidancePrograms' => $this->mosques->distinctColumnForExport('guidance_program'),
            'guideImams' => $this->guideImams->allWithMosqueCounts(),
        ]);
    }
}
