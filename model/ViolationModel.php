<?php
class ViolationModel
{
    private $conn;

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    public function addViolation($student_id, $officer_id, $violation_type, $description, $date_of_violation)
    {
        try {
            $query = "INSERT INTO violations (student_id, officer_id, violation_type, description, date_of_violation)
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$student_id, $officer_id, $violation_type, $description, $date_of_violation]);

            return $result
                ? ['success' => true, 'message' => 'Violation recorded successfully']
                : ['success' => false, 'message' => 'Failed to record violation'];

        } catch (PDOException $e) {
            error_log("Add Violation Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    public function getViolationsByStudent($student_id)
    {
        try {
            $query = "SELECT v.violation_id,
                             vt.type_name,
                             vt.severity_level,
                             v.description,
                             v.date_of_violation,
                             v.created_at,
                             o.name as officer_name
                      FROM violations v
                      JOIN officers o ON v.officer_id = o.officer_id
                      LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                      WHERE v.student_id = ?
                      ORDER BY v.date_of_violation DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get Violations by Student Error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllViolations()
    {
        try {
            $query = "SELECT v.violation_id,
                             vt.type_name,
                             vt.severity_level,
                             v.description,
                             v.date_of_violation,
                             v.created_at,
                         COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                             o.name as officer_name
                      FROM violations v
                      JOIN students s ON v.student_id = s.student_id
                     LEFT JOIN student_information si ON s.student_id = si.student_id
                      JOIN officers o ON v.officer_id = o.officer_id
                      LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                      ORDER BY v.created_at DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get All Violations Error: " . $e->getMessage());
            return [];
        }
    }

    public function getViolationCountByType($student_id)
    {
        try {
            // Now "violation_type" is a FK to violation_types.violation_type_id.
            // We count per severity_level.
            $query = "
            SELECT
                vt.severity_level,
                COUNT(*) as count
            FROM violations v
            LEFT JOIN violation_types vt
                ON vt.violation_type_id = v.violation_type
            WHERE v.student_id = ?
            GROUP BY vt.severity_level
            ORDER BY
                CASE vt.severity_level
                    WHEN 'major' THEN 1
                    WHEN 'moderate' THEN 2
                    WHEN 'minor' THEN 3
                    ELSE 4
                END
        ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Violation Count Error: " . $e->getMessage());
            return [];
        }
    }

    public function getRecentViolations($limit = 4)
    {
        try {
            $limit = (int) $limit;
            if ($limit < 1)
                $limit = 4;

            $driver = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'pgsql') {
                $query = "
                SELECT
                       v.violation_id,
                       v.description,
                       v.date_of_violation,
                       v.created_at,
                      COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                       o.name AS officer_name,
                       COALESCE(vt.severity_level, 'unknown') AS severity_level
                FROM violations v
                JOIN students s ON v.student_id = s.student_id
                  LEFT JOIN student_information si ON s.student_id = si.student_id
                JOIN officers o ON v.officer_id = o.officer_id
                LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                ORDER BY v.created_at DESC
                LIMIT $limit
            ";
            } else {
                $query = "
                SELECT
                       TOP ($limit)
                       v.violation_id,
                       v.description,
                       v.date_of_violation,
                       v.created_at,
                      COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                       o.name AS officer_name,
                       COALESCE(vt.severity_level, 'unknown') AS severity_level
                FROM violations v
                JOIN students s ON v.student_id = s.student_id
                  LEFT JOIN student_information si ON s.student_id = si.student_id
                JOIN officers o ON v.officer_id = o.officer_id
                LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                ORDER BY v.created_at DESC
            ";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get Recent Violations Error: " . $e->getMessage());
            return [];
        }
    }
}
?>