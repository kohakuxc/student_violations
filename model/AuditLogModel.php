<?php
class AuditLogModel
{
    private $conn;

    public function __construct()
    {
        include __DIR__ . '/../config/db_connection.php';
        $this->conn = $conn;
    }

    public function getRecentLogs($limit = 50)
    {
        $query = "SELECT audit_id, actor_officer_id, actor_role, action_type, target_type, target_id, metadata, created_at
                  FROM audit_logs
                  ORDER BY created_at DESC
                  LIMIT ?";
        if ($this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            $query = "SELECT TOP (?) audit_id, actor_officer_id, actor_role, action_type, target_type, target_id, metadata, created_at
                      FROM audit_logs
                      ORDER BY created_at DESC";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->execute([(int) $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
