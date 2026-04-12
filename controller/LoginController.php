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