<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0c342b">
    <title>تسجيل الدخول — نظام مساجد بركان</title>
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
                    <span class="login-brand__mark" aria-hidden="true"><i class="fas fa-mosque"></i></span>
                    <div>
                        <strong class="d-block">نظام مساجد بركان</strong>
                        <small class="text-muted">المجلس العلمي المحلي</small>
                    </div>
                </div>

                <span class="page-kicker">بوابة الإدارة المؤمنة</span>
                <h1 id="loginTitle">مرحباً بعودتك</h1>
                <p class="login-card__intro">أدخل بيانات حسابك للوصول إلى لوحة القيادة وإدارة السجلات التشغيلية للمساجد.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert" aria-live="assertive"><i class="fas fa-circle-exclamation me-2" aria-hidden="true"></i><?= $view->e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                    <div class="login-field mb-3">
                        <label for="username" class="form-label">اسم المستخدم</label>
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <input type="text" class="form-control" id="username" name="username" required autofocus autocomplete="username" autocapitalize="none" placeholder="أدخل اسم المستخدم">
                    </div>
                    <div class="login-field mb-3">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" placeholder="أدخل كلمة المرور">
                        <button type="button" class="btn btn-sm btn-link position-absolute top-50 end-0 mt-2 me-2 text-muted" id="passwordToggle" aria-label="إظهار كلمة المرور" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <button type="submit" name="login" value="1" class="btn btn-primary w-100 login-submit"><i class="fas fa-arrow-left-to-bracket me-2" aria-hidden="true"></i>دخول آمن إلى النظام</button>
                </form>

                <p class="login-security"><i class="fas fa-shield-halved" aria-hidden="true"></i><span>جلسة محمية ومخصصة للمستخدمين المخولين فقط</span></p>
            </div>
        </section>

        <section class="login-visual" aria-label="هوية أطلس نور البصرية">
            <div class="login-visual__content">
                <div class="arch-scene decorative-scene" aria-hidden="true">
                    <span class="arch-scene__layer"></span><span class="arch-scene__layer"></span><span class="arch-scene__layer"></span>
                </div>
                <span class="page-kicker">Atlas Noor · أطلس نور</span>
                <h2>معرفة موثوقة لخدمة بيوت الله</h2>
                <p>منصة مؤسساتية تجمع وضوح الإدارة مع هوية معمارية مغربية مستلهمة من أقواس المساجد ونور الشرق.</p>
            </div>
        </section>
    </main>
    <script src="assets/dist/login.min.js"></script>
</body>
</html>
