<?php
require_once __DIR__ . '/../model/ReportModel.php';
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/AuditLogger.php';

if (!isset($_SESSION['officer_id'])) {
    header('Location: index.php?page=login');
    exit();
}

$reportModel = new ReportModel();
$flash = $_SESSION['report_triage_flash'] ?? null;
unset($_SESSION['report_triage_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $allowedStatuses = ['new', 'in_review', 'resolved', 'escalated'];
        if (!$reportId || !in_array($status, $allowedStatuses, true)) {
            throw new Exception('Invalid report update.');
        }

        $reportModel->updateReportStatus($reportId, $status, (int) $_SESSION['officer_id']);
        $auditLogger = new AuditLogger();
        $auditLogger->log((int) $_SESSION['officer_id'], !empty($_SESSION['is_superadmin']) ? 'superadmin' : 'officer', 'student_report_status_updated', 'student_report', $reportId, [
            'status' => $status
        ]);

        $_SESSION['report_triage_flash'] = ['type' => 'success', 'message' => 'Report updated successfully.'];
        header('Location: index.php?page=report_triage');
        exit();
    } catch (Exception $e) {
        $_SESSION['report_triage_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        header('Location: index.php?page=report_triage');
        exit();
    }
}

$filterStatus = $_GET['status'] ?? '';
$reports = $reportModel->getReports($filterStatus ?: null);

include 'view/report_triage.php';
