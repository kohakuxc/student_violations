<?php
require_once __DIR__ . '/../config/db_connection.php';

class AppointmentModel
{
    private $conn;

    private function loadSettings()
    {
        require_once __DIR__ . '/../config/system_settings.php';
        return loadSystemSettings();
    }

    private function getDefaultOfficerId()
    {
        $settings = $this->loadSettings();
        return (int) ($settings['default_officer_id'] ?? (getenv('DEFAULT_OFFICER_ID') ?: 1));
    }

    private function pdoErrorMessage()
    {
        $errorInfo = $this->conn->errorInfo();
        return $errorInfo[2] ?? 'Unknown database error';
    }

    private function driverName()
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function isPgsql()
    {
        return $this->driverName() === 'pgsql';
    }

    private function hasAppointmentForDate($student_id, $appointment_date, $excludeAppointmentId = null)
    {
        $query = "SELECT COUNT(*) AS count FROM appointments
                  WHERE student_id = ? AND appointment_date = ?";
        $params = [(int) $student_id, (string) $appointment_date];
        if ($excludeAppointmentId) {
            $query .= " AND appointment_id != ?";
            $params[] = (int) $excludeAppointmentId;
        }
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int) ($result['count'] ?? 0)) > 0;
    }

    public function studentHasAppointmentOnDate($student_id, $appointment_date, $excludeAppointmentId = null)
    {
        return $this->hasAppointmentForDate($student_id, $appointment_date, $excludeAppointmentId);
    }

    private function isAppointmentLocked($appointment_id)
    {
        $stmt = $this->conn->prepare("SELECT locked_at, status FROM appointments WHERE appointment_id = ?");
        $stmt->execute([(int) $appointment_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if (!empty($row['locked_at'])) {
            return true;
        }
        return in_array($row['status'] ?? '', ['cancelled', 'rejected'], true);
    }

    private function lockAppointment($appointment_id, $lockedByRole = null, $lockedById = null)
    {
        $query = "UPDATE appointments
                  SET locked_at = CURRENT_TIMESTAMP,
                      locked_by_role = ?,
                      locked_by_id = ?,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE appointment_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $lockedByRole !== null ? (string) $lockedByRole : null,
            $lockedById !== null ? (int) $lockedById : null,
            (int) $appointment_id,
        ]);
    }

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    // Get all categories
    public function getAllCategories()
    {
        try {
            $stmt = $this->conn->prepare("
            SELECT category_id, category_name 
            FROM appointment_categories 
            ORDER BY category_name
        ");
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Exception in getAllCategories: " . $e->getMessage());
            return [];
        }
    }

    // Get subcategories by category_id
    public function getSubcategoriesByCategory($category_id)
    {
        $query = "SELECT subcategory_id, subcategory_name FROM appointment_subcategories 
                  WHERE category_id = ? ORDER BY subcategory_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$category_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create new appointment
    /**
     * Create a new appointment
     */
    // Check your table structure
    public function createAppointment($student_id, $category_id, $subcategory_id, $description, $scheduled_datetime, $evidence_image = null, $is_self_harm = false)
    {
        try {
            $log_file = __DIR__ . '/../logs/appointments.log';
            $isProduction = strtolower(trim(getenv('APP_ENV') ?: 'development')) === 'production';
            $log = function ($message, $force = false) use ($log_file, $isProduction) {
                if ($isProduction && !$force) {
                    return;
                }
                file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
            };

            if (!$student_id || !$category_id || !$subcategory_id || !$description || !$scheduled_datetime) {
                $log('MODEL ERROR: Missing required fields', true);
                return false;
            }

            $appointment_date = date('Y-m-d', strtotime((string) $scheduled_datetime));
            if (!$appointment_date) {
                $log('MODEL ERROR: Invalid appointment date', true);
                return false;
            }

            if ($this->hasAppointmentForDate($student_id, $appointment_date)) {
                $log('MODEL ERROR: Student already has appointment for date ' . $appointment_date, true);
                return false;
            }

            $query = "INSERT INTO appointments (
                    student_id,
                    officer_id,
                    category_id,
                    subcategory_id,
                    description,
                    appointment_date,
                    scheduled_date,
                    evidence_image,
                    status,
                    is_self_harm,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            $log('SQL Query prepared');

            $stmt = $this->conn->prepare($query);

            if (!$stmt) {
                $error = $this->pdoErrorMessage();
                $log("PREPARE ERROR: $error", true);
                return false;
            }

            $status = 'pending';
            $officer_id = $this->getDefaultOfficerId();

            $params = [
                (int) $student_id,
                (int) $officer_id,
                (int) $category_id,
                (int) $subcategory_id,
                (string) $description,
                (string) $appointment_date,
                (string) $scheduled_datetime,
                $evidence_image !== null ? (string) $evidence_image : null,
                (string) $status,
                (int) ($is_self_harm ? 1 : 0),
            ];

            // Diagnostic logging for parameter/placeholder mismatch
            $placeholderCount = substr_count($query, '?');
            $log('EXEC PARAMS COUNT: ' . count($params));
            $log('EXEC PLACEHOLDERS COUNT: ' . $placeholderCount);
            $log('EXEC PARAMS: ' . json_encode($params));

            $result = $stmt->execute($params);

            if (!$result) {
                $error_info = $stmt->errorInfo();
                $log("EXECUTE ERROR: " . ($error_info[2] ?? json_encode($error_info)), true);
                $log('QUERY STRING: ' . ($stmt->queryString ?? $query), true);
                return false;
            }

            $appointmentId = (int) $this->conn->lastInsertId();
            $log('SUCCESS: Appointment created');
            return $appointmentId > 0 ? $appointmentId : true;

        } catch (Exception $e) {
            $log_file = __DIR__ . '/../logs/appointments.log';
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public function getAppointmentStats()
    {
        try {
            $today = date('Y-m-d');
            $month_start = date('Y-m-01');

            $stats = [
                'today_count' => 0,
                'pending_count' => 0,
                'completed_this_month' => 0,
                'total_count' => 0
            ];

            // Today's appointments
            $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
                        WHERE CAST(scheduled_date AS DATE) = ?
                            AND status IN ('pending', 'approved', 'in_progress', 'rescheduled')
        ");
            $stmt->execute([$today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats['today_count'] = (int) $row['count'];
            }

            // Pending appointments
            $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE status = ?
        ");
            $stmt->execute(['pending']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats['pending_count'] = (int) $row['count'];
            }

            // Completed this month
            $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE status = ? AND CAST(scheduled_date AS DATE) >= ?
        ");
            $stmt->execute(['completed', $month_start]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats['completed_this_month'] = (int) $row['count'];
            }

            // Total
            $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM appointments
        ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats['total_count'] = (int) $row['count'];
            }

            return $stats;

        } catch (Exception $e) {
            error_log("Exception in getAppointmentStats: " . $e->getMessage());
            return [
                'today_count' => 0,
                'pending_count' => 0,
                'completed_this_month' => 0,
                'total_count' => 0
            ];
        }
    }

    /**
     * Count how many times a student has cancelled appointments (student-initiated)
     */
    public function getStudentCancellationCount($student_id)
    {
        try {
            $query = "SELECT COUNT(*) as count FROM appointments WHERE student_id = ? AND status = 'cancelled' AND locked_by_role = 'student'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $student_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($row['count'] ?? 0);
        } catch (Exception $e) {
            error_log('Error getting student cancellation count: ' . $e->getMessage());
            return 0;
        }
    }

    public function getAllAppointments($category_id = null, $status = null, $search = null, $sort = 'scheduled_date_desc', $limit = null, $offset = null)
    {
        try {
            $query = "SELECT a.*, 
                             o.name as officer_name,
                             c.category_name,
                             COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), CAST(a.student_id AS VARCHAR(50))) AS student_name
                      FROM appointments a
                      LEFT JOIN officers o ON a.officer_id = o.officer_id
                      LEFT JOIN appointment_categories c ON a.category_id = c.category_id
                      LEFT JOIN students st ON a.student_id = st.student_id
                      LEFT JOIN student_information si ON st.student_id = si.student_id
                      WHERE 1=1";
            $params = [];

            if ($category_id) {
                $query .= " AND a.category_id = ?";
                $params[] = (int) $category_id;
            }

            if ($status) {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }

            if ($search) {
                if ($this->isPgsql()) {
                    $query .= " AND (
                                    CAST(a.student_id AS TEXT) LIKE ?
                                    OR a.description LIKE ?
                                    OR CONCAT(COALESCE(si.first_name, ''), ' ', COALESCE(si.last_name, '')) ILIKE ?
                                    OR CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')) ILIKE ?
                                )";
                } else {
                    $query .= " AND (
                                    CAST(a.student_id AS NVARCHAR(50)) LIKE ?
                                    OR a.description LIKE ?
                                    OR CONCAT(COALESCE(si.first_name, ''), ' ', COALESCE(si.last_name, '')) LIKE ?
                                    OR CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')) LIKE ?
                                )";
                }
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            $orderBy = "a.scheduled_date DESC";
            if ($sort === 'scheduled_date_asc') {
                $orderBy = "a.scheduled_date ASC";
            } elseif ($sort === 'created_at_desc') {
                $orderBy = "a.created_at DESC";
            }

            $query .= " ORDER BY " . $orderBy;

            if ($limit !== null) {
                $limit = max(1, (int) $limit);
                $offset = max(0, (int) ($offset ?? 0));

                if ($this->isPgsql()) {
                    $query .= " LIMIT ? OFFSET ?";
                    $params[] = $limit;
                    $params[] = $offset;
                } else {
                    $query .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                    $params[] = $offset;
                    $params[] = $limit;
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Exception in getAllAppointments: " . $e->getMessage());
            return [];
        }
    }

    // Get student's appointments (with optional status filter, LEFT JOIN for officer)
    public function getStudentAppointments($student_id, $status = null)
    {
        try {
            $query = "SELECT a.*, 
                             c.category_name, 
                             s.subcategory_name,
                             o.name as officer_name
                     FROM appointments a
                      LEFT JOIN appointment_categories c ON a.category_id = c.category_id
                      LEFT JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
                      LEFT JOIN officers o ON a.officer_id = o.officer_id
                      WHERE a.student_id = ?";

            $params = [$student_id];
            if ($status) {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }
            $query .= " ORDER BY a.created_at DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Exception in getStudentAppointments: " . $e->getMessage());
            return [];
        }
    }

    // Get upcoming appointments for student
    public function getStudentUpcomingAppointments($student_id)
    {
        try {
            $query = "SELECT a.*, 
                             c.category_name, 
                             s.subcategory_name,
                             o.name as officer_name
                     FROM appointments a
                     LEFT JOIN appointment_categories c ON a.category_id = c.category_id
                     LEFT JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
                     LEFT JOIN officers o ON a.officer_id = o.officer_id
                      WHERE a.student_id = ? 
                      AND a.status IN ('pending', 'approved', 'rescheduled')
                     AND a.scheduled_date >= CURRENT_TIMESTAMP
                      ORDER BY a.scheduled_date ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Exception in getStudentUpcomingAppointments: " . $e->getMessage());
            return [];
        }
    }

    // Get appointment status counts for student
    public function getStudentAppointmentCounts($student_id)
    {
        $query = "SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_count,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
                  FROM appointments
                  WHERE student_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$student_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all appointments for officer (paginated)
    // Get all appointments for officer (paginated)
    public function getOfficerAppointments($officer_id, $page = 1, $per_page = 10, $category_id = null, $status = null)
    {
        try {
            // Ensure parameters are correct type
            $officer_id = (int) $officer_id;
            $page = (int) $page;
            $per_page = (int) $per_page;
            $offset = ($page - 1) * $per_page;

              $query = "SELECT a.*, 
                        c.category_name, 
                        s.subcategory_name,
                        COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                        COALESCE(NULLIF(si.email, ''), '') AS email
                    FROM appointments a
                    JOIN appointment_categories c ON a.category_id = c.category_id
                    JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
                    JOIN students st ON a.student_id = st.student_id
                    LEFT JOIN student_information si ON st.student_id = si.student_id
                  WHERE a.officer_id = ?";

            $params = [$officer_id];

            if ($category_id) {
                $query .= " AND a.category_id = ?";
                $params[] = (int) $category_id;
            }

            if ($status) {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }

            if ($this->isPgsql()) {
                $query .= " ORDER BY a.scheduled_date DESC LIMIT ? OFFSET ?";
                $params[] = $per_page;
                $params[] = $offset;
            } else {
                $query .= " ORDER BY a.scheduled_date DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                $params[] = $offset;
                $params[] = $per_page;
            }

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->pdoErrorMessage());
            }

            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting officer appointments: " . $e->getMessage());
            return [];
        }
    }

    // Get total appointments count for officer (for pagination)
    public function getOfficerAppointmentsCount($officer_id, $category_id = null, $status = null)
    {
        $query = "SELECT COUNT(*) as total FROM appointments WHERE officer_id = ?";
        $params = [$officer_id];

        if ($category_id) {
            $query .= " AND category_id = ?";
            $params[] = $category_id;
        }

        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Get today's appointments for officer
    public function getOfficerTodayAppointments($officer_id)
    {
                $query = "SELECT a.*, 
                                                 c.category_name, 
                                                 s.subcategory_name,
                                                 COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name
                FROM appointments a
                JOIN appointment_categories c ON a.category_id = c.category_id
                JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
                                JOIN students st ON a.student_id = st.student_id
                                LEFT JOIN student_information si ON st.student_id = si.student_id
                  WHERE a.officer_id = ? 
                AND CAST(a.scheduled_date AS DATE) = CAST(CURRENT_TIMESTAMP AS DATE)
                  AND a.status IN ('approved', 'in_progress')
                  ORDER BY a.scheduled_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$officer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get officer statistics
    // Get officer statistics
    public function getOfficerStats($officer_id)
    {
        try {
            $officer_id = (int) $officer_id;

                if ($this->isPgsql()) {
                $query = "SELECT 
                    (SELECT COUNT(*) FROM appointments 
                     WHERE officer_id = ? AND CAST(scheduled_date AS DATE) = CAST(CURRENT_TIMESTAMP AS DATE) 
                     AND status IN ('approved', 'in_progress')) as today_count,
                    (SELECT COUNT(*) FROM appointments 
                     WHERE officer_id = ? AND status = 'pending') as pending_count,
                    (SELECT COUNT(*) FROM appointments 
                     WHERE officer_id = ? AND status = 'completed' 
                     AND EXTRACT(MONTH FROM scheduled_date) = EXTRACT(MONTH FROM CURRENT_TIMESTAMP)
                     AND EXTRACT(YEAR FROM scheduled_date) = EXTRACT(YEAR FROM CURRENT_TIMESTAMP)) as completed_this_month";
                } else {
                $query = "SELECT 
                    (SELECT COUNT(*) FROM appointments 
                     WHERE officer_id = ? AND CAST(scheduled_date AS DATE) = CAST(CURRENT_TIMESTAMP AS DATE) 
                     AND status IN ('approved', 'in_progress')) as today_count,
                    (SELECT COUNT(*) FROM appointments 
                     WHERE officer_id = ? AND status = 'pending') as pending_count,
                    (SELECT COUNT(*) FROM appointments 
                     WHERE officer_id = ? AND status = 'completed' 
                     AND MONTH(scheduled_date) = MONTH(CURRENT_TIMESTAMP) 
                     AND YEAR(scheduled_date) = YEAR(CURRENT_TIMESTAMP)) as completed_this_month";
                }

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->pdoErrorMessage());
            }

            $stmt->execute([$officer_id, $officer_id, $officer_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: [
                'today_count' => 0,
                'pending_count' => 0,
                'completed_this_month' => 0
            ];
        } catch (Exception $e) {
            error_log("Error getting officer stats: " . $e->getMessage());
            return [
                'today_count' => 0,
                'pending_count' => 0,
                'completed_this_month' => 0
            ];
        }
    }

    // Get appointment details
    // Get appointment details
    public function getAppointmentById($appointment_id) {
    try {
        $stmt = $this->conn->prepare("
            SELECT a.*, 
                   o.name as officer_name,
                   c.category_name,
                   s.subcategory_name,
                   COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), CAST(a.student_id AS VARCHAR(50))) AS student_name
            FROM appointments a
            LEFT JOIN officers o ON a.officer_id = o.officer_id
            LEFT JOIN appointment_categories c ON a.category_id = c.category_id
            LEFT JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
            LEFT JOIN students st ON a.student_id = st.student_id
            LEFT JOIN student_information si ON st.student_id = si.student_id
            WHERE a.appointment_id = ?
        ");
        $stmt->execute([$appointment_id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            error_log("Appointment not found: ID = " . $appointment_id);
            return null;
        }

        return $result;

    } catch (Exception $e) {
        error_log("Exception in getAppointmentById: " . $e->getMessage());
        return null;
    }
}

    // Update appointment status
    public function updateAppointmentStatus($appointment_id, $status, $lockedByRole = null, $lockedById = null)
    {
        try {
            if ($this->isAppointmentLocked($appointment_id)) {
                return false;
            }

            $lockNow = in_array($status, ['cancelled', 'rejected'], true);
            $setParts = ['status = ?', 'updated_at = CURRENT_TIMESTAMP'];
            $params = [(string) $status];

            if ($lockNow) {
                $setParts[] = 'locked_at = CURRENT_TIMESTAMP';
                $setParts[] = 'locked_by_role = ?';
                $setParts[] = 'locked_by_id = ?';
                $params[] = $lockedByRole !== null ? (string) $lockedByRole : null;
                $params[] = $lockedById !== null ? (int) $lockedById : null;
            }

            $query = "UPDATE appointments SET " . implode(', ', $setParts) . " WHERE appointment_id = ?";
            $params[] = (int) $appointment_id;
            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Exception in updateAppointmentStatus: " . $e->getMessage());
            return false;
        }
    }

    // Assign officer to appointment
    public function assignOfficer($appointment_id, $officer_id)
    {
        try {
            if ($this->isAppointmentLocked($appointment_id)) {
                return false;
            }
            $stmt = $this->conn->prepare(
                "UPDATE appointments SET officer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ?"
            );
            return $stmt->execute([$officer_id, $appointment_id]);
        } catch (Exception $e) {
            error_log("Exception in assignOfficer: " . $e->getMessage());
            return false;
        }
    }

    // Add a note to an appointment
    public function addAppointmentNote($appointment_id, $note_text, $officer_id = null)
    {
        try {
            if ($this->isAppointmentLocked($appointment_id)) {
                return false;
            }
            $stmt = $this->conn->prepare(
                                "INSERT INTO appointment_notes (appointment_id, note_text, officer_id, created_at)
                  VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
            );
            return $stmt->execute([$appointment_id, $note_text, $officer_id]);
        } catch (Exception $e) {
            error_log("Exception in addAppointmentNote: " . $e->getMessage());
            return false;
        }
    }

    // Get all notes for an appointment
    public function getAppointmentNotes($appointment_id)
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT n.*, o.name as officer_name
                  FROM appointment_notes n
                  LEFT JOIN officers o ON n.officer_id = o.officer_id
                 WHERE n.appointment_id = ?
                 ORDER BY n.created_at ASC"
            );
            $stmt->execute([$appointment_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Exception in getAppointmentNotes: " . $e->getMessage());
            return [];
        }
    }

    // Get all appointments assigned to a specific officer (or all if officer_id is null)
    public function getOfficerAssignedAppointments($officer_id = null, $status = null)
    {
        try {
            $query = "SELECT a.*, 
                             c.category_name,
                             s.subcategory_name,
                             o.name as officer_name
                     FROM appointments a
                     LEFT JOIN appointment_categories c ON a.category_id = c.category_id
                     LEFT JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
                     LEFT JOIN officers o ON a.officer_id = o.officer_id
                      WHERE 1=1";
            $params = [];
            if ($officer_id !== null) {
                $query .= " AND a.officer_id = ?";
                $params[] = (int) $officer_id;
            }
            if ($status) {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }
            $query .= " ORDER BY a.scheduled_date DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Exception in getOfficerAssignedAppointments: " . $e->getMessage());
            return [];
        }
    }

    // Get recent pending appointment requests for notification center
    public function getRecentPendingAppointmentNotifications($limit = 8)
    {
        try {
            $query = "SELECT a.appointment_id,
                             a.student_id,
                             a.category_id,
                             a.created_at,
                             c.category_name,
                             COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), CAST(a.student_id AS VARCHAR(50))) AS student_name
                      FROM appointments a
                      LEFT JOIN appointment_categories c ON a.category_id = c.category_id
                      LEFT JOIN students st ON a.student_id = st.student_id
                      LEFT JOIN student_information si ON st.student_id = si.student_id
                      WHERE a.status = ?
                      ORDER BY a.created_at DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute(['pending']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_slice($rows, 0, max(1, (int) $limit));
        } catch (Exception $e) {
            error_log("Exception in getRecentPendingAppointmentNotifications: " . $e->getMessage());
            return [];
        }
    }

    // Reschedule appointment
    public function rescheduleAppointment($appointment_id, $new_date)
    {
        try {
            if ($this->isAppointmentLocked($appointment_id)) {
                return false;
            }
            $this->conn->beginTransaction();

            // Get current appointment
            $current = $this->getAppointmentById($appointment_id);

            // Update current to rescheduled
            $appointment_date = date('Y-m-d', strtotime((string) $new_date));
            if (!$appointment_date) {
                throw new Exception('Invalid appointment date');
            }
            if ($this->hasAppointmentForDate($current['student_id'] ?? 0, $appointment_date, $appointment_id)) {
                throw new Exception('Student already has an appointment on that day.');
            }

            $query = "UPDATE appointments 
                      SET status = 'rescheduled', scheduled_date = ?, appointment_date = ?, updated_at = CURRENT_TIMESTAMP
                      WHERE appointment_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$new_date, $appointment_date, $appointment_id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error rescheduling appointment: " . $e->getMessage());
            return false;
        }
    }

    // Add rejection/cancellation reason
    public function addReason($appointment_id, $reason_type, $reason_text, $created_by)
    {
        $query = "INSERT INTO appointment_reasons 
                  (appointment_id, reason_type, reason_text, created_by, created_at)
              VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$appointment_id, $reason_type, $reason_text, $created_by]);
    }

    // Get rejection/cancellation reason
    public function getReasonByAppointmentId($appointment_id)
    {
        if ($this->isPgsql()) {
            $query = "SELECT * FROM appointment_reasons 
                      WHERE appointment_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT 1";
        } else {
            $query = "SELECT TOP (1) * FROM appointment_reasons 
                      WHERE appointment_id = ? 
                      ORDER BY created_at DESC";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$appointment_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check if time slot is available for officer pukinanginang time slot napakatagal kong inayos to
    public function isTimeSlotAvailable($officer_id, $scheduled_date, $exclude_appointment_id = null)
    {
        try {
            $officer_id = (int) $officer_id;

            if ($this->isPgsql()) {
                $query = "SELECT COUNT(*) as count FROM appointments
                    WHERE officer_id = ?
                    AND ABS(EXTRACT(EPOCH FROM (scheduled_date - CAST(? AS timestamp)))) < 3600
                    AND status NOT IN ('cancelled', 'rejected')";
            } else {
                $query = "SELECT COUNT(*) as count FROM appointments 
                    WHERE officer_id = ? 
                    AND ABS(DATEDIFF(MINUTE, scheduled_date, ?)) < 60
                    AND status NOT IN ('cancelled', 'rejected')";
            }

            $params = [$officer_id, $scheduled_date];

            if ($exclude_appointment_id) {
                $query .= " AND appointment_id != ?";
                $params[] = (int) $exclude_appointment_id;
            }

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->pdoErrorMessage());
            }

            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] == 0;
        } catch (Exception $e) {
            error_log("Error checking time slot: " . $e->getMessage());
            return false;
        }
    }

    // Get available time slots for a date
    public function getAvailableTimeSlots($officer_id, $date)
    {
        $available_slots = [];
        $settings = $this->loadSettings();
        $officer_id = $officer_id ?: $this->getDefaultOfficerId();

        $start = $settings['office_hours_start'] ?? '08:00';
        $morningEnd = $settings['office_hours_morning_end'] ?? '12:00';
        $afternoonStart = $settings['office_hours_afternoon_start'] ?? '13:00';
        $end = $settings['office_hours_end'] ?? '17:00';
        $durationMinutes = max(15, (int) ($settings['appointment_duration_minutes'] ?? 60));

        $slots = [];
        $ranges = [
            [$start, $morningEnd],
            [$afternoonStart, $end],
        ];

        foreach ($ranges as $range) {
            [$rangeStart, $rangeEnd] = $range;
            $current = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $rangeStart);
            $rangeEndTime = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $rangeEnd);

            if (!$current || !$rangeEndTime) {
                continue;
            }

            while ($current < $rangeEndTime) {
                $slots[] = $current->format('H:i');
                $current->modify('+' . $durationMinutes . ' minutes');
            }
        }

        foreach ($slots as $time) {
            $datetime = $date . ' ' . $time;
            if ($this->isTimeSlotAvailable($officer_id, $datetime)) {
                $available_slots[] = $time;
            }
        }

        return $available_slots;
    }

    // Cancel appointment
    public function cancelAppointment($appointment_id, $reason, $student_id)
    {
        try {
            $this->conn->beginTransaction();

            // Update appointment status
            $updated = $this->updateAppointmentStatus($appointment_id, 'cancelled', 'student', $student_id);
            if (!$updated) {
                throw new Exception('Failed to update appointment status');
            }

            // Add cancellation reason
            $addedReason = $this->addReason($appointment_id, 'cancellation', $reason, $student_id);
            if (!$addedReason) {
                throw new Exception('Failed to add cancellation reason');
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error cancelling appointment: " . $e->getMessage());
            return false;
        }
    }

    // Reject appointment
    public function rejectAppointment($appointment_id, $reason, $officer_id)
    {
        try {
            $this->conn->beginTransaction();

            // Update appointment status to rejected
            $this->updateAppointmentStatus($appointment_id, 'rejected', 'officer', $officer_id);

            // Add rejection reason as a note
            $this->addAppointmentNote($appointment_id, 'Rejected: ' . $reason, $officer_id);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error rejecting appointment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create notification for appointment status change
     */
    public function createAppointmentNotification($appointment_id, $status, $officer_id, $officer_name = '')
    {
        try {
            require_once __DIR__ . '/NotificationModel.php';
            $notificationModel = new NotificationModel();

            $appointment = $this->getAppointmentById($appointment_id);
            if (!$appointment) {
                throw new Exception("Appointment not found");
            }

            // Get student name
            $studentName = $appointment['student_name'] ?? 'Student ID: ' . $appointment['student_id'];

            // Build notification title and message based on status
            $notificationTypeMap = [
                'approved' => [
                    'type' => 'appointment_approved',
                    'title' => 'Appointment Approved',
                    'message' => "Your appointment on " . date('M j, Y g:i A', strtotime($appointment['scheduled_date'])) . " has been approved by Officer " . ($officer_name ?: 'Staff'),
                    'recipient_role' => 'student',
                    'recipient_id' => (int) $appointment['student_id'],
                    'target_url' => 'index.php?page=student_appointments&appointment_id=' . $appointment_id
                ],
                'rejected' => [
                    'type' => 'appointment_rejected',
                    'title' => 'Appointment Rejected',
                    'message' => "Your appointment request has been rejected. Please contact the office for more information.",
                    'recipient_role' => 'student',
                    'recipient_id' => (int) $appointment['student_id'],
                    'target_url' => 'index.php?page=student_appointments&appointment_id=' . $appointment_id
                ],
                'rescheduled' => [
                    'type' => 'appointment_rescheduled',
                    'title' => 'Appointment Rescheduled',
                    'message' => "Your appointment has been rescheduled to " . date('M j, Y g:i A', strtotime($appointment['scheduled_date'])),
                    'recipient_role' => 'student',
                    'recipient_id' => (int) $appointment['student_id'],
                    'target_url' => 'index.php?page=student_appointments&appointment_id=' . $appointment_id
                ],
                'completed' => [
                    'type' => 'appointment_completed',
                    'title' => 'Appointment Completed',
                    'message' => "Your appointment on " . date('M j, Y g:i A', strtotime($appointment['scheduled_date'])) . " has been marked as completed.",
                    'recipient_role' => 'student',
                    'recipient_id' => (int) $appointment['student_id'],
                    'target_url' => 'index.php?page=student_appointments&appointment_id=' . $appointment_id
                ],
                'pending' => [
                    'type' => 'appointment_pending',
                    'title' => 'Appointment Pending',
                    'message' => 'Your appointment request is pending review by the assigned officer.',
                    'recipient_role' => 'student',
                    'recipient_id' => (int) $appointment['student_id'],
                    'target_url' => 'index.php?page=student_appointments&appointment_id=' . $appointment_id
                ],
                'request' => [
                    'type' => 'appointment_request',
                    'title' => 'Appointment Request',
                    'message' => "New appointment request from " . $studentName . " on " . date('M j, Y g:i A', strtotime($appointment['scheduled_date'])),
                    'recipient_role' => 'officer',
                    'recipient_id' => (int) $officer_id,
                    'target_url' => 'index.php?page=officer_appointments&appointment_id=' . $appointment_id
                ],
                'cancelled' => [
                    'type' => 'appointment_cancelled',
                    'title' => 'Appointment Cancelled',
                    'message' => 'Your appointment has been cancelled.',
                    'recipient_role' => 'student',
                    'recipient_id' => (int) $appointment['student_id'],
                    'target_url' => 'index.php?page=student_appointments&appointment_id=' . $appointment_id
                ]
            ];

            $notifConfig = $notificationTypeMap[$status] ?? $notificationTypeMap['pending'];
            
            return $notificationModel->createNotificationForRecipient(
                $notifConfig['recipient_role'],
                (int) $notifConfig['recipient_id'],
                $notifConfig['type'],
                $notifConfig['title'],
                $notifConfig['message'],
                $appointment_id,
                $notifConfig['target_url']
            );

        } catch (Exception $e) {
            error_log("Error creating appointment notification: " . $e->getMessage());
            return false;
        }
    }
}
?>
