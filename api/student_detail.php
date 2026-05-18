<?php
/**
 * Student Detail API
 * Returns student information and violations in JSON format
 */

// Ensure output is JSON
header('Content-Type: application/json; charset=utf-8');

// Log errors, but do not display them in the response
ini_set('display_errors', '0');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/student_detail_api.log');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['officer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$student_id = $_GET['student_id'] ?? null;

if (!$student_id || !is_numeric($student_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid student_id']);
    exit();
}

try {
    // Use absolute path
    $basePath = dirname(__DIR__);
    
    require_once $basePath . '/config/db_connection.php';
    require_once $basePath . '/model/StudentModel.php';
    require_once $basePath . '/model/ViolationModel.php';

    $studentModel = new StudentModel();
    $violationModel = new ViolationModel();

    // Get student info
    $student_info = $studentModel->getStudentById($student_id);

    if (!$student_info) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Student not found'
        ]);
        exit();
    }

    // Get related data
    $violations = $violationModel->getViolationsByStudent($student_id);
    $violation_counts = $violationModel->getViolationCountByType($student_id);
    $escalation_history = $violationModel->getEscalationHistory($student_id);

    // Return success response
    echo json_encode([
        'success' => true,
        'student' => $student_info,
        'violations' => $violations ?: [],
        'violation_counts' => $violation_counts ?: [],
        'escalation_history' => $escalation_history ?: []
    ]);

} catch (Exception $e) {
    error_log("Student Detail API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
?>
