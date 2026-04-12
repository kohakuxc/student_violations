<?php
/**
 * Main Routing Controller
 * Consolidated login and routing
 */

session_start();

// Logout handler
if (isset($_GET['page']) && $_GET['page'] === 'logout') {
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}

// Include Microsoft 365 config
include 'config/microsoft365_config.php';

// Determine which page to load
$page = $_GET['page'] ?? null;

// If no page specified and user is not logged in, redirect to login
if ($page === null) {
    if (isset($_SESSION['officer_id'])) {
        header("Location: index.php?page=dashboard");
        exit();
    } else if (isset($_SESSION['student_id'])) {
        header("Location: index.php?page=student_dashboard");
        exit();
    } else {
        header("Location: index.php?page=login");
        exit();
    }
}

switch ($page) {
    // ===== OFFICER/ADMIN ROUTES =====
    case 'login':
        // Handle both admin login and student OAuth callback
        $error = "";
        $login_url = "";

        // Admin login form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'admin') {
            include 'controller/LoginController.php';
        }
        // Student OAuth callback
        else if (isset($_GET['code']) || isset($_GET['error'])) {
            include 'controller/StudentLoginController.php';
        }
        // Generate student login URL
        else if (!isset($_SESSION['officer_id']) && !isset($_SESSION['student_id'])) {
            include 'model/StudentAuthModel.php';
            $studentAuthModel = new StudentAuthModel();
            $login_url = $studentAuthModel->generateAuthorizationUrl();
            include 'view/login.php';
        } else {
            // User is already logged in, redirect to appropriate dashboard
            if (isset($_SESSION['officer_id'])) {
                header("Location: index.php?page=dashboard");
            } else if (isset($_SESSION['student_id'])) {
                header("Location: index.php?page=student_dashboard");
            }
            exit();
        }
        break;

    case 'dashboard':
        // Officer dashboard - check if logged in
        if (!isset($_SESSION['officer_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'controller/DashboardController.php';
        break;

    case 'add_violation':
        if (!isset($_SESSION['officer_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'controller/ViolationController.php';
        break;

    case 'search_student':
        if (!isset($_SESSION['officer_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'controller/SearchController.php';
        break;

    case 'all_violations':
        if (!isset($_SESSION['officer_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'controller/AllViolationsController.php';
        break;

    case 'appointments':
        if (!isset($_SESSION['officer_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'controller/AppointmentsController.php';
        break;

    // ===== STUDENT ROUTES =====
    case 'student_oauth_callback':
        include 'controller/StudentLoginController.php';
        break;

    case 'student_dashboard':
        // Student dashboard - check if logged in
        if (!isset($_SESSION['student_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'controller/StudentDashboardController.php';
        break;

    // Default - redirect to login
    default:
        header("Location: index.php?page=login");
        exit();
        break;
}
?>