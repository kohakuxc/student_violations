<?php
/**
 * Login Controller
 * Handles officer authentication
 */

// If already logged in, redirect to dashboard
if (isset($_SESSION['officer_id'])) {
    header("Location: index.php?page=dashboard");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'admin') {
    require_once __DIR__ . '/../helper/CsrfHelper.php';
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);
    } catch (Exception $e) {
        $error = $e->getMessage();
        include 'view/login.php';
        exit();
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "Username and password are required!";
    } else {
        // Use OfficerModel
        include 'model/OfficerModel.php';
        $officerModel = new OfficerModel();

        $result = $officerModel->authenticate($username, $password);

        if ($result['success']) {
            $_SESSION['officer_id'] = $result['officer_id'];
            $_SESSION['name'] = $result['name'];
            $_SESSION['is_admin'] = !empty($result['is_admin']);
            $_SESSION['is_superadmin'] = !empty($result['is_superadmin']);
            $_SESSION['can_import_excel'] = !empty($result['can_import_excel']);
            header("Location: index.php?page=dashboard");
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Load login view with error/login_url variables
include 'view/login.php';
?>
