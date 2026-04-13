<?php
require_once __DIR__ . '/../model/AppointmentModel.php';
require_once __DIR__ . '/../model/StudentModel.php';
require_once __DIR__ . '/../helper/EmailNotification.php';

class StudentAppointmentController
{
    private $appointmentModel;
    private $studentModel;
    private $emailNotification;

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->studentModel = new StudentModel();
        $this->emailNotification = new EmailNotification();
    }

    // Get categories (AJAX endpoint)
    public function getCategories()
    {
        header('Content-Type: application/json');
        try {
            $categories = $this->appointmentModel->getAllCategories();
            echo json_encode(['success' => true, 'data' => $categories]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Get subcategories (AJAX endpoint)
    public function getSubcategories()
    {
        header('Content-Type: application/json');
        try {
            $category_id = $_GET['category_id'] ?? null;
            if (!$category_id) {
                throw new Exception('Category ID is required');
            }

            $subcategories = $this->appointmentModel->getSubcategoriesByCategory($category_id);
            echo json_encode(['success' => true, 'data' => $subcategories]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Get available time slots (AJAX endpoint)
    public function getAvailableSlots()
    {
        header('Content-Type: application/json');
        try {
            $date = $_GET['date'] ?? null;
            $officer_id = 1; // Assuming only one officer

            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Valid date is required (YYYY-MM-DD)');
            }

            // Check if date is Mon-Fri
            $day_of_week = date('N', strtotime($date)); // 1=Monday, 7=Sunday
            if ($day_of_week > 5) {
                throw new Exception('Appointments can only be scheduled on weekdays (Monday-Friday)');
            }

            $slots = $this->appointmentModel->getAvailableTimeSlots($officer_id, $date);
            echo json_encode(['success' => true, 'data' => $slots]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Get appointment details (for modal view - AJAX endpoint)
    public function getAppointmentDetailsAjax()
    {
        header('Content-Type: application/json');
        try {
            $appointment_id = $_GET['id'] ?? null;
            if (!$appointment_id) {
                throw new Exception('Appointment ID is required');
            }

            $appointment = $this->appointmentModel->getAppointmentById($appointment_id);
            if (!$appointment) {
                throw new Exception('Appointment not found');
            }

            echo json_encode(['success' => true, 'data' => $appointment]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Create new appointment
    public function createAppointment()
    {
        try {
            // Validate form data
            $student_id = $_SESSION['student_id'] ?? null;
            $category_id = $_POST['category_id'] ?? null;
            $subcategory_id = $_POST['subcategory_id'] ?? null;
            $description = $_POST['description'] ?? null;
            $date = $_POST['date'] ?? null;
            $time = $_POST['time'] ?? null;
            $officer_id = 1; // Only one officer

            if (!$student_id || !$category_id || !$subcategory_id || !$description || !$date || !$time) {
                throw new Exception('All required fields must be filled');
            }

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Invalid date format');
            }

            // Check if date is in the past
            if (strtotime($date . ' ' . $time) < time()) {
                throw new Exception('Cannot schedule appointments in the past');
            }

            // Check if date is weekday
            $day_of_week = date('N', strtotime($date));
            if ($day_of_week > 5) {
                throw new Exception('Appointments can only be scheduled Monday-Friday');
            }

            // Combine date and time
            $scheduled_date = $date . ' ' . $time . ':00';

            // Check time slot availability
            if (!$this->appointmentModel->isTimeSlotAvailable($officer_id, $scheduled_date)) {
                throw new Exception('This time slot is not available');
            }

            // Handle file upload
            $image_path = null;
            if (isset($_FILES['evidence_image']) && $_FILES['evidence_image']['error'] == 0) {
                $image_path = $this->handleFileUpload($_FILES['evidence_image']);
                if (!$image_path) {
                    throw new Exception('Failed to upload file');
                }
            }

            // Create appointment
            $appointment_id = $this->appointmentModel->createAppointment(
                $student_id,
                $officer_id,
                $category_id,
                $subcategory_id,
                $description,
                $scheduled_date,
                $image_path
            );

            if (!$appointment_id) {
                throw new Exception('Failed to create appointment');
            }

            // Get appointment details for email
            $appointment = $this->appointmentModel->getAppointmentById($appointment_id);
            $student = $this->studentModel->getStudentByIdForAppointments($student_id);

            // Send email notifications
            $this->emailNotification->sendAppointmentCreatedEmail($student, $appointment);

            header('Location: index.php?page=student_dashboard&alert=appointment_created');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?page=student_appointments');
            exit;
        }
    }

    // Handle file upload
    private function handleFileUpload($file)
    {
        try {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg'];
            $max_size = 3 * 1024 * 1024; // 3MB

            // Validate file
            if ($file['size'] > $max_size) {
                throw new Exception('File size exceeds 3MB limit');
            }

            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Only PDF and JPG files are allowed');
            }

            // Create uploads directory if not exists
            $upload_dir = __DIR__ . '/../uploads/appointments';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'appointment_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $filepath = $upload_dir . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to save uploaded file');
            }

            return 'uploads/appointments/' . $filename;
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return false;
        }
    }

    // Get student appointments
    public function getStudentAppointments()
    {
        try {
            $student_id = $_SESSION['student_id'] ?? null;
            if (!$student_id) {
                throw new Exception('Student not logged in');
            }

            $appointments = $this->appointmentModel->getStudentAppointments($student_id);
            $upcoming = $this->appointmentModel->getStudentUpcomingAppointments($student_id);
            $counts = $this->appointmentModel->getStudentAppointmentCounts($student_id);

            return [
                'all' => $appointments,
                'upcoming' => $upcoming,
                'counts' => $counts
            ];
        } catch (Exception $e) {
            error_log("Error getting student appointments: " . $e->getMessage());
            return null;
        }
    }

    // Cancel appointment
    public function cancelAppointment()
    {
        try {
            $appointment_id = $_POST['appointment_id'] ?? null;
            $reason = $_POST['reason'] ?? null;
            $student_id = $_SESSION['student_id'] ?? null;

            if (!$appointment_id || !$reason || !$student_id) {
                throw new Exception('Missing required fields');
            }

            // Verify student owns this appointment
            $appointment = $this->appointmentModel->getAppointmentById($appointment_id);
            if ($appointment['student_id'] != $student_id) {
                throw new Exception('Unauthorized');
            }

            // Cancel appointment
            if (!$this->appointmentModel->cancelAppointment($appointment_id, $reason, $student_id)) {
                throw new Exception('Failed to cancel appointment');
            }

            // Send email notification
            $this->emailNotification->sendAppointmentCancelledEmail($appointment, $reason);

            $_SESSION['success'] = 'Appointment cancelled successfully';
            header('Location: index.php?page=student_appointments');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?page=student_appointments');
            exit;
        }
    }

    public function getSubcategoriesAjax()
    {
        $category_id = $_GET['category_id'] ?? null;

        if (!$category_id) {
            echo json_encode(['success' => false, 'message' => 'Category ID required']);
            exit;
        }

        require_once __DIR__ . '/../model/AppointmentModel.php';
        $model = new AppointmentModel();
        $subcategories = $model->getSubcategoriesByCategory($category_id);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $subcategories]);
        exit;
    }

    public function getAvailableSlotsAjax()
    {
        $date = $_GET['date'] ?? null;

        if (!$date) {
            echo json_encode(['success' => false, 'message' => 'Date required']);
            exit;
        }

        $slots = [
            ['time' => '8:00 AM'],
            ['time' => '9:00 AM'],
            ['time' => '10:00 AM'],
            ['time' => '11:00 AM'],
            ['time' => '1:00 PM'],
            ['time' => '2:00 PM'],
            ['time' => '3:00 PM'],
            ['time' => '4:00 PM']
        ];

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $slots]);
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    session_start();
    require_once __DIR__ . '/../config/db_connection.php';

    $controller = new StudentAppointmentController();

    switch ($_GET['action']) {
        case 'getCategories':
            $controller->getCategories();
            break;
        case 'getSubcategories':
            $controller->getSubcategories();
            break;
        case 'getAvailableSlots':
            $controller->getAvailableSlots();
            break;
        case 'getAppointmentDetails':
            $controller->getAppointmentDetailsAjax();
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action not found']);
    }
    exit;
}

// Handle POST requests (form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_start();
    require_once __DIR__ . '/../config/db_connection.php';

    $controller = new StudentAppointmentController();

    switch ($_POST['action']) {
        case 'createAppointment':
            $controller->createAppointment();
            break;
        case 'cancelAppointment':
            $controller->cancelAppointment();
            break;
        default:
            $_SESSION['error'] = 'Invalid action';
            header('Location: index.php?page=student_appointments');
    }
    exit;
}
?>