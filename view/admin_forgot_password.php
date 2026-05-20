<?php
require_once __DIR__ . '/../helper/CsrfHelper.php';
$formKey = 'admin_forgot_password';
$formToken = csrfGenerateFormToken($formKey);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Password Reset - Student Violations System</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
        body { background:#f8fafc; font-family: "Segoe UI", sans-serif; }
        .reset-shell { max-width: 420px; margin: 80px auto; background:#fff; padding:24px; border-radius:12px; box-shadow:0 10px 30px rgba(15,23,42,0.12); }
        .reset-shell h1 { font-size: 20px; margin-bottom: 12px; }
        .reset-shell label { font-weight: 700; margin-bottom: 6px; display:block; }
        .reset-shell input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
        .reset-shell .btn { width: 100%; margin-top: 12px; }
        .alert { margin: 12px 0; padding: 10px 12px; background: #eef2ff; border-radius: 8px; }
        .alert.error { background: #fee2e2; }
        .reset-shell a { color: #2563eb; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>
    <div class="reset-shell">
        <h1>Admin Password Reset</h1>
        <p style="color:#6b7280;font-size:13px;">Enter your username. A superadmin can provide a reset link if your account exists.</p>

        <?php if (!empty($message)): ?>
            <div class="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=admin_forgot_password" data-confirm="Request a password reset link?">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <button type="submit" class="btn btn-primary">Request Reset</button>
        </form>

        <p style="margin-top:12px;"><a href="index.php?page=login">Back to login</a></p>
    </div>

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
