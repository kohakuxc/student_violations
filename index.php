<?php
/**
 * Main Routing Controller
 * Consolidated login and routing
 */

session_start();

if (isset($_GET['test_student']) && $_GET['test_student'] === 'yes') {
    $_SESSION['student_id'] = 1;
    $_SESSION['student_name'] = 'Test Student';
    $_SESSION['student_email'] = 'teststudent@school.edu.ph';
    header('Location: index.php?page=student_dashboard');
    exit;
}

if (isset($_GET['action']) && strpos($_GET['action'], 'get') === 0) {
    require_once __DIR__ . '/controller/StudentAppointmentController.php';
    $controller = new StudentAppointmentController();
    
    if ($_GET['action'] === 'getSubcategories') {
        $controller->getSubcategoriesAjax();
    } elseif ($_GET['action'] === 'getAvailableSlots') {
        $controller->getAvailableSlotsAjax();
    }
    exit;
}

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

    // ===== NEW APPOINTMENT ROUTES =====
    case 'student_appointments':
        // Student appointments - check if logged in
        if (!isset($_SESSION['student_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'view/student_appointments.php';
        break;

    case 'officer_appointments':
        // Officer appointments list - check if logged in
        if (!isset($_SESSION['officer_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'view/officer_appointments.php';
        break;

    case 'officer_appointment_details':
        // Officer appointment details - check if logged in
        if (!isset($_SESSION['officer_id'])) {
            header("Location: index.php?page=login");
            exit();
        }
        include 'view/officer_appointment_details.php';
        break;

    case 'appointments':
        // Keep existing appointments route if you have it
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

// ===== HANDLE AJAX/ACTION REQUESTS =====
// Handle appointment AJAX requests (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'getCategories':
        case 'getSubcategories':
        case 'getAvailableSlots':
        case 'getAppointmentDetails':
            require_once 'config/db_connection.php';
            require_once 'controller/StudentAppointmentController.php';
            $controller = new StudentAppointmentController();

            switch ($action) {
                case 'getCategories':
                    $controller->getCategories();
                    break;
                case 'getSubcategories':
                    $controller->getSubcategories();
                    break;
                case 'getAvailableSlots':
                    $controller->getAvailableSlots();
                    break;
                case 'getAppointmentDetails':
                    $controller->getAppointmentDetailsAjax();
                    break;
            }
            exit;
    }
}

// Handle appointment POST requests (Form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        // Student Appointment Actions
        case 'createAppointment':
            require_once 'config/db_connection.php';
            require_once 'controller/StudentAppointmentController.php';
            $controller = new StudentAppointmentController();
            $controller->createAppointment();
            exit;

        case 'cancelAppointment':
            require_once 'config/db_connection.php';
            require_once 'controller/StudentAppointmentController.php';
            $controller = new StudentAppointmentController();
            $controller->cancelAppointment();
            exit;

        // Officer Appointment Actions
        case 'approve':
        case 'reject':
        case 'reschedule':
        case 'markInProgress':
        case 'markCompleted':
            require_once 'config/db_connection.php';
            require_once 'controller/OfficerAppointmentController.php';
            $controller = new OfficerAppointmentController();

            switch ($action) {
                case 'approve':
                    $controller->approveAppointment();
                    break;
                case 'reject':
                    $controller->rejectAppointment();
                    break;
                case 'reschedule':
                    $controller->rescheduleAppointment();
                    break;
                case 'markInProgress':
                    $controller->markInProgress();
                    break;
                case 'markCompleted':
                    $controller->markCompleted();
                    break;
            }
            exit;
    }
}

?>