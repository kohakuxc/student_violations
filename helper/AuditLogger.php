<?php
class AuditLogger
{
    private $conn;

    public function __construct()
    {
        include __DIR__ . '/../config/db_connection.php';
        $this->conn = $conn;
    }

    public function log($actorId, $actorRole, $actionType, $targetType = null, $targetId = null, array $metadata = [])
    {
        try {
            $query = "INSERT INTO audit_logs (actor_officer_id, actor_role, action_type, target_type, target_id, metadata, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                (int) $actorId,
                (string) $actorRole,
                (string) $actionType,
                $targetType !== null ? (string) $targetType : null,
                $targetId !== null ? (int) $targetId : null,
                json_encode($metadata, JSON_UNESCAPED_SLASHES),
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
}
