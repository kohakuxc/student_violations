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
        }

        .login-topbar .brand {
            color: #fff;
            font-weight: 900;
            font-size: 34px;
            letter-spacing: 0.2px;
        }

        .login-topbar .brand img {
            height: 32px;
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

        /* Tabs Styling */
        .login-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 24px;
            border-bottom: 2px solid #e0e0e0;
        }

        .login-tab-button {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .login-tab-button:hover {
            color: var(--brand-blue);
        }

        .login-tab-button.active {
            color: var(--brand-blue);
            border-bottom-color: var(--brand-blue);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
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
            <img src="assets/img/onesti_logo.png" alt="one sti">
        </div>
    </header>

    <main class="login-hero">
        <div class="login-content">
            <div class="login-card">
                <div class="login-logo">
                    <img src="assets/img/sti_logo.png" alt="STI">
                </div>

                <!-- Tab Navigation -->
                <div class="login-tabs">
                    <button class="login-tab-button" data-tab="student-login">
                        👤 Student
                    </button>

                    <button class="login-tab-button active" data-tab="admin-login">
                        👮 Admin
                    </button>
                </div>

                <!-- Admin Login Tab -->
                <div id="admin-login" class="tab-content active">
                    <h1 style="text-align: center; font-size: 22px; color: #2c3e50; margin: 0 0 18px;">Admin Login
                    </h1>

                    <?php if (!empty($error)): ?>
                        <div class="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php?page=login">
                        <input type="hidden" name="login_type" value="admin">

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required autofocus>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn-login">Sign In</button>
                    </form>

                    <div class="login-help">
                        Having trouble logging in? <a href="#">Click here</a>
                    </div>
                </div>

                <!-- Student Login Tab -->
                <div id="student-login" class="tab-content">
                    <h1 style="text-align: center; font-size: 22px; color: #2c3e50; margin: 0 0 8px;">Student Login
                    </h1>
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
                        <button onclick="location.reload()" class="btn-microsoft">
                            <img src="assets/icons/windows.jpg" alt="Windows" class="btn-microsoft-icon">
                            SIGN IN WITH YOUR O365 ACCOUNT
                        </button>
                    <?php endif; ?>
                </div>



                <div class="login-footer">
                    © STI Education Services Group, Inc. All Rights Reserved.
                </div>
            </div>
        </div>
    </main>

    <script>
        // Tab Switching Logic
        document.querySelectorAll('.login-tab-button').forEach(button => {
            button.addEventListener('click', () => {
                const tabName = button.getAttribute('data-tab');

                // Remove active class from all buttons and content
                document.querySelectorAll('.login-tab-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });

                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                document.getElementById(tabName).classList.add('active');

                // Focus first input in the active tab
                const firstInput = document.getElementById(tabName).querySelector('input, button');
                if (firstInput) firstInput.focus();
            });
        });
    </script>
</body>

</html>