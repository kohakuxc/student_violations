<?php
session_start();
header('Content-Type: application/json');

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
            $category_id = intval($_GET['category_id'] ?? 0);
            if (!$category_id) {
                throw new Exception('Category ID required');
            }
            $subcategories = $appointmentModel->getSubcategoriesByCategory($category_id);
            echo json_encode(['success' => true, 'data' => $subcategories]);
            break;

        case 'getAvailableSlots':
            $date = $_GET['date'] ?? null;
            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Valid date required');
            }
            $slots = $appointmentModel->getAvailableTimeSlots(1, $date);
            echo json_encode(['success' => true, 'data' => $slots]);
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

            $notes = $appointmentModel->getAppointmentNotes($appointment_id);
            $appointment['notes'] = $notes;
            $appointment['latest_reason'] = $appointmentModel->getReasonByAppointmentId($appointment_id);

            echo json_encode(['success' => true, 'data' => $appointment]);
            break;

        case 'approveAppointment':
            if (!isset($_SESSION['officer_id'])) {
                throw new Exception('Officer access required');
            }
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $appointment_id = intval($data['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
            $note_text = trim($data['note'] ?? $_POST['note'] ?? '');
            $officer_id = (int) $_SESSION['officer_id'];

            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }

            $appointmentModel->assignOfficer($appointment_id, $officer_id);
            $appointmentModel->updateAppointmentStatus($appointment_id, 'approved');

            if ($note_text !== '') {
                $appointmentModel->addAppointmentNote($appointment_id, $note_text, $officer_id);
            }

            echo json_encode(['success' => true, 'message' => 'Appointment approved successfully']);
            break;

        case 'rejectAppointment':
            if (!isset($_SESSION['officer_id'])) {
                throw new Exception('Officer access required');
            }
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $appointment_id = intval($data['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
            $reason = trim($data['reason'] ?? $_POST['reason'] ?? '');
            $officer_id = (int) $_SESSION['officer_id'];

            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            if ($reason === '') {
                throw new Exception('Rejection reason is required');
            }

            $appointmentModel->rejectAppointment($appointment_id, $reason, $officer_id);

            echo json_encode(['success' => true, 'message' => 'Appointment rejected']);
            break;

        case 'addNote':
            if (!isset($_SESSION['officer_id'])) {
                throw new Exception('Officer access required');
            }
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $appointment_id = intval($data['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
            $note_text = trim($data['note'] ?? $_POST['note'] ?? '');
            $officer_id = (int) $_SESSION['officer_id'];

            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            if ($note_text === '') {
                throw new Exception('Note text is required');
            }

            $result = $appointmentModel->addAppointmentNote($appointment_id, $note_text, $officer_id);
            if (!$result) {
                throw new Exception('Failed to add note');
            }

            echo json_encode(['success' => true, 'message' => 'Note added successfully']);
            break;

        case 'updateStatus':
            if (!isset($_SESSION['officer_id'])) {
                throw new Exception('Officer access required');
            }
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $appointment_id = intval($data['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
            $status = trim($data['status'] ?? $_POST['status'] ?? '');
            $note_text = trim($data['note'] ?? $_POST['note'] ?? '');
            $officer_id = (int) $_SESSION['officer_id'];

            $allowed_statuses = ['pending', 'approved', 'in_progress', 'completed', 'rejected', 'cancelled'];
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            if (!in_array($status, $allowed_statuses)) {
                throw new Exception('Invalid status value');
            }

            $appointmentModel->updateAppointmentStatus($appointment_id, $status);

            if ($note_text !== '') {
                $appointmentModel->addAppointmentNote($appointment_id, $note_text, $officer_id);
            }

            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            break;

        case 'cancelAppointment':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $appointment_id = intval($data['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
            $reason = trim($data['reason'] ?? $_POST['reason'] ?? '');

            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            if ($reason === '') {
                throw new Exception('Cancellation reason is required');
            }

            // Students can only cancel their own appointments
            if (isset($_SESSION['student_id'])) {
                $appointment = $appointmentModel->getAppointmentById($appointment_id);
                if (!$appointment || $appointment['student_id'] != $_SESSION['student_id']) {
                    throw new Exception('Unauthorized');
                }
            }

            $created_by = isset($_SESSION['student_id'])
                ? (int) $_SESSION['student_id']
                : (int) ($_SESSION['officer_id'] ?? 0);

            if ($created_by <= 0) {
                throw new Exception('Invalid user context for cancellation');
            }

            $ok = $appointmentModel->cancelAppointment($appointment_id, $reason, $created_by);
            if (!$ok) {
                throw new Exception('Failed to cancel appointment');
            }

            echo json_encode(['success' => true, 'message' => 'Appointment cancelled']);
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