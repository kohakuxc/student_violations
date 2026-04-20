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

// Date range selection. Default = last 3 months to current month
$from_month = $_GET['from_month'] ?? date('Y-m', strtotime('first day of -3 months'));
$to_month = $_GET['to_month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $from_month)) {
    $from_month = date('Y-m', strtotime('first day of -3 months'));
}
if (!preg_match('/^\d{4}-\d{2}$/', $to_month)) {
    $to_month = date('Y-m');
}

// Ensure from_month is not after to_month
if ($from_month > $to_month) {
    $temp = $from_month;
    $from_month = $to_month;
    $to_month = $temp;
}

$monthlyStudentCounts = $officerModel->getDateRangeStudentSeverityCounts($from_month, $to_month);
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