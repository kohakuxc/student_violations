<?php
/**
 * Officer Model
 * Handles all officer-related database operations
 */

class OfficerModel
{
    private $conn;

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    /**
     * Authenticate officer by username and password
     */
    public function authenticate($username, $password)
    {
        try {
            $query = "SELECT officer_id, name, password FROM officers WHERE username = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username]);

            $officer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($officer && password_verify($password, $officer['password'])) {
                return [
                    'success' => true,
                    'officer_id' => $officer['officer_id'],
                    'name' => $officer['name']
                ];
            }

            return ['success' => false, 'message' => 'Invalid credentials'];
        } catch (PDOException $e) {
            error_log("Authentication Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Get total count of violations
     */
    public function getTotalViolations()
    {
        try {
            $query = "SELECT COUNT(*) as total FROM violations";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['total'];
        } catch (PDOException $e) {
            error_log("Get Total Violations Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Monthly report (students): count students by highest severity for a given month.
     * Includes "none" = students with zero violations in that month.
     *
     * @param string $month YYYY-MM (e.g., 2026-03)
     * @return array ['none'=>int,'minor'=>int,'moderate'=>int,'major'=>int]
     */
    public function getMonthlyStudentSeverityCounts($month)
    {
        try {
            $startDate = $month . "-01";
            $endDate = date("Y-m-d", strtotime($startDate . " +1 month"));

            $query = "
                WITH monthly_max AS (
                    SELECT
                        s.student_id,
                        MAX(
                            CASE vt.severity_level
                                WHEN 'minor' THEN 1
                                WHEN 'major' THEN 2
                                ELSE 0
                            END
                        ) AS max_sev
                    FROM students s
                    LEFT JOIN violations v
                        ON v.student_id = s.student_id
                       AND v.date_of_violation >= ?
                       AND v.date_of_violation < ?
                    LEFT JOIN violation_types vt
                        ON vt.violation_type_id = v.violation_type
                    GROUP BY s.student_id
                )
                SELECT
                    SUM(CASE WHEN max_sev = 0 THEN 1 ELSE 0 END) AS none_count,
                    SUM(CASE WHEN max_sev = 1 THEN 1 ELSE 0 END) AS minor_count,
                    SUM(CASE WHEN max_sev = 2 THEN 1 ELSE 0 END) AS major_count
                FROM monthly_max;
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$startDate, $endDate]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'none' => (int) ($row['none_count'] ?? 0),
                'minor' => (int) ($row['minor_count'] ?? 0),
                'major' => (int) ($row['major_count'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log("Monthly Student Severity Report Error: " . $e->getMessage());
            return ['none' => 0, 'minor' => 0, 'major' => 0];
        }
    }

    /**
     * Get aggregated student severity counts across a date range.
     * Counts each student's highest severity violation within the date range.
     *
     * @param string $from_month (YYYY-MM format)
     * @param string $to_month (YYYY-MM format)
     * @return array ['none'=>int,'minor'=>int,'major'=>int]
     */
    public function getDateRangeStudentSeverityCounts($from_month, $to_month)
    {
        try {
            $startDate = $from_month . "-01";
            $endDate = date("Y-m-d", strtotime($to_month . "-01 +1 month"));

            $query = "
                WITH range_max AS (
                    SELECT
                        s.student_id,
                        MAX(
                            CASE vt.severity_level
                                WHEN 'minor' THEN 1
                                WHEN 'major' THEN 2
                                ELSE 0
                            END
                        ) AS max_sev
                    FROM students s
                    LEFT JOIN violations v
                        ON v.student_id = s.student_id
                       AND v.date_of_violation >= ?
                       AND v.date_of_violation < ?
                    LEFT JOIN violation_types vt
                        ON vt.violation_type_id = v.violation_type
                    GROUP BY s.student_id
                )
                SELECT
                    SUM(CASE WHEN max_sev = 0 THEN 1 ELSE 0 END) AS none_count,
                    SUM(CASE WHEN max_sev = 1 THEN 1 ELSE 0 END) AS minor_count,
                    SUM(CASE WHEN max_sev = 2 THEN 1 ELSE 0 END) AS major_count
                FROM range_max;
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$startDate, $endDate]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'none' => (int) ($row['none_count'] ?? 0),
                'minor' => (int) ($row['minor_count'] ?? 0),
                'major' => (int) ($row['major_count'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log("Date Range Student Severity Report Error: " . $e->getMessage());
            return ['none' => 0, 'minor' => 0, 'major' => 0];
        }
    }

    /**
     * Overall report (violations): count violation records by severity (all time).
     * Note: After violation type update, only 'minor' and 'major' severity levels exist.
     *
     * @return array ['minor'=>int,'major'=>int]
     */
    public function getOverallViolationCountsBySeverity()
    {
        try {
            $query = "
                SELECT
                    SUM(CASE WHEN vt.severity_level = 'minor' THEN 1 ELSE 0 END) AS minor_count,
                    SUM(CASE WHEN vt.severity_level = 'major' THEN 1 ELSE 0 END) AS major_count
                FROM violations v
                LEFT JOIN violation_types vt
                    ON vt.violation_type_id = v.violation_type
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'minor' => (int) ($row['minor_count'] ?? 0),
                'major' => (int) ($row['major_count'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log("Overall Violation Severity Report Error: " . $e->getMessage());
            return ['minor' => 0, 'major' => 0];
        }
    }

    /**
     * Get officer by ID
     */
    public function getOfficerById($officer_id)
    {
        try {
            $query = "SELECT officer_id, username, name FROM officers WHERE officer_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$officer_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Officer Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Yearly overview (students): for each month (Jan to Dec), count students by highest severity in that month.
     * Includes "none" = students with zero violations in that month.
     * Note: After violation type update, only 'minor' and 'major' severity levels exist.
     *
     * @param int $year (e.g., 2026)
     * @return array
     * [
     *   'labels' => ['Jan',...,'Dec'],
     *   'none' => [int...12],
     *   'minor' => [int...12],
     *   'major' => [int...12]
     * ]
     */
    public function getYearlyStudentSeverityCounts($year)
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $result = [
            'labels' => $labels,
            'none' => array_fill(0, 12, 0),
            'minor' => array_fill(0, 12, 0),
            'major' => array_fill(0, 12, 0),
        ];

        try {
            for ($m = 1; $m <= 12; $m++) {
                $month = sprintf('%04d-%02d', (int) $year, (int) $m);

                $counts = $this->getMonthlyStudentSeverityCounts($month);

                $idx = $m - 1;
                $result['none'][$idx] = (int) $counts['none'];
                $result['minor'][$idx] = (int) $counts['minor'];
                $result['major'][$idx] = (int) $counts['major'];
            }

            return $result;
        } catch (Exception $e) {
            error_log("Yearly Student Severity Overview Error: " . $e->getMessage());
            return $result;
        }
    }
}