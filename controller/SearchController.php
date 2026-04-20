<?php
/**
 * Search Controller
 * Handles searching for student violations
 */

//session_start();

// Check if officer is logged in
if (!isset($_SESSION['officer_id'])) {
    header("Location: index.php");
    exit();
}

include 'model/StudentModel.php';
include 'model/ViolationModel.php';

$studentModel = new StudentModel();
$violationModel = new ViolationModel();

$violations = [];
$student_info = null;
$violation_counts = [];
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['search'])) {
    $search_term = $_POST['search'] ?? $_GET['search'] ?? '';
    $search_performed = true;

    if (!empty($search_term)) {
        // Search for student
        $student_info = $studentModel->searchStudent($search_term);

        if ($student_info) {
            $student_info['display_student_id'] = $studentModel->getStudentCodeFromEmail(
                $student_info['email'] ?? '',
                $student_info['student_number'] ?? ''
            );

            // Get violations for this student
            $violations = $violationModel->getViolationsByStudent($student_info['student_id']);
            $violation_counts = $violationModel->getViolationCountByType($student_info['student_id']);
        }
    }
}

$violation_types = [
    1 => "Major Violation",
    2 => "Moderate Violation",
    3 => "Minor Violation"
];

// Load search view
include 'view/search_student.php';
?>