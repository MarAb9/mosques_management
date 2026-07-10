<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

final class LoginController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly AuthService $auth,
    ) {
        parent::__construct($view, $session);
    }

    public function show(Request $request): Response
    {
        return $this->loginPage();
    }

    public function login(Request $request): Response
    {
        // Same trigger condition as legacy includes/auth.php: the submit
        // button is named "login"; a POST without it just renders the page.
        if ($request->post('login') === null) {
            return $this->loginPage();
        }

        $username = trim((string) $request->post('username', ''));
        $password = trim((string) $request->post('password', ''));

        if ($this->auth->attempt($username, $password)) {
            return $this->redirect('index.php');
        }

        return $this->loginPage('اسم المستخدم أو كلمة المرور غير صحيحة');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();

        return $this->redirect('login.php');
    }

    private function loginPage(?string $error = null): Response
    {
        // Standalone page — not wrapped in the shared layout.
        return $this->render('auth.login', ['error' => $error], null);
    }
}
