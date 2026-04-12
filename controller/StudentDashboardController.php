<?php
/**
 * Student Dashboard Controller
 * Displays student's own violations
 */

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: index.php?page=student_login");
    exit();
}

include 'model/StudentAuthModel.php';

$studentAuthModel = new StudentAuthModel();
$violations = $studentAuthModel->getStudentViolations($_SESSION['student_id']);
$violation_counts = $studentAuthModel->getViolationCountByType($_SESSION['student_id']);

// Calculate total violations
$total_violations = count($violations);

include 'view/student_dashboard.php';
?>