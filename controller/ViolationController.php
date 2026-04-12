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
$student_number = $violation_type = $description = $date_of_violation = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_number = $_POST['student_number'] ?? '';
    $violation_type = $_POST['violation_type'] ?? ''; // now this is violation_type_id
    $description = $_POST['description'] ?? '';
    $date_of_violation = $_POST['date_of_violation'] ?? '';

    if (empty($student_number) || empty($violation_type) || empty($description) || empty($date_of_violation)) {
        $error = "All fields are required!";
    } else {
        $student = $studentModel->getStudentByNumber($student_number);

        if ($student) {
            $result = $violationModel->addViolation(
                $student['student_id'],
                $_SESSION['officer_id'],
                $violation_type,
                $description,
                $date_of_violation
            );

            if ($result['success']) {
                $success = $result['message'];
                $student_number = $violation_type = $description = $date_of_violation = '';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Student not found! Please check the student number.";
        }
    }
}

include 'view/add_violation.php';
?>