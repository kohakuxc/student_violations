<?php
require_once __DIR__ . '/../config/db_connection.php';

class AppointmentModel
{
    private $conn;

    private function driverName()
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function isPgsql()
    {
        return $this->driverName() === 'pgsql';
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
    public function createAppointment($student_id, $category_id, $subcategory_id, $description, $scheduled_datetime, $evidence_image = null)
    {
        try {
            $log_file = __DIR__ . '/../logs/appointments.log';

            if (!$student_id || !$category_id || !$subcategory_id || !$description || !$scheduled_datetime) {
                file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] MODEL ERROR: Missing required fields\n", FILE_APPEND);
                return false;
            }

            $query = "INSERT INTO appointments (
                    student_id,
                    officer_id,
                    category_id,
                    subcategory_id,
                    description,
                    scheduled_date,
                    evidence_image,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] SQL Query prepared\n", FILE_APPEND);

            $stmt = $this->conn->prepare($query);

            if (!$stmt) {
                $error = $this->conn->error;
                file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] PREPARE ERROR: $error\n", FILE_APPEND);
                return false;
            }

            $status = 'pending';
            $officer_id = 1;
            $result = $stmt->execute([
                (int) $student_id,
                (int) $officer_id,
                (int) $category_id,
                (int) $subcategory_id,
                (string) $description,
                (string) $scheduled_datetime,
                (string) $evidence_image,
                (string) $status
            ]);

            if (!$result) {
                $error_info = $stmt->errorInfo();
                file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] EXECUTE ERROR: " . $error_info[2] . "\n", FILE_APPEND);
                return false;
            }

            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] SUCCESS: Appointment created\n", FILE_APPEND);
            return true;

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

    public function getAllAppointments($category_id = null, $status = null, $search = null) {
    try {
        $query = "SELECT a.*, o.name as officer_name
              FROM appointments a
              LEFT JOIN officers o ON a.officer_id = o.officer_id
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
                $query .= " AND (CAST(a.student_id AS TEXT) LIKE ? OR a.description LIKE ?)";
            } else {
                $query .= " AND (CAST(a.student_id AS NVARCHAR(50)) LIKE ? OR a.description LIKE ?)";
            }
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $query .= " ORDER BY a.scheduled_date DESC";

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
                        st.name as student_name,
                         st.email
                    FROM appointments a
                    JOIN appointment_categories c ON a.category_id = c.category_id
                    JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
                    JOIN students st ON a.student_id = st.student_id
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
                throw new Exception("Prepare failed: " . $this->conn->error);
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
                    st.name as student_name
                FROM appointments a
                JOIN appointment_categories c ON a.category_id = c.category_id
                JOIN appointment_subcategories s ON a.subcategory_id = s.subcategory_id
                JOIN students st ON a.student_id = st.student_id
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
                throw new Exception("Prepare failed: " . $this->conn->error);
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
            SELECT a.*, o.name as officer_name
            FROM appointments a
            LEFT JOIN officers o ON a.officer_id = o.officer_id
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
    public function updateAppointmentStatus($appointment_id, $status, $notes = null)
    {
        try {
            $query = "UPDATE appointments 
                      SET status = ?, updated_at = CURRENT_TIMESTAMP 
                      WHERE appointment_id = ?";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$status, $appointment_id]);
        } catch (Exception $e) {
            error_log("Exception in updateAppointmentStatus: " . $e->getMessage());
            return false;
        }
    }

    // Assign officer to appointment
    public function assignOfficer($appointment_id, $officer_id)
    {
        try {
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

    // Reschedule appointment
    public function rescheduleAppointment($appointment_id, $new_date)
    {
        try {
            $this->conn->beginTransaction();

            // Get current appointment
            $current = $this->getAppointmentById($appointment_id);

            // Update current to rescheduled
            $query = "UPDATE appointments 
                      SET status = 'rescheduled', scheduled_date = ?, updated_at = CURRENT_TIMESTAMP
                      WHERE appointment_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$new_date, $appointment_id]);

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

    // Check if time slot is available
    // Check if time slot is available
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
                throw new Exception("Prepare failed: " . $this->conn->error);
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
        $morning_slots = ['08:00', '09:00', '10:00', '11:00'];
        $afternoon_slots = ['13:00', '14:00', '15:00', '16:00'];

        $slots = array_merge($morning_slots, $afternoon_slots);

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
            $this->updateAppointmentStatus($appointment_id, 'cancelled');

            // Add cancellation reason
            $this->addReason($appointment_id, 'cancellation', $reason, $student_id);

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
            $this->updateAppointmentStatus($appointment_id, 'rejected');

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
}
?>