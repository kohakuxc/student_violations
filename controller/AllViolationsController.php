<?php
/**
 * All Violations Controller
 * Displays all violations in the database
 */

if (!isset($_SESSION['officer_id'])) {
    header("Location: index.php?page=login");
    exit();
}

include 'model/ViolationModel.php';

$violationModel = new ViolationModel();
$sort = $_GET['sort'] ?? '';
$dir = strtolower($_GET['dir'] ?? 'desc');
$search = $_GET['search'] ?? '';

$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$pageSize = (int) ($_GET['page_size'] ?? 25);
if ($pageSize < 1)
    $pageSize = 25;
if ($pageSize > 200)
    $pageSize = 200;

$export = $_GET['export'] ?? '';

$allowedSorts = ['created_at', 'date_of_violation', 'violation_type', 'severity'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'created_at';
}

if ($dir !== 'asc' && $dir !== 'desc') {
    $dir = 'desc';
}
$totalRecords = $violationModel->getAllViolationsCount($search);

if ($export === 'csv') {
    // Export current filtered results as CSV
    $allRows = $violationModel->getAllViolations($sort, $search, $dir, null, 0);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=violations_export.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date of Violation', 'Student Num', 'Student', 'Officer', 'Violation Type', 'Severity', 'Description', 'Created At']);
    foreach ($allRows as $r) {
        // Format dates by extracting just the date portion
        $dov = '';
        if (!empty($r['date_of_violation'])) {
            // Strip everything after space or timezone
            $dov = "'" . preg_replace('/\s.*/', '', $r['date_of_violation']);
        }
        
        $cat = '';
        if (!empty($r['created_at'])) {
            // Strip everything after space or timezone
            $cat = "'" . preg_replace('/\s.*/', '', $r['created_at']);
        }
        
        fputcsv($out, [
            $dov,
            $r['student_num'] ?? '-',
            $r['student_name'] ?? '',
            $r['officer_name'] ?? '',
            $r['type_name'] ?? '',
            $r['severity_level'] ?? '',
            $r['description'] ?? '',
            $cat
        ]);
    }
    fclose($out);
    exit();
}

$totalPages = ($pageSize > 0) ? (int) ceil($totalRecords / $pageSize) : 1;
if ($pageNum > $totalPages && $totalPages > 0)
    $pageNum = $totalPages;
$offset = ($pageNum - 1) * $pageSize;

$violations = $violationModel->getAllViolations($sort, $search, $dir, $pageSize, $offset);
$escalation_history = $violationModel->getEscalationHistory();

include 'view/all_violations.php';
?>