<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0c342b">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <title>تسجيل الدخول — نظام إدارة مساجد إقليم بركان</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/dist/app.min.css">
</head>
<body class="login-page">
    <a class="skip-link" href="#loginForm">الانتقال إلى نموذج الدخول</a>
    <main class="login-shell">
        <section class="login-panel" aria-labelledby="loginTitle">
            <div class="login-card">
                <div class="login-brand">
                    <span class="login-brand__mark" aria-hidden="true"><img src="assets/images/logo.png" width="36" height="36" alt=""></span>
                    <div>
                        <strong class="d-block">نظام إدارة مساجد إقليم بركان</strong>
                        <small>المجلس العلمي المحلي بإقليم بركان</small>
                    </div>
                </div>

                <div class="login-intro">
                    <span>مرحباً بعودتك</span>
                    <h1 id="loginTitle">تسجيل الدخول</h1>
                    <p>أدخل بياناتك للوصول إلى بوابة إدارة مساجد إقليم بركان.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert" aria-live="assertive"><i class="fas fa-circle-exclamation me-2" aria-hidden="true"></i><?= $view->e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                    <div class="login-field mb-3">
                        <label for="username" class="form-label">اسم المستخدم</label>
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <input type="text" class="form-control" id="username" name="username" required autofocus autocomplete="username" autocapitalize="none">
                    </div>
                    <div class="login-field mb-3">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="btn btn-sm btn-link position-absolute top-50 end-0 mt-2 me-2 text-muted" id="passwordToggle" aria-label="إظهار كلمة المرور" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <button type="submit" name="login" value="1" class="btn btn-primary w-100 login-submit"><i class="fas fa-arrow-left-to-bracket me-2" aria-hidden="true"></i>تسجيل الدخول</button>
                </form>

                <details class="login-recovery">
                    <summary><i class="fas fa-key" aria-hidden="true"></i>نسيت كلمة المرور؟</summary>
                    <div class="login-recovery__content" role="note">
                        <strong>طلب إعادة تعيين كلمة المرور</strong>
                        <p>تواصل مع المسؤول العام للنظام لتأكيد هويتك وتعيين كلمة مرور جديدة.</p>
                        <small>حفاظاً على بيانات المساجد، لا يمكن استرجاع كلمة المرور الحالية أو تغييرها تلقائياً.</small>
                    </div>
                </details>
            </div>
        </section>
    </main>
    <script src="assets/dist/login.min.js"></script>
</body>
</html>
