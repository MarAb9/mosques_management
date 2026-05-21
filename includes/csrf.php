<?php

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token($redirectTo = null) {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals(csrf_token(), $postedToken)) {
        http_response_code(403);

        if ($redirectTo !== null) {
            $_SESSION['error'] = 'طلب غير صالح';
            header('Location: ' . $redirectTo);
            exit();
        }

        die('طلب غير صالح');
    }

    return true;
}

?>
