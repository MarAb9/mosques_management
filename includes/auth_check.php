<?php

function requireAdmin() {
    if ($_SESSION['role'] != 'admin') {
        http_response_code(403);
        die("غير مصرح بالوصول إلى هذه الصفحة");
    }
}

function requireManager() {
    if (!in_array($_SESSION['role'], ['admin'])) {
        http_response_code(403);
        die("غير مصرح بالوصول إلى هذه الصفحة");
    }
}

function canEditMosque() {
    return in_array($_SESSION['role'], ['admin']);
}

function canDeleteMosque() {
    return $_SESSION['role'] == 'admin';
}

function canCreateMosque() {
    return in_array($_SESSION['role'], ['admin']);
}

function canImportData() {
    return $_SESSION['role'] == 'admin';
}

function canViewSensitiveData() {
    return in_array($_SESSION['role'], ['admin','user']);
}
?>