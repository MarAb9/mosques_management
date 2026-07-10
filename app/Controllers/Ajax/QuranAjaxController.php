<?php

declare(strict_types=1);

namespace App\Controllers\Ajax;

use App\Core\Controller;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\QuranProgramRepository;

final class QuranAjaxController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly QuranProgramRepository $programs,
        private readonly ErrorHandler $errors,
    ) {
        parent::__construct($view, $session);
    }

    /** Legacy ajax/get_quran_mosque_details.php */
    public function details(Request $request): Response
    {
        $id = $request->query('id');

        if ($id === null) {
            return $this->json(['success' => false, 'message' => 'معرف المسجد غير محدد']);
        }

        try {
            $program = $this->programs->findForDetails($id);

            if ($program === null) {
                return $this->json(['success' => false, 'message' => 'مسجد التحفيظ غير موجود']);
            }

            $program['responsibles'] = $this->programs->responsiblesOrderedById($id);

            return $this->json(['success' => true, 'data' => $program]);
        } catch (\Exception $e) {
            $this->errors->log($e);

            return $this->json(['success' => false, 'message' => 'حدث خطأ أثناء جلب البيانات']);
        }
    }
}
