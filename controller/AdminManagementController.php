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

if (!function_exists('parseStudentEmailImportFile')) {
    function parseStudentEmailImportFile($filePath, $fileName)
    {
        $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            return parseStudentEmailImportCsv($filePath);
        }

        if ($extension === 'xlsx') {
            return parseStudentEmailImportXlsx($filePath);
        }

        throw new Exception('Unsupported file type. Please upload a CSV or XLSX file.');
    }
}

if (!function_exists('parseStudentEmailImportCsv')) {
    function parseStudentEmailImportCsv($filePath)
    {
        $emails = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception('Unable to open the uploaded file.');
        }

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (!is_array($row) || empty($row)) {
                continue;
            }
            $email = trim((string) ($row[0] ?? ''));
            if ($email === '') {
                continue;
            }
            if (strcasecmp($email, 'email') === 0 || strcasecmp($email, 'student_email') === 0) {
                continue;
            }
            $emails[] = strtolower($email);
        }

        fclose($handle);
        return array_values(array_unique($emails));
    }
}

if (!function_exists('parseStudentEmailImportXlsx')) {
    function parseStudentEmailImportXlsx($filePath)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('XLSX import requires the ZipArchive extension. Use CSV if unavailable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('Unable to open the XLSX file.');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sharedDoc = new DOMDocument();
            $sharedDoc->loadXML($sharedStringsXml);
            $sharedXPath = new DOMXPath($sharedDoc);
            $sharedXPath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($sharedXPath->query('//a:si') as $sharedItem) {
                $textNodes = $sharedXPath->query('.//a:t', $sharedItem);
                $text = '';
                foreach ($textNodes as $textNode) {
                    $text .= $textNode->textContent;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new Exception('Could not read the first worksheet from the XLSX file.');
        }

        $doc = new DOMDocument();
        $doc->loadXML($sheetXml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $emails = [];
        foreach ($xpath->query('//a:sheetData/a:row') as $row) {
            $cell = $xpath->query('./a:c[1]', $row)->item(0);
            if (!$cell) {
                continue;
            }

            $value = '';
            $attrType = $cell->attributes->getNamedItem('t');
            $cellType = $attrType ? (string) $attrType->nodeValue : '';

            if ($cellType === 's') {
                $valueNode = $xpath->query('./a:v', $cell)->item(0);
                $index = $valueNode ? (int) $valueNode->textContent : -1;
                $value = $sharedStrings[$index] ?? '';
            } elseif ($cellType === 'inlineStr') {
                $textNode = $xpath->query('.//a:t', $cell)->item(0);
                $value = $textNode ? (string) $textNode->textContent : '';
            } else {
                $valueNode = $xpath->query('./a:v', $cell)->item(0);
                $value = $valueNode ? (string) $valueNode->textContent : '';
            }

            $value = strtolower(trim($value));
            if ($value === '' || $value === 'email' || $value === 'student_email') {
                continue;
            }
            $emails[] = $value;
        }

        return array_values(array_unique($emails));
    }
}

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

            case 'import_student_accounts':
                if (!rateLimitCheck('import_student_accounts_' . (int) $_SESSION['officer_id'], 5, 300)) {
                    throw new Exception('Too many import attempts. Please wait before trying again.');
                }
                if (empty($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
                    throw new Exception('Please choose a file to upload.');
                }
                $upload = $_FILES['import_file'];
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new Exception('The uploaded file could not be read.');
                }
                $fileName = (string) ($upload['name'] ?? 'student_emails');
                $filePath = (string) ($upload['tmp_name'] ?? '');
                if ($filePath === '' || !is_uploaded_file($filePath)) {
                    throw new Exception('The uploaded file is invalid.');
                }
                if ((int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
                    throw new Exception('File size exceeds 10MB limit.');
                }

                $emails = parseStudentEmailImportFile($filePath, $fileName);
                if (empty($emails)) {
                    throw new Exception('No valid emails were found in the uploaded file.');
                }

                $imported = 0;
                $updated = 0;
                $invalid = 0;
                foreach ($emails as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $invalid++;
                        continue;
                    }
                    $before = $studentAccountModel->isEmailAllowed($email);
                    $studentAccountModel->addOrEnableAccount($email, (int) $_SESSION['officer_id']);
                    if ($before) {
                        $updated++;
                    } else {
                        $imported++;
                    }
                }

                $auditLogger->log((int) $_SESSION['officer_id'], 'superadmin', 'student_whitelist_imported', 'student_account', null, [
                    'file_name' => $fileName,
                    'imported' => $imported,
                    'updated' => $updated,
                    'invalid' => $invalid,
                ]);

                $_SESSION['admin_management_flash'] = [
                    'type' => 'success',
                    'message' => 'Student access list imported: ' . $imported . ' added, ' . $updated . ' updated, ' . $invalid . ' invalid rows skipped.'
                ];
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
