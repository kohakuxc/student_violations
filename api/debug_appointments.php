<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/env_loader.php';
loadEnvFile(__DIR__ . '/../config/.env');

$appEnv = strtolower(trim(getenv('APP_ENV') ?: 'development'));
if ($appEnv !== 'development') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

if (!isset($_SESSION['officer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Officer authentication required']);
    exit;
}

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/AppointmentModel.php';

$appointmentModel = new AppointmentModel();
$driver = $GLOBALS['conn']->getAttribute(PDO::ATTR_DRIVER_NAME);
$appointmentsTable = ($driver === 'sqlsrv') ? 'dbo.appointments' : 'appointments';

// Test 1: Count appointments
$count_result = "Unknown";
try {
    $stmt = $GLOBALS['conn']->prepare("SELECT COUNT(*) as count FROM {$appointmentsTable}");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_result = $result['count'] ?? 0;
} catch (Exception $e) {
    $count_result = "Error: " . $e->getMessage();
}

// Test 2: Get raw appointments
$raw_appointments = [];
try {
    $stmt = $GLOBALS['conn']->prepare("SELECT appointment_id, student_id, category_id, status, scheduled_date FROM {$appointmentsTable}");
    $stmt->execute();
    $raw_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $raw_appointments = ["error" => $e->getMessage()];
}

// Test 3: Try getAllAppointments
$appointments_data = $appointmentModel->getAllAppointments();

// Test 4: Get stats
$stats = $appointmentModel->getAppointmentStats();

echo json_encode([
    'total_appointments_in_table' => $count_result,
    'raw_query_result' => $raw_appointments,
    'getAllAppointments_result' => $appointments_data,
    'stats' => $stats
], JSON_PRETTY_PRINT);
?>