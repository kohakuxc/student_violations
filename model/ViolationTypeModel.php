<?php
/**
 * ViolationType Model
 * Handles violation type mapping (type name + severity level)
 */

class ViolationTypeModel
{
    private $conn;

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    public function getActiveViolationTypes()
    {
        try {
            $query = "SELECT violation_type_id, type_name, severity_level
                      FROM violation_types
                      WHERE is_active = true
                      ORDER BY severity_level, type_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Violation Types Error: " . $e->getMessage());
            return [];
        }
    }
}
?>