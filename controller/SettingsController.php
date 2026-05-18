<?php
require_once __DIR__ . '/../config/system_settings.php';
require_once __DIR__ . '/../model/NotificationModel.php';

if (!isset($_SESSION['officer_id'])) {
    header('Location: index.php?page=login');
    exit();
}

$settings = loadSystemSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activeTab = trim((string) ($_POST['active_tab'] ?? 'student'));
    $allowedTabs = ['student', 'officer', 'admin'];
    if (!in_array($activeTab, $allowedTabs, true)) {
        $activeTab = 'student';
    }

    $workingDays = $_POST['working_days'] ?? [];
    if (!is_array($workingDays)) {
        $workingDays = [];
    }

    $allowedTypesRaw = trim((string) ($_POST['allowed_file_types'] ?? ''));
    $allowedFileTypes = array_values(array_filter(array_map('trim', explode(',', $allowedTypesRaw))));

    if (empty($allowedFileTypes)) {
        $allowedFileTypes = systemSettingsDefaultValues()['allowed_file_types'];
    }

    $settings = [
        'app_name' => trim((string) ($_POST['app_name'] ?? systemSettingsDefaultValues()['app_name'])),
        'default_officer_id' => max(1, (int) ($_POST['default_officer_id'] ?? 1)),
        'dashboard_page_size' => max(1, (int) ($_POST['dashboard_page_size'] ?? 10)),
        'student_dashboard_page_size' => max(1, (int) ($_POST['student_dashboard_page_size'] ?? 10)),
        'student_default_view' => in_array($_POST['student_default_view'] ?? 'violations', ['violations', 'appointments', 'escalations'], true)
            ? (string) $_POST['student_default_view']
            : 'violations',
        'student_show_escalation_history' => isset($_POST['student_show_escalation_history']),
        'student_email_notify_appointment_updates' => isset($_POST['student_email_notify_appointment_updates']),
        'appointment_duration_minutes' => max(15, (int) ($_POST['appointment_duration_minutes'] ?? 60)),
        'office_hours_start' => trim((string) ($_POST['office_hours_start'] ?? '08:00')),
        'office_hours_morning_end' => trim((string) ($_POST['office_hours_morning_end'] ?? '12:00')),
        'office_hours_afternoon_start' => trim((string) ($_POST['office_hours_afternoon_start'] ?? '13:00')),
        'office_hours_end' => trim((string) ($_POST['office_hours_end'] ?? '17:00')),
        'working_days' => array_values(array_map('intval', $workingDays)),
        'min_advance_days' => max(0, (int) ($_POST['min_advance_days'] ?? 0)),
        'max_advance_days' => max(0, (int) ($_POST['max_advance_days'] ?? 30)),
        'max_file_size_mb' => max(1, (int) ($_POST['max_file_size_mb'] ?? 3)),
        'allowed_file_types' => $allowedFileTypes,
        'email_from_name' => trim((string) ($_POST['email_from_name'] ?? 'STI Student Violations System')),
        'email_from_address' => trim((string) ($_POST['email_from_address'] ?? 'noreply@fairview-violations.edu.ph')),
        'officer_email_notify_new_appointments' => isset($_POST['officer_email_notify_new_appointments']),
        'officer_email_notify_status_changes' => isset($_POST['officer_email_notify_status_changes']),
        'officer_default_sort' => in_array($_POST['officer_default_sort'] ?? 'scheduled_date_desc', ['scheduled_date_desc', 'scheduled_date_asc', 'created_at_desc'], true)
            ? (string) $_POST['officer_default_sort']
            : 'scheduled_date_desc',
        'auto_escalation_enabled' => isset($_POST['auto_escalation_enabled']),
        'minor_escalation_threshold' => max(1, (int) ($_POST['minor_escalation_threshold'] ?? 3)),
    ];

    if ($settings['office_hours_start'] === '' || $settings['office_hours_end'] === '') {
        $_SESSION['settings_flash'] = ['type' => 'error', 'message' => 'Office hours are required.'];
    } elseif (!filter_var($settings['email_from_address'], FILTER_VALIDATE_EMAIL)) {
        $_SESSION['settings_flash'] = ['type' => 'error', 'message' => 'Please provide a valid email address for notifications.'];
    } elseif (empty($settings['working_days'])) {
        $_SESSION['settings_flash'] = ['type' => 'error', 'message' => 'Select at least one working day.'];
    } elseif ($settings['max_advance_days'] < $settings['min_advance_days']) {
        $_SESSION['settings_flash'] = ['type' => 'error', 'message' => 'Maximum advance days must be greater than or equal to minimum advance days.'];
    } elseif (saveSystemSettings($settings)) {
        // Create notification for all admin officers
        try {
            global $conn;
            $notificationModel = new NotificationModel();
            $officer_name = htmlspecialchars((string) ($_SESSION['name'] ?? 'Unknown Officer'));
            $tab_name = ucfirst($activeTab);
            $title = $officer_name . ' updated ' . $tab_name . ' settings';
            $message = 'Officer ' . $officer_name . ' made changes to ' . $tab_name . ' configuration.';
            $target_url = 'index.php?page=settings&tab=' . urlencode($activeTab);

            // Find all admin officers
            $admin_query = "SELECT officer_id FROM officers WHERE is_admin = true AND officer_id != ?";
            $admin_stmt = $conn->prepare($admin_query);
            $admin_stmt->execute([(int) $_SESSION['officer_id']]);
            $admin_officers = $admin_stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($admin_officers as $admin) {
                $notificationModel->createNotification(
                    (int) $admin['officer_id'],
                    'settings_changed',
                    $title,
                    $message,
                    null,
                    $target_url
                );
            }
        } catch (Exception $e) {
            error_log("Error creating settings notification: " . $e->getMessage());
        }
        $_SESSION['settings_flash'] = ['type' => 'success', 'message' => 'Settings saved successfully.'];
    } else {
        $_SESSION['settings_flash'] = ['type' => 'error', 'message' => 'Unable to save settings right now.'];
    }

    header('Location: index.php?page=settings&tab=' . urlencode($activeTab));
    exit();
}

$flash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);

include 'view/settings.php';
