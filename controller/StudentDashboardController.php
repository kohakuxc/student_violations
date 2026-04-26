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
include 'model/ViolationModel.php';
require_once __DIR__ . '/../config/system_settings.php';

$settings = loadSystemSettings();

$studentDefaultView = $settings['student_default_view'] ?? 'violations';
if ($studentDefaultView === 'appointments' && !isset($_GET['force_dashboard'])) {
    header("Location: index.php?page=student_appointments");
    exit();
}

$studentAuthModel = new StudentAuthModel();
$violationModel = new ViolationModel();
$violations = $studentAuthModel->getStudentViolations($_SESSION['student_id']);
$violation_counts = $studentAuthModel->getViolationCountByType($_SESSION['student_id']);
$escalation_history = $violationModel->getEscalationHistory($_SESSION['student_id']);

// Calculate total violations before applying page-size preferences
$total_violations = count($violations);

$studentPageSize = max(1, (int) ($settings['student_dashboard_page_size'] ?? 10));
$showEscalationHistory = !empty($settings['student_show_escalation_history']);

$violationsPage = max(1, (int) ($_GET['vpage'] ?? 1));
$escalationsPage = max(1, (int) ($_GET['epage'] ?? 1));

$totalViolationsPages = max(1, (int) ceil($total_violations / $studentPageSize));
if ($violationsPage > $totalViolationsPages) {
    $violationsPage = $totalViolationsPages;
}

$totalEscalations = count($escalation_history);
$totalEscalationPages = max(1, (int) ceil($totalEscalations / $studentPageSize));
if ($escalationsPage > $totalEscalationPages) {
    $escalationsPage = $totalEscalationPages;
}

if (!empty($violations)) {
    $violations = array_slice($violations, ($violationsPage - 1) * $studentPageSize, $studentPageSize);
}

if ($showEscalationHistory && !empty($escalation_history)) {
    $escalation_history = array_slice($escalation_history, ($escalationsPage - 1) * $studentPageSize, $studentPageSize);
}

if (!$showEscalationHistory) {
    $escalation_history = [];
    $totalEscalations = 0;
    $totalEscalationPages = 1;
    $escalationsPage = 1;
}

include 'view/student_dashboard.php';
?>