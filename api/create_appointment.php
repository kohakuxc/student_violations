<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/env_loader.php';
loadEnvFile(__DIR__ . '/../config/.env');

$isProduction = strtolower(trim(getenv('APP_ENV') ?: 'development')) === 'production';

// Create custom log file
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/appointments.log';

// Function to log messages
function debug_log($message, $force = false) {
    global $log_file, $isProduction;
    if ($isProduction && !$force) {
        return;
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("=== CREATE APPOINTMENT REQUEST ===");
debug_log("Student ID: " . ($_SESSION['student_id'] ?? 'NOT SET'));
debug_log("POST Data: " . json_encode($_POST));

if (!isset($_SESSION['student_id'])) {
    debug_log("ERROR: Not authenticated", true);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    require_once __DIR__ . '/../config/db_connection.php';
    require_once __DIR__ . '/../model/AppointmentModel.php';
    
    debug_log("Database connection established");
    
    $category_id = intval($_POST['category_id'] ?? 0);
    $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? null;
    $time = $_POST['time'] ?? null;
    
    debug_log("Form Data - Cat: $category_id, SubCat: $subcategory_id, Date: $date, Time: $time");
    
    if (!$category_id || !$subcategory_id || !$description || !$date || !$time) {
        debug_log("ERROR: Missing required fields", true);
        throw new Exception('All fields are required');
    }
    
    if (strlen($description) > 1000) {
        debug_log("ERROR: Description too long", true);
        throw new Exception('Description cannot exceed 1000 characters');
    }
    
    $evidence_image = null;
    if (!empty($_FILES['evidence_image']['name'])) {
        debug_log("File upload attempted");
        $file = $_FILES['evidence_image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'application/pdf'];
        $max_size = 3 * 1024 * 1024;
        
        if (!in_array($file['type'], $allowed_types)) {
            debug_log("ERROR: Invalid file type - {$file['type']}", true);
            throw new Exception('Invalid file type. Only PDF and JPG allowed');
        }
        
        if ($file['size'] > $max_size) {
            debug_log("ERROR: File too large - {$file['size']} bytes", true);
            throw new Exception('File size exceeds 3MB limit');
        }
        
        $uploads_dir = __DIR__ . '/../uploads/evidence/';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
            debug_log("Created uploads directory");
        }
        
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'appointment_' . $_SESSION['student_id'] . '_' . time() . '.' . $file_ext;
        $file_path = $uploads_dir . $file_name;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            debug_log("ERROR: Failed to move uploaded file", true);
            throw new Exception('Failed to upload file');
        }
        
        $evidence_image = 'uploads/evidence/' . $file_name;
        debug_log("File uploaded successfully: $evidence_image");
    }
    
    $scheduled_datetime = $date . ' ' . $time;
    debug_log("Scheduled DateTime: $scheduled_datetime");
    
    $appointmentModel = new AppointmentModel();
    debug_log("AppointmentModel instantiated");
    
    $result = $appointmentModel->createAppointment(
        $_SESSION['student_id'],
        $category_id,
        $subcategory_id,
        $description,
        $scheduled_datetime,
        $evidence_image
    );
    
    debug_log("createAppointment result: " . ($result ? 'SUCCESS' : 'FAILED'));
    
    if (!$result) {
        throw new Exception('Database error: Failed to create appointment');
    }
    
    debug_log("Appointment created successfully for student {$_SESSION['student_id']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment created successfully',
        'redirect' => 'index.php?page=student_appointments'
    ]);
    
} catch (Exception $e) {
    debug_log("EXCEPTION: " . $e->getMessage(), true);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?>