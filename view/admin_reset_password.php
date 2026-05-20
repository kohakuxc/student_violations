<?php
require_once __DIR__ . '/../helper/CsrfHelper.php';
$formKey = 'admin_reset_password';
$formToken = csrfGenerateFormToken($formKey);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Set New Password - Student Violations System</title>
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
        .alert.success { background: #dcfce7; }
        .reset-shell a { color: #2563eb; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>
    <div class="reset-shell">
        <h1>Set New Password</h1>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <p><a href="index.php?page=login">Return to login</a></p>
        <?php elseif ($resetRecord): ?>
            <form method="POST" action="index.php?page=admin_reset_password" data-confirm="Update your password now?">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="password">New password</label>
                <input type="password" id="password" name="password" required>

                <label for="confirm_password" style="margin-top:12px;">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        <?php else: ?>
            <div class="alert error">Reset link is invalid or expired.</div>
            <p><a href="index.php?page=login">Return to login</a></p>
        <?php endif; ?>
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
