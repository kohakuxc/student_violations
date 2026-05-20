<?php
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/AuditLogger.php';
require_once __DIR__ . '/../model/AdminModel.php';

$adminModel = new AdminModel();
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$resetRecord = $token !== '' ? $adminModel->findValidResetToken($token) : null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);
        if (!$resetRecord) {
            throw new Exception('Reset link is invalid or expired.');
        }

        $password = trim((string) ($_POST['password'] ?? ''));
        $confirm = trim((string) ($_POST['confirm_password'] ?? ''));
        if ($password === '' || $confirm === '') {
            throw new Exception('Please enter and confirm your new password.');
        }
        if ($password !== $confirm) {
            throw new Exception('Passwords do not match.');
        }
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters.');
        }

        $adminModel->updatePassword((int) $resetRecord['officer_id'], $password);
        $adminModel->markResetTokenUsed((int) $resetRecord['reset_id']);

        $auditLogger = new AuditLogger();
        $auditLogger->log((int) $resetRecord['officer_id'], 'officer', 'admin_password_reset_completed', 'officer', (int) $resetRecord['officer_id']);

        $success = 'Password updated successfully. You can now sign in.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'view/admin_reset_password.php';
