<?php

if (!function_exists('systemSettingsDefaultValues')) {
    function systemSettingsDefaultValues()
    {
        return [
            'app_name' => 'Student Violations System',
            'default_officer_id' => 1,
            'dashboard_page_size' => 10,
            'student_dashboard_page_size' => 10,
            'student_default_view' => 'violations',
            'student_show_escalation_history' => true,
            'student_email_notify_appointment_updates' => true,
            'appointment_duration_minutes' => 60,
            'office_hours_start' => '08:00',
            'office_hours_morning_end' => '12:00',
            'office_hours_afternoon_start' => '13:00',
            'office_hours_end' => '17:00',
            'working_days' => [1, 2, 3, 4, 5],
            'min_advance_days' => 0,
            'max_advance_days' => 30,
            'max_file_size_mb' => 3,
            'allowed_file_types' => ['application/pdf', 'image/jpeg', 'image/jpg'],
            'email_from_name' => 'STI Student Violations System',
            'email_from_address' => 'noreply@fairview-violations.edu.ph',
            'officer_email_notify_new_appointments' => true,
            'officer_email_notify_status_changes' => true,
            'officer_default_sort' => 'scheduled_date_desc',
            'auto_escalation_enabled' => true,
            'minor_escalation_threshold' => 3,
            'smtp_enabled' => false,
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 465,
            'smtp_user' => '',
            'smtp_password' => '',
            'email_use_queue' => true,
        ];
    }
}

if (!function_exists('systemSettingsFilePath')) {
    function systemSettingsFilePath()
    {
        return __DIR__ . '/system_settings.json';
    }
}

if (!function_exists('loadSystemSettings')) {
    function loadSystemSettings()
    {
        $settings = systemSettingsDefaultValues();
        $filePath = systemSettingsFilePath();

        if (!file_exists($filePath)) {
            return $settings;
        }

        $decoded = json_decode((string) file_get_contents($filePath), true);
        if (!is_array($decoded)) {
            return $settings;
        }

        return array_replace_recursive($settings, $decoded);
    }
}

if (!function_exists('saveSystemSettings')) {
    function saveSystemSettings(array $settings)
    {
        $filePath = systemSettingsFilePath();
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return file_put_contents($filePath, $json . PHP_EOL) !== false;
    }
}

if (!function_exists('systemSettingsAuditLogPath')) {
    function systemSettingsAuditLogPath()
    {
        return __DIR__ . '/../logs/settings_changes.log';
    }
}

if (!function_exists('logSystemSettingsChange')) {
    function logSystemSettingsChange(array $entry)
    {
        $logPath = systemSettingsAuditLogPath();
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $payload = [
            'changed_at' => date('Y-m-d H:i:s'),
            'officer_id' => (int) ($entry['officer_id'] ?? 0),
            'officer_name' => (string) ($entry['officer_name'] ?? 'Unknown Officer'),
            'active_tab' => (string) ($entry['active_tab'] ?? 'student'),
            'target_url' => (string) ($entry['target_url'] ?? 'index.php?page=settings'),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents($logPath, $json . PHP_EOL, FILE_APPEND) !== false;
    }
}

if (!function_exists('getRecentSystemSettingsChanges')) {
    function getRecentSystemSettingsChanges($limit = 8)
    {
        $logPath = systemSettingsAuditLogPath();
        if (!file_exists($logPath)) {
            return [];
        }

        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || empty($lines)) {
            return [];
        }

        $events = [];
        foreach ($lines as $line) {
            $decoded = json_decode((string) $line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $events[] = $decoded;
        }

        if (empty($events)) {
            return [];
        }

        usort($events, static function ($a, $b) {
            return strcmp((string) ($b['changed_at'] ?? ''), (string) ($a['changed_at'] ?? ''));
        });

        return array_slice($events, 0, max(1, (int) $limit));
    }
}
