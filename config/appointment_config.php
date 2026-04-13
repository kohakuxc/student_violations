<?php
// Appointment System Configuration

// Office Hours (24-hour format)
define('OFFICE_HOURS_START', '08:00');  // 8:00 AM
define('OFFICE_HOURS_END_MORNING', '12:00');  // 12:00 PM
define('OFFICE_HOURS_START_AFTERNOON', '13:00');  // 1:00 PM
define('OFFICE_HOURS_END', '17:00');  // 5:00 PM

// Appointment Duration in minutes
define('APPOINTMENT_DURATION', 60);

// Maximum file upload size (in bytes)
define('MAX_FILE_SIZE', 3 * 1024 * 1024);  // 3 MB

// Allowed file types
define('ALLOWED_FILE_TYPES', ['application/pdf', 'image/jpeg', 'image/jpg']);
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg']);

// Email settings
define('EMAIL_FROM_NAME', 'STI Student Violations System');
define('EMAIL_FROM_ADDRESS', 'noreply@fairview-violations.edu.ph');

// Appointment statuses
define('APPOINTMENT_STATUSES', [
    'pending' => 'Pending Review',
    'approved' => 'Approved',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
    'rescheduled' => 'Rescheduled'
]);

// Status colors for UI
define('STATUS_COLORS', [
    'pending' => 'warning',
    'approved' => 'info',
    'in_progress' => 'purple',
    'completed' => 'success',
    'rejected' => 'danger',
    'cancelled' => 'secondary',
    'rescheduled' => 'cyan'
]);

// Appointment categories
define('APPOINTMENT_CATEGORIES', [
    1 => 'Attendance & Administrative Appointments',
    2 => 'Behavioral & Disciplinary Meetings',
    3 => 'Safety & Counseling Referrals',
    4 => 'Special Hearings'
]);

// Subcategories by category
define('APPOINTMENT_SUBCATEGORIES', [
    1 => [
        'Uniform Exemption / Gate Pass',
        'Violation Review',
        'Obtaining Certificates'
    ],
    2 => [
        'Parent-Teacher Conference',
        'Investigation Hearing',
        'Behavioral Reports'
    ],
    3 => [
        'Mediation between Peers',
        'Confiscated Item Retrieval',
        'Counseling Meeting'
    ],
    4 => [
        'Suspension Appeal',
        'Re-admission Interview',
        'Instructor Review'
    ]
]);

// Days of week (for validation)
define('WORKING_DAYS', [1, 2, 3, 4, 5]);  // 1 = Monday, 5 = Friday

// Appointment advance booking (in days)
define('MIN_ADVANCE_DAYS', 0);  // Can book same day
define('MAX_ADVANCE_DAYS', 30);  // Can book up to 30 days in advance

// Email templates directory
define('EMAIL_TEMPLATES_DIR', __DIR__ . '/../templates/emails/');

// Upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/appointments/');

// Database settings
define('DB_TABLE_APPOINTMENTS', 'dbo.appointments');
define('DB_TABLE_CATEGORIES', 'dbo.appointment_categories');
define('DB_TABLE_SUBCATEGORIES', 'dbo.appointment_subcategories');
define('DB_TABLE_REASONS', 'dbo.appointment_reasons');
?>