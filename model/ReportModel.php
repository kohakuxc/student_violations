<?php
class ReportModel
{
    private $conn;

    public function __construct()
    {
        include __DIR__ . '/../config/db_connection.php';
        $this->conn = $conn;
    }

    private function driverName()
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function isPgsql()
    {
        return $this->driverName() === 'pgsql';
    }

    public function createReport($studentId, $reportType, $description, $isSelfHarm = false)
    {
        $status = $isSelfHarm ? 'escalated' : 'new';
        $query = "INSERT INTO student_reports (student_id, report_type, description, status, is_self_harm, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            (int) $studentId,
            (string) $reportType,
            (string) $description,
            (string) $status,
            $this->isPgsql() ? (bool) $isSelfHarm : ($isSelfHarm ? 1 : 0),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function getReports($status = null)
    {
        $query = "SELECT r.report_id, r.student_id, r.report_type, r.description, r.status, r.is_self_harm,
                         r.created_at, r.updated_at,
                         COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), CAST(s.student_id AS VARCHAR(50))) AS student_name
                  FROM student_reports r
                  LEFT JOIN students s ON r.student_id = s.student_id
                  LEFT JOIN student_information si ON s.student_id = si.student_id";
        $params = [];
        if ($status) {
            $query .= " WHERE r.status = ?";
            $params[] = (string) $status;
        }
        $query .= " ORDER BY r.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportById($reportId)
    {
        $query = "SELECT * FROM student_reports WHERE report_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([(int) $reportId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateReportStatus($reportId, $status, $officerId)
    {
        $query = "UPDATE student_reports
                  SET status = ?, triaged_by_officer_id = ?, updated_at = CURRENT_TIMESTAMP
                  WHERE report_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            (string) $status,
            (int) $officerId,
            (int) $reportId
        ]);
    }
}
