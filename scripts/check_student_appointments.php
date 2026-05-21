<?php
require_once __DIR__ . '/../config/env_loader.php';
loadEnvFile(__DIR__ . '/../config/.env');
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/StudentModel.php';
require_once __DIR__ . '/../model/AppointmentModel.php';

$student_num = $argv[1] ?? null;
$date = $argv[2] ?? null;
if (!$student_num || !$date) {
    echo "Usage: php check_student_appointments.php <student_num> <YYYY-MM-DD>\n";
    exit(1);
}

$studentModel = new StudentModel();
$appointmentModel = new AppointmentModel();

$student = $studentModel->getStudentByNumber($student_num);
if (!$student) {
    echo json_encode(['found' => false, 'message' => 'Student not found', 'student_num' => $student_num]) . "\n";
    exit(0);
}

$student_id = $student['student_id'];
$appointments = $appointmentModel->getStudentAppointments($student_id);
$matches = [];
foreach ($appointments as $a) {
    $scheduled_date = date('Y-m-d', strtotime((string) $a['scheduled_date'] ?? $a['appointment_date'] ?? ''));
    if ($scheduled_date === $date) {
        $matches[] = $a;
    }
}

if (count($matches) === 0) {
    echo json_encode(['found' => false, 'student' => $student, 'date' => $date]) . "\n";
} else {
    echo json_encode(['found' => true, 'student' => $student, 'date' => $date, 'appointments' => $matches]) . "\n";
}

exit(0);
