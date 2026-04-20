<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/StudentModel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['officer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    echo json_encode(['success' => true, 'students' => []]);
    exit;
}

$studentModel = new StudentModel();
$students = $studentModel->searchStudents($query, 8);

echo json_encode([
    'success' => true,
    'students' => $students,
]);