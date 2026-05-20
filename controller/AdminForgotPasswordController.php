<?php
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/RateLimiter.php';
require_once __DIR__ . '/../helper/AuditLogger.php';
require_once __DIR__ . '/../model/AdminModel.php';

$message = $_SESSION['admin_reset_flash'] ?? '';
unset($_SESSION['admin_reset_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);

        $username = trim((string) ($_POST['username'] ?? ''));
        if ($username === '') {
            throw new Exception('Username is required.');
        }

        if (!rateLimitCheck('admin_reset_request_' . $username, 3, 300)) {
            throw new Exception('Too many reset requests. Please wait and try again.');
        }

        $adminModel = new AdminModel();
        $admin = $adminModel->getAdminByUsername($username);
        if ($admin && !empty($admin['is_active'])) {
            $token = $adminModel->createPasswordResetToken((int) $admin['officer_id'], null);
            $auditLogger = new AuditLogger();
            $auditLogger->log((int) $admin['officer_id'], 'officer', 'admin_password_reset_requested', 'officer', (int) $admin['officer_id']);
        }

        $_SESSION['admin_reset_flash'] = 'If the account exists, a superadmin can provide a reset link.';
        header('Location: index.php?page=admin_forgot_password');
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

include 'view/admin_forgot_password.php';
