<?php
require_once __DIR__ . '/../helper/AuthHelper.php';
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/RateLimiter.php';
require_once __DIR__ . '/../helper/AuditLogger.php';
require_once __DIR__ . '/../model/AdminModel.php';
require_once __DIR__ . '/../model/StudentAccountModel.php';
require_once __DIR__ . '/../model/AuditLogModel.php';

requireSuperAdmin();

$adminModel = new AdminModel();
$studentAccountModel = new StudentAccountModel();
$auditLogger = new AuditLogger();
$auditLogModel = new AuditLogModel();

$flash = $_SESSION['admin_management_flash'] ?? null;
unset($_SESSION['admin_management_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create_admin':
                if (!rateLimitCheck('create_admin_' . (int) $_SESSION['officer_id'], 5, 300)) {
                    throw new Exception('Too many admin creations. Please wait.');
                }
                $username = trim((string) ($_POST['username'] ?? ''));
                $name = trim((string) ($_POST['name'] ?? ''));
                $password = trim((string) ($_POST['password'] ?? ''));
                $isAdmin = !empty($_POST['is_admin']);
                $isSuperadmin = !empty($_POST['is_superadmin']);
                $canImport = !empty($_POST['can_import_excel']);
                if ($username === '' || $name === '' || $password === '') {
                    throw new Exception('Username, name, and password are required.');
                }
                if (strlen($password) < 8) {
                    throw new Exception('Password must be at least 8 characters.');
                }
                $newId = $adminModel->createAdmin($username, $name, $password, $isAdmin, $isSuperadmin, $canImport);
                if (!$newId) {
                    throw new Exception('Unable to create admin account.');
                }
                $auditLogger->log((int) $_SESSION['officer_id'], 'superadmin', 'admin_created', 'officer', $newId, [
                    'username' => $username
                ]);
                $_SESSION['admin_management_flash'] = ['type' => 'success', 'message' => 'Admin account created.'];
                break;

            case 'update_admin':
                $officerId = (int) ($_POST['officer_id'] ?? 0);
                if (!$officerId) {
                    throw new Exception('Invalid admin selection.');
                }
                $isActive = !empty($_POST['is_active']);
                $isAdmin = !empty($_POST['is_admin']);
                $isSuperadmin = !empty($_POST['is_superadmin']);
                $canImport = !empty($_POST['can_import_excel']);
                $adminModel->updateAdminStatus($officerId, $isActive);
                $adminModel->updateAdminPermissions($officerId, $isAdmin, $isSuperadmin, $canImport);
                $auditLogger->log((int) $_SESSION['officer_id'], 'superadmin', 'admin_updated', 'officer', $officerId, [
                    'is_active' => $isActive,
                    'is_admin' => $isAdmin,
                    'is_superadmin' => $isSuperadmin,
                    'can_import_excel' => $canImport
                ]);
                $_SESSION['admin_management_flash'] = ['type' => 'success', 'message' => 'Admin permissions updated.'];
                break;

            case 'generate_reset':
                $officerId = (int) ($_POST['officer_id'] ?? 0);
                if (!$officerId) {
                    throw new Exception('Invalid admin selection.');
                }
                $token = $adminModel->createPasswordResetToken($officerId, (int) $_SESSION['officer_id']);
                $link = 'index.php?page=admin_reset_password&token=' . urlencode($token);
                $auditLogger->log((int) $_SESSION['officer_id'], 'superadmin', 'admin_password_reset_generated', 'officer', $officerId);
                $_SESSION['admin_management_flash'] = ['type' => 'success', 'message' => 'Reset link generated: ' . $link];
                break;

            case 'add_student_accounts':
                $bulk = trim((string) ($_POST['bulk_emails'] ?? ''));
                if ($bulk === '') {
                    throw new Exception('Provide at least one email.');
                }
                $emails = preg_split('/[\\s,;]+/', $bulk);
                $added = 0;
                foreach ($emails as $email) {
                    $email = strtolower(trim((string) $email));
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $studentAccountModel->addOrEnableAccount($email, (int) $_SESSION['officer_id']);
                    $added++;
                }
                $auditLogger->log((int) $_SESSION['officer_id'], 'superadmin', 'student_whitelist_updated', 'student_account', null, [
                    'count' => $added
                ]);
                $_SESSION['admin_management_flash'] = ['type' => 'success', 'message' => 'Student access list updated.'];
                break;

            case 'toggle_student_account':
                $accountId = (int) ($_POST['account_id'] ?? 0);
                $enabled = !empty($_POST['is_enabled']);
                if (!$accountId) {
                    throw new Exception('Invalid student account.');
                }
                $studentAccountModel->setAccountEnabled($accountId, $enabled);
                $auditLogger->log((int) $_SESSION['officer_id'], 'superadmin', 'student_whitelist_status_updated', 'student_account', $accountId, [
                    'enabled' => $enabled
                ]);
                $_SESSION['admin_management_flash'] = ['type' => 'success', 'message' => 'Student account updated.'];
                break;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $e) {
        $_SESSION['admin_management_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    }

    header('Location: index.php?page=admin_management');
    exit();
}

$admins = $adminModel->getAllAdmins();
$studentAccounts = $studentAccountModel->getAccounts();
$auditLogs = $auditLogModel->getRecentLogs(30);

include 'view/admin_management.php';
