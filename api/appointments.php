<?php
session_start();
header('Content-Type: application/json');

// For AJAX requests, we don't necessarily need to check officer_id
// Just make sure a session exists
if (!isset($_SESSION['student_id']) && !isset($_SESSION['officer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/AppointmentModel.php';

$action = $_GET['action'] ?? null;
$appointmentModel = new AppointmentModel();

try {
    switch ($action) {
        case 'getSubcategories':
            // ... existing code ...
            break;

        case 'getAvailableSlots':
            // ... existing code ...
            break;

        case 'getAppointmentDetails':
            $appointment_id = intval($_GET['id'] ?? 0);
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            
            $appointment = $appointmentModel->getAppointmentById($appointment_id);
            if (!$appointment) {
                throw new Exception('Appointment not found');
            }
            
            echo json_encode(['success' => true, 'data' => $appointment]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>