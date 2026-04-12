<?php
/**
 * Dashboard Controller
 * Displays dashboard with statistics + charts
 */

if (!isset($_SESSION['officer_id'])) {
    header("Location: index.php");
    exit();
}

include 'model/OfficerModel.php';
include 'model/ViolationModel.php';

$officerModel = new OfficerModel();
$violationModel = new ViolationModel();

$total_violations = $officerModel->getTotalViolations();

// Month selection (YYYY-MM). Default = current month.
$selected_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
}

$monthlyStudentCounts = $officerModel->getMonthlyStudentSeverityCounts($selected_month);
$overallViolationCounts = $officerModel->getOverallViolationCountsBySeverity();

// NEW: Yearly line graph data (Jan..Dec for selected year)
$selected_year = (int) ($_GET['year'] ?? date('Y'));
if ($selected_year < 2000 || $selected_year > 2100) {
    $selected_year = (int) date('Y');
}
$yearlyOverview = $officerModel->getYearlyStudentSeverityCounts($selected_year);

// NEW: Recent activity (last 4 violations)
$recentViolations = $violationModel->getRecentViolations(4);

include 'view/dashboard.php';
?>