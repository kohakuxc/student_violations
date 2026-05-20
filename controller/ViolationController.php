<?php
/**
 * Violation Controller
 * Handles recording and managing violations
 */

if (!isset($_SESSION['officer_id'])) {
    header("Location: index.php");
    exit();
}

include 'model/StudentModel.php';
include 'model/ViolationModel.php';
include 'model/ViolationTypeModel.php';
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/RateLimiter.php';
require_once __DIR__ . '/../helper/TextGuard.php';

$studentModel = new StudentModel();
$violationModel = new ViolationModel();
$violationTypeModel = new ViolationTypeModel();

$violation_types = $violationTypeModel->getActiveViolationTypes();

$success = "";
$new_violation_id = null;
$error = "";
$student_lookup = $student_id = $violation_type = $description = $date_of_violation = '';
$selected_student = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    $student_lookup = trim($_POST['student_lookup'] ?? $_POST['student_number'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $violation_type = $_POST['violation_type'] ?? ''; // now this is violation_type_id
    $description = $_POST['description'] ?? '';
    $date_of_violation = $_POST['date_of_violation'] ?? '';
    $honeypot = trim((string) ($_POST['contact_website'] ?? ''));

    if ($error) {
        // keep error from CSRF
    } elseif ($honeypot !== '') {
        $error = "Submission flagged as spam.";
    } elseif (!rateLimitCheck('violation_submit_' . (int) ($_SESSION['officer_id'] ?? 0), 8, 60)) {
        $error = "Too many submissions. Please wait a moment and try again.";
    } elseif (empty($student_lookup) || empty($violation_type) || empty($description) || empty($date_of_violation)) {
        $error = "All fields are required!";
    } else {
        $validation = validateFreeText($description, 10, 1000);
        if (!$validation['valid']) {
            $error = $validation['message'];
        }
    }

    if (empty($error)) {
        $student = null;

        if (!empty($student_id)) {
            $student = $studentModel->getStudentById($student_id);
        }

        if (!$student) {
            $student = $studentModel->findStudentByLookup($student_lookup);
        }

            if ($student) {
                $selected_student = $student;
                $result = $violationModel->addViolation(
                    $student['student_id'],
                    $_SESSION['officer_id'],
                    $violation_type,
                    $validation['value'],
                    $date_of_violation,
                    $validation['self_harm']
                );

            if ($result['success']) {
                $success = $result['message'];
                $new_violation_id = $result['violation_id'] ?? null;
                $student_lookup = $student_id = $violation_type = $description = $date_of_violation = '';
                $selected_student = null;
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Student not found! Please check the student name or student number.";
        }
    }
}

include 'view/add_violation.php';
?>
