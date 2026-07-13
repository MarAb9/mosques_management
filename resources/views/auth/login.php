<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - مساجد إقليم بركان</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --success-color: #4cc9f0;
            --overlay-color: rgba(67, 97, 238, 0.85);
        }

        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Tajawal', 'Segoe UI', sans-serif;
        }

        .full-page-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/1.jpg') no-repeat center center;
            background-size: cover;
            z-index: -1;
        }

        .login-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-form-container {
            background: rgba(255, 255, 255, 0.85);
            border-radius: 15px;
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInRight 0.8s ease-out;
        }

        .login-content {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .login-content h2 {
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 2rem;
            color: var(--dark-color);
        }

        .login-content p {
            font-size: 1rem;
            color: var(--dark-color);
            opacity: 0.8;
        }

        .form-title {
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
            font-size: 1.8rem;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }

        .form-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 50%;
            transform: translateX(50%);
            width: 60px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .form-control {
            padding: 0.85rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
            background: white;
        }

        .btn-login {
            padding: 0.85rem;
            font-weight: 600;
            margin-top: 1rem;
            border-radius: 8px;
            background: var(--primary-color);
            border: none;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn-login:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .testimonial {
            margin-top: 2rem;
            font-style: italic;
            font-size: 0.9rem;
            position: relative;
            padding: 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.3);
            color: var(--dark-color);
        }

        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .login-form-container {
                padding: 2rem;
                margin: 1rem;
                backdrop-filter: blur(5px);
            }

            .login-content h2 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 575.98px) {
            .login-form-container {
                padding: 1.5rem;
            }

            .form-title {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <!-- Full page background image -->
    <div class="full-page-bg animate__animated animate__fadeIn"></div>

    <!-- Login container -->
    <div class="container login-container">
        <div class="login-form-container">
            <div class="login-content animate__animated animate__fadeIn">
                <h2>مرحبًا بعودتك!</h2>
                <p>أدخل بيانات الدخول للوصول إلى لوحة التحكم الخاصة بمساجد إقليم بركان</p>
            </div>

            <div class="login-form">
                <h3 class="form-title">تسجيل الدخول</h3>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">اسم المستخدم</label>
                        <input type="text" class="form-control animate__animated animate__fadeIn animate__delay-1s" id="username" name="username" required placeholder="أدخل اسم المستخدم">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <input type="password" class="form-control animate__animated animate__fadeIn animate__delay-1s" id="password" name="password" required placeholder="أدخل كلمة المرور">
                    </div>
                    <button type="submit" name="login" class="btn btn-primary w-100 btn-login animate__animated animate__fadeInUp animate__delay-2s">
                        <i class="fas fa-sign-in-alt me-2"></i> تسجيل الدخول
                    </button>
                </form>

               <!-- <div class="testimonial animate__animated animate__fadeIn animate__delay-3s">
                    "هذا النظام سهل عملية إدارة المساجد بشكل كبير ووفر علينا الكثير من الوقت والجهد"
                </div>  -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>