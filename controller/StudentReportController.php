<?php
require_once __DIR__ . '/../model/ReportModel.php';
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/TextGuard.php';
require_once __DIR__ . '/../helper/RateLimiter.php';
require_once __DIR__ . '/../helper/AuditLogger.php';

$reportModel = new ReportModel();
$success = $_SESSION['report_success'] ?? '';
$error = $_SESSION['report_error'] ?? '';
unset($_SESSION['report_success'], $_SESSION['report_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);
        $reportType = trim((string) ($_POST['report_type'] ?? ''));
        $description = $_POST['description'] ?? '';
        $honeypot = trim((string) ($_POST['contact_website'] ?? ''));

        if ($honeypot !== '') {
            throw new Exception('Submission flagged as spam.');
        }

        if (!rateLimitCheck('student_report_' . (int) $_SESSION['student_id'], 4, 300)) {
            throw new Exception('Too many reports submitted. Please wait a moment.');
        }

        $allowedTypes = ['bullying', 'discipline', 'mental_health'];
        if (!in_array($reportType, $allowedTypes, true)) {
            throw new Exception('Please select a valid report category.');
        }

        $validation = validateFreeText($description, 10, 1200);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }

        $reportId = $reportModel->createReport(
            (int) $_SESSION['student_id'],
            $reportType,
            $validation['value'],
            $validation['self_harm']
        );

        $auditLogger = new AuditLogger();
        $auditLogger->log((int) $_SESSION['student_id'], 'student', 'student_report_created', 'student_report', $reportId, [
            'self_harm' => $validation['self_harm']
        ]);

        $_SESSION['report_success'] = 'Your report has been submitted. A staff member will review it shortly.';
        header('Location: index.php?page=student_report');
        exit();
    } catch (Exception $e) {
        $_SESSION['report_error'] = $e->getMessage();
        header('Location: index.php?page=student_report');
        exit();
    }
}

include 'view/student_report.php';
