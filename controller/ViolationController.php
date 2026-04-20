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

$studentModel = new StudentModel();
$violationModel = new ViolationModel();
$violationTypeModel = new ViolationTypeModel();

$violation_types = $violationTypeModel->getActiveViolationTypes();

$success = "";
$error = "";
$student_lookup = $student_id = $violation_type = $description = $date_of_violation = '';
$selected_student = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_lookup = trim($_POST['student_lookup'] ?? $_POST['student_number'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $violation_type = $_POST['violation_type'] ?? ''; // now this is violation_type_id
    $description = $_POST['description'] ?? '';
    $date_of_violation = $_POST['date_of_violation'] ?? '';

    if (empty($student_lookup) || empty($violation_type) || empty($description) || empty($date_of_violation)) {
        $error = "All fields are required!";
    } else {
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
                $description,
                $date_of_violation
            );

            if ($result['success']) {
                $success = $result['message'];
                $student_lookup = $student_id = $violation_type = $description = $date_of_violation = '';
                $selected_student = null;
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Student not found! Please check the student name, student number, or 6-digit email code.";
        }
    }
}

include 'view/add_violation.php';
?>