<?php
require_once __DIR__ . '/../helper/CsrfHelper.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Student Violations System</title>
    <link rel="stylesheet" href="css/style.css" />

    <style>
        :root {
            --brand-blue: #0B5FA6;
            --btn-red: #E84C2A;
            --text: #1f2937;
            --muted: #6b7280;
            --card-radius: 10px;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body.login-page {
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .login-topbar {
            height: 70px;
            background: #0B5793;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            padding: 0 28px;
            border-top: 10px solid #f4d03f;
            box-sizing: border-box;
        }

        .login-topbar .brand {
            color: #fff;
            font-weight: 900;
            font-size: 34px;
            letter-spacing: 0.2px;
            display: flex;
            align-items: center;
            height: 100%;
            padding-top: 1px;
        }

        .login-topbar .brand img {
            height: 60px;
            max-height: calc(100% - 10px);
            width: auto;
            display: block;
        }

        .login-hero {
            background: url("assets/img/test6.png") center/cover no-repeat;
            position: relative;
            inset: 0;
        }

        .login-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.15);
        }

        .login-content {
            position: relative;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 90px 16px 24px;
        }

        .login-card {
            width: 420px;
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
            padding: 32px;
        }

        .login-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .login-logo img {
            height: 62px;
        }

        .admin-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 50;
        }

        .admin-modal.open {
            display: flex;
        }

        .admin-modal-dialog {
            width: min(420px, 100%);
            background: #fff;
            border-radius: var(--card-radius);
            padding: 28px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
        }

        .admin-modal-close {
            border: 0;
            background: #f3f4f6;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            font-size: 20px;
            line-height: 1;
            color: #374151;
            cursor: pointer;
            float: right;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Form Styles */
        .form-group {
            margin: 12px 0;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 6px;
            color: #111827;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            background: #fff;
            box-sizing: border-box;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        /* Button Styles */
        .btn-login {
            width: 100%;
            margin-top: 10px;
            padding: 12px 14px;
            border: 0;
            border-radius: 6px;
            background: var(--btn-red);
            color: #fff;
            font-weight: 900;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.35px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            filter: brightness(0.95);
        }

        .btn-microsoft {
            width: 100%;
            padding: 12px 14px;
            border: 0;
            border-radius: 6px;
            background: #0078D4;
            color: #fff;
            font-weight: 400;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.3s ease;
            margin-top: 10px;
            text-decoration: none;
            !important;
        }

        .btn-microsoft:hover {
            background: #083e6d;
        }

        .btn-microsoft-icon {
            height: 1em;
            width: auto;
            display: inline-block;
            object-fit: contain;
        }

        .alert {
            margin-bottom: 10px;
            border-radius: 8px;
            padding: 10px;
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
            font-size: 13px;
        }

        .login-footer {
            text-align: center;
            margin-top: 18px;
            font-size: 12px;
            color: var(--muted);
        }

        .login-help {
            text-align: center;
            margin-top: 12px;
            font-size: 13px;
            color: var(--muted);
        }

        .login-help a {
            color: var(--btn-red);
            text-decoration: none;
        }

        .login-help a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .login-card {
                width: min(420px, 94vw);
            }

            .login-topbar {
                padding: 0 16px;
            }

            .login-tabs {
                gap: 4px;
            }

            .login-tab-button {
                font-size: 12px;
                padding: 10px 8px;
            }
        }

        @media (min-width: 1200px) {
            .login-content {
                justify-content: flex-end;
                padding-right: 60px;
            }

            .login-card {
                width: 420px;
            }
        }
    </style>
</head>

<body class="login-page">
    <header class="login-topbar">
        <div class="brand">
            <img src="assets/img/axcelerate_logo.png" alt="axcelerate logo">
        </div>
    </header>

    <main class="login-hero">
        <div class="login-content">
            <div class="login-card">
                <div class="login-logo">
                    <img src="assets/img/ax_logo.png" alt="ax">
                </div>

                <div class="student-login">
                    <h1 style="text-align: center; font-size: 22px; color: #2c3e50; margin: 0 0 8px;">AXcelerate</h1>
                    <p style="text-align: center; color: #666; font-size: 13px; margin: 0 0 18px;">
                        Sign in with your Fairview STI email
                    </p>

                    <?php if (isset($_GET['student_error'])): ?>
                        <div class="alert">
                            <?php echo htmlspecialchars($_GET['student_error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($login_url)): ?>
                        <a href="<?php echo htmlspecialchars($login_url); ?>" class="btn-microsoft">
                            <img src="assets/icons/windows.jpg" alt="Windows" class="btn-microsoft-icon">
                            SIGN IN WITH YOUR O365 ACCOUNT
                        </a>
                    <?php else: ?>
                        <button onclick="location.reload()" class="btn-microsoft" type="button">
                            <img src="assets/icons/windows.jpg" alt="Windows" class="btn-microsoft-icon">
                            SIGN IN WITH YOUR O365 ACCOUNT
                        </button>
                    <?php endif; ?>

                    <button type="button" class="btn-login" id="openAdminModal" style="margin-top:16px;">
                        Admin Login
                    </button>
                </div>
            </div>
        </div>
    </main>

    <div class="admin-modal" id="adminModal" aria-hidden="true">
        <div class="admin-modal-dialog" role="dialog" aria-modal="true">
            <button class="admin-modal-close" type="button" id="closeAdminModal" aria-label="Close admin login">&times;</button>
            <h1 style="text-align: center; font-size: 22px; color: #2c3e50; margin: 0 0 18px;">Admin Login</h1>

            <?php if (!empty($error)): ?>
                <div class="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php?page=login" data-confirm="Sign in as admin?">
                <input type="hidden" name="login_type" value="admin">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <?php $formKey = 'admin_login_form'; $formToken = csrfGenerateFormToken($formKey); ?>
                <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="login-help">
                Forgot your password? <a href="index.php?page=admin_forgot_password">Request reset</a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('adminModal');
            const openBtn = document.getElementById('openAdminModal');
            const closeBtn = document.getElementById('closeAdminModal');

            function openModal() {
                modal?.classList.add('open');
                modal?.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                modal?.classList.remove('open');
                modal?.setAttribute('aria-hidden', 'true');
            }

            if (openBtn) {
                openBtn.addEventListener('click', openModal);
            }
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            }

            <?php if (!empty($error)): ?>
            openModal();
            <?php endif; ?>
        })();
    </script>

    <script>
        (function () {
            document.querySelectorAll('form[data-confirm]').forEach(form => {
                form.addEventListener('submit', function (e) {
                    const message = form.getAttribute('data-confirm');
                    if (message && !window.confirm(message)) {
                        e.preventDefault();
                        return;
                    }
                    if (form.dataset.submitted === 'true') {
                        e.preventDefault();
                        return;
                    }
                    form.dataset.submitted = 'true';
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn) btn.disabled = true;
                });
            });
        })();
    </script>
</body>

</html>
