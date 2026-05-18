<?php $pageTitle = 'Settings';
include 'view/partials/layout_top.php';

$defaults = systemSettingsDefaultValues();
$workingDays = $settings['working_days'] ?? $defaults['working_days'];
$allowedFileTypes = implode(', ', $settings['allowed_file_types'] ?? $defaults['allowed_file_types']);
$activeTab = $_GET['tab'] ?? 'student';
if (!in_array($activeTab, ['student', 'officer', 'admin'], true)) {
    $activeTab = 'student';
}
$daysMap = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
];
?>

<style>
    .settings-shell {
        display: grid;
        gap: 20px;
    }

    .settings-grid {
        display: grid;
        gap: 20px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .tab-row {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        border-radius: 0.5rem;
        background-color: #EEE;
        box-sizing: border-box;
        box-shadow: 0 0 0px 1px #0000000f;
        padding: 0.25rem;
        width: 270px;
        font-size: 14px;
        gap: 10px;
    }

    .tab-btn {
        border: 1px #cbd5e1;
        background: #EEE;
        color: #1e293b;
        padding: 10px 14px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        flex: 1 1 auto;
    }

    .tab-btn.active {
        border-color: #0b5793;
        background: #f8fafc;
        color: #0b5793;
    }

    .settings-tab {
        display: none;
    }

    .settings-tab.active {
        display: block;
    }

    .settings-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
        border: 1px solid #e5e7eb;
    }

    .settings-card h3 {
        margin: 0 0 14px;
        font-size: 18px;
        font-weight: 800;
    }

    .settings-card .form-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .settings-card .form-group {
        margin-bottom: 14px;
    }

    .settings-card label {
        font-weight: 700;
        margin-bottom: 6px;
        display: block;
    }

    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        padding: 10px 12px;
        border-radius: 10px;
        width: 100%;
    }

    .settings-card label.checkbox-item {
        display: flex;
        align-items: center;
        gap: 10px;
        justify-content: flex-start;
        margin-bottom: 10px;
    }

    .checkbox-item input[type="checkbox"] {
        width: auto;
        min-width: 16px;
        height: 16px;
        margin: 0;
        padding: 0;
        border: 0;
        box-shadow: none;
        flex: 0 0 auto;
    }

    .checkbox-item span {
        line-height: 1.35;
    }

    .settings-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .settings-note {
        color: #64748b;
        font-size: 0.92rem;
        margin-top: 8px;
    }

    @media (max-width: 960px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }

        .checkbox-grid,
        .settings-card .form-row {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 640px) {

        .checkbox-grid,
        .settings-card .form-row {
            grid-template-columns: 1fr;
        }

        .settings-actions {
            justify-content: stretch;
            flex-direction: column;
        }
    }
</style>

<div class="settings-shell">
    <div class="page-header">
        <div>
            <h2 class="page-title">Settings</h2>
            <p class="settings-note">Adjust student, officer, and system behavior without editing code.</p>
        </div>
        <a href="index.php?page=dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger'); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=settings">
        <input type="hidden" name="active_tab" id="active_tab" value="<?php echo htmlspecialchars($activeTab); ?>">

        <div class="tab-row" style="margin-bottom: 16px;">
            <button type="button" class="tab-btn <?php echo $activeTab === 'student' ? 'active' : ''; ?>"
                data-tab="student">Student</button>
            <button type="button" class="tab-btn <?php echo $activeTab === 'officer' ? 'active' : ''; ?>"
                data-tab="officer">Officer</button>
            <button type="button" class="tab-btn <?php echo $activeTab === 'admin' ? 'active' : ''; ?>"
                data-tab="admin">Admin</button>
        </div>

        <div class="settings-tab <?php echo $activeTab === 'student' ? 'active' : ''; ?>" id="tab-student">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Student Experience</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="student_default_view">Default View</label>
                            <?php $studentDefaultView = $settings['student_default_view'] ?? $defaults['student_default_view']; ?>
                            <select id="student_default_view" name="student_default_view" class="form-control">
                                <option value="violations" <?php echo $studentDefaultView === 'violations' ? 'selected' : ''; ?>>Violations</option>
                                <option value="appointments" <?php echo $studentDefaultView === 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                                <option value="escalations" <?php echo $studentDefaultView === 'escalations' ? 'selected' : ''; ?>>Escalation History</option>
                            </select>
                        </div>
                        <div class="form-group">


                            <label for="student_dashboard_page_size">Items Per Page
                                <!-- From Uiverse.io by SmookyDev -->
                                <div class="helptooltip">
                                    <div class="icon">?</div>
                                    <div class="helptooltiptext">Changes the amount of violations showed on the
                                        student's dashboard.</div>
                                </div>
                            </label>
                            <input type="number" id="student_dashboard_page_size" name="student_dashboard_page_size"
                                class="form-control" min="1"
                                value="<?php echo (int) ($settings['student_dashboard_page_size'] ?? $defaults['student_dashboard_page_size']); ?>">
                        </div>
                    </div>

                    <div class="form-row" style="margin-top: 12px;">

                        <label class="checkbox-item">
                            <input type="checkbox" name="student_show_escalation_history" value="1" <?php echo !empty($settings['student_show_escalation_history']) ? 'checked' : ''; ?>>
                            <span>Show escalation history panel on student dashboard</span>
                        </label>


                        <label class="checkbox-item">
                            <input type="checkbox" name="student_email_notify_appointment_updates" value="1" <?php echo !empty($settings['student_email_notify_appointment_updates']) ? 'checked' : ''; ?>>
                            <span>Email students for appointment updates</span>
                        </label>
                    </div>
                </div>



            </div>
        </div>

        <div class="settings-tab <?php echo $activeTab === 'officer' ? 'active' : ''; ?>" id="tab-officer">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Officer Appointments</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dashboard_page_size">Dashboard Page Size</label>
                            <input type="number" id="dashboard_page_size" name="dashboard_page_size"
                                class="form-control" min="1"
                                value="<?php echo (int) ($settings['dashboard_page_size'] ?? $defaults['dashboard_page_size']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="officer_default_sort">Default Sort</label>
                            <?php $officerDefaultSort = $settings['officer_default_sort'] ?? $defaults['officer_default_sort']; ?>
                            <select id="officer_default_sort" name="officer_default_sort" class="form-control">
                                <option value="scheduled_date_desc" <?php echo $officerDefaultSort === 'scheduled_date_desc' ? 'selected' : ''; ?>>Scheduled Date
                                    (Newest)</option>
                                <option value="scheduled_date_asc" <?php echo $officerDefaultSort === 'scheduled_date_asc' ? 'selected' : ''; ?>>Scheduled Date (Oldest)</option>
                                <option value="created_at_desc" <?php echo $officerDefaultSort === 'created_at_desc' ? 'selected' : ''; ?>>Created At (Newest)</option>
                            </select>
                        </div>
                    </div>
                    <label class="checkbox-item" style="margin-bottom: 10px;">
                        <input type="checkbox" name="officer_email_notify_new_appointments" value="1" <?php echo !empty($settings['officer_email_notify_new_appointments']) ? 'checked' : ''; ?>>
                        <span>Email officers for new appointment submissions</span>
                    </label>
                    <label class="checkbox-item" style="margin-bottom: 10px;">
                        <input type="checkbox" name="officer_email_notify_status_changes" value="1" <?php echo !empty($settings['officer_email_notify_status_changes']) ? 'checked' : ''; ?>>
                        <span>Email officers on status changes by other officers</span>
                    </label>
                </div>

                <div class="settings-card">
                    <h3>Office Hours</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="office_hours_start">Start</label>
                            <input type="time" id="office_hours_start" name="office_hours_start" class="form-control"
                                value="<?php echo htmlspecialchars($settings['office_hours_start'] ?? $defaults['office_hours_start']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_hours_morning_end">Morning End</label>
                            <input type="time" id="office_hours_morning_end" name="office_hours_morning_end"
                                class="form-control"
                                value="<?php echo htmlspecialchars($settings['office_hours_morning_end'] ?? $defaults['office_hours_morning_end']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_hours_afternoon_start">Afternoon Start</label>
                            <input type="time" id="office_hours_afternoon_start" name="office_hours_afternoon_start"
                                class="form-control"
                                value="<?php echo htmlspecialchars($settings['office_hours_afternoon_start'] ?? $defaults['office_hours_afternoon_start']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_hours_end">End</label>
                            <input type="time" id="office_hours_end" name="office_hours_end" class="form-control"
                                value="<?php echo htmlspecialchars($settings['office_hours_end'] ?? $defaults['office_hours_end']); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="appointment_duration_minutes">Slot Duration (minutes)</label>
                            <input type="number" id="appointment_duration_minutes" name="appointment_duration_minutes"
                                class="form-control" min="15" step="15"
                                value="<?php echo (int) ($settings['appointment_duration_minutes'] ?? $defaults['appointment_duration_minutes']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="default_officer_id">Default Officer ID</label>
                            <input type="number" id="default_officer_id" name="default_officer_id" class="form-control"
                                min="1"
                                value="<?php echo (int) ($settings['default_officer_id'] ?? $defaults['default_officer_id']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Working Days</label>
                        <div class="checkbox-grid">
                            <?php foreach ($daysMap as $dayNumber => $dayLabel): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="working_days[]" value="<?php echo (int) $dayNumber; ?>"
                                        <?php echo in_array($dayNumber, $workingDays, true) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($dayLabel); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-tab <?php echo $activeTab === 'admin' ? 'active' : ''; ?>" id="tab-admin">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Application</h3>
                    <div class="form-group">
                        <label for="app_name">App Name</label>
                        <input type="text" id="app_name" name="app_name" class="form-control"
                            value="<?php echo htmlspecialchars($settings['app_name'] ?? $defaults['app_name']); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="min_advance_days">Minimum Advance Days</label>
                            <input type="number" id="min_advance_days" name="min_advance_days" class="form-control"
                                min="0"
                                value="<?php echo (int) ($settings['min_advance_days'] ?? $defaults['min_advance_days']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="max_advance_days">Maximum Advance Days</label>
                            <input type="number" id="max_advance_days" name="max_advance_days" class="form-control"
                                min="0"
                                value="<?php echo (int) ($settings['max_advance_days'] ?? $defaults['max_advance_days']); ?>">
                        </div>
                    </div>
                </div>

                <div class="settings-card">
                    <h3>Uploads & Email</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_file_size_mb">Max File Size (MB)</label>
                            <input type="number" id="max_file_size_mb" name="max_file_size_mb" class="form-control"
                                min="1"
                                value="<?php echo (int) ($settings['max_file_size_mb'] ?? $defaults['max_file_size_mb']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="allowed_file_types">Allowed File Types</label>
                            <input type="text" id="allowed_file_types" name="allowed_file_types" class="form-control"
                                value="<?php echo htmlspecialchars($allowedFileTypes); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email_from_name">Email From Name</label>
                        <input type="text" id="email_from_name" name="email_from_name" class="form-control"
                            value="<?php echo htmlspecialchars($settings['email_from_name'] ?? $defaults['email_from_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email_from_address">Email From Address</label>
                        <input type="email" id="email_from_address" name="email_from_address" class="form-control"
                            value="<?php echo htmlspecialchars($settings['email_from_address'] ?? $defaults['email_from_address']); ?>">
                    </div>
                </div>

                <div class="settings-card">
                    <h3>Violation Rules</h3>
                    <label class="checkbox-item" style="width: fit-content; margin-bottom: 12px;">
                        <input type="checkbox" name="auto_escalation_enabled" value="1" <?php echo !empty($settings['auto_escalation_enabled']) ? 'checked' : ''; ?>>
                        <span>Enable auto escalation</span>
                        <!-- From Uiverse.io by SmookyDev -->
                                <div class="helptooltip">
                                    <div class="icon">?</div>
                                    <div class="helptooltiptext">Should be adjusted according to STI Constitution.</div>
                                </div>
                    </label>
                    <div class="form-group">
                        <label for="minor_escalation_threshold">Minor Offenses Needed for Escalation</label>
                        <input type="number" id="minor_escalation_threshold" name="minor_escalation_threshold"
                            class="form-control" min="1"
                            value="<?php echo (int) ($settings['minor_escalation_threshold'] ?? $defaults['minor_escalation_threshold']); ?>">
                    </div>
                    <p class="settings-note">These values are designed for the existing 3-minor-to-1-major workflow, but
                        can be adjusted if policy changes.</p>
                </div>
            </div>
        </div>

        <div class="settings-actions" style="margin-top: 20px;">
            <a href="index.php?page=dashboard" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tabButtons = document.querySelectorAll('.tab-btn');
        var tabPanels = document.querySelectorAll('.settings-tab');
        var activeTabInput = document.getElementById('active_tab');

        function activateTab(tabName) {
            tabButtons.forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.tab === tabName);
            });

            tabPanels.forEach(function (panel) {
                panel.classList.toggle('active', panel.id === 'tab-' + tabName);
            });

            if (activeTabInput) {
                activeTabInput.value = tabName;
            }
        }

        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateTab(btn.dataset.tab || 'student');
            });
        });

        activateTab('<?php echo htmlspecialchars($activeTab); ?>');
    });
</script>

<?php include 'view/partials/layout_bottom.php'; ?>