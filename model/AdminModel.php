<?php
class AdminModel
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

    private function hashResetToken($token)
    {
        return hash('sha256', (string) $token);
    }

    public function getAdminByUsername($username)
    {
        $query = "SELECT officer_id, username, name, password, is_admin, is_superadmin, is_active, can_import_excel
                  FROM officers WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([(string) $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAdminById($officer_id)
    {
        $query = "SELECT officer_id, username, name, is_admin, is_superadmin, is_active, can_import_excel
                  FROM officers WHERE officer_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([(int) $officer_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllAdmins()
    {
        $query = "SELECT officer_id, username, name, is_admin, is_superadmin, is_active, can_import_excel, created_at
                  FROM officers
                  ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAdmin($username, $name, $password, $isAdmin = true, $isSuperadmin = false, $canImportExcel = false)
    {
        $hash = password_hash((string) $password, PASSWORD_BCRYPT);
        $query = "INSERT INTO officers (username, name, password, is_admin, is_superadmin, is_active, can_import_excel, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            (string) $username,
            (string) $name,
            (string) $hash,
            $this->isPgsql() ? (bool) $isAdmin : ($isAdmin ? 1 : 0),
            $this->isPgsql() ? (bool) $isSuperadmin : ($isSuperadmin ? 1 : 0),
            $this->isPgsql() ? true : 1,
            $this->isPgsql() ? (bool) $canImportExcel : ($canImportExcel ? 1 : 0),
        ]);
        if (!$result) {
            return null;
        }
        return (int) $this->conn->lastInsertId();
    }

    public function updateAdminStatus($officer_id, $isActive)
    {
        $query = "UPDATE officers
                  SET is_active = ?
                  WHERE officer_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $this->isPgsql() ? (bool) $isActive : ($isActive ? 1 : 0),
            (int) $officer_id,
        ]);
    }

    public function updateAdminPermissions($officer_id, $isAdmin, $isSuperadmin, $canImportExcel)
    {
        $query = "UPDATE officers
                  SET is_admin = ?, is_superadmin = ?, can_import_excel = ?
                  WHERE officer_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
                $isAdmin ? 1 : 0,
                $isSuperadmin ? 1 : 0,
                $canImportExcel ? 1 : 0,
            (int) $officer_id,
        ]);
    }

    public function updatePassword($officer_id, $password)
    {
        $hash = password_hash((string) $password, PASSWORD_BCRYPT);
        $query = "UPDATE officers
                  SET password = ?, password_updated_at = CURRENT_TIMESTAMP
                  WHERE officer_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([(string) $hash, (int) $officer_id]);
    }

    public function createPasswordResetToken($officer_id, $createdBy = null, $expiresInSeconds = 3600)
    {
        $token = bin2hex(random_bytes(32));
        $hash = $this->hashResetToken($token);
        $expiresAt = date('Y-m-d H:i:s', time() + (int) $expiresInSeconds);
        $query = "INSERT INTO admin_password_resets
                  (officer_id, token_hash, expires_at, created_by_officer_id, created_at)
                  VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            (int) $officer_id,
            (string) $hash,
            (string) $expiresAt,
            $createdBy !== null ? (int) $createdBy : null,
        ]);
        return $token;
    }

    public function findValidResetToken($token)
    {
        $hash = $this->hashResetToken($token);
        $query = $this->isPgsql()
            ? "SELECT reset_id, officer_id, expires_at, used_at
               FROM admin_password_resets
               WHERE token_hash = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP
               LIMIT 1"
            : "SELECT TOP 1 reset_id, officer_id, expires_at, used_at
               FROM admin_password_resets
               WHERE token_hash = ? AND used_at IS NULL AND expires_at > GETDATE()
               ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([(string) $hash]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markResetTokenUsed($reset_id)
    {
        $query = "UPDATE admin_password_resets
                  SET used_at = CURRENT_TIMESTAMP
                  WHERE reset_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([(int) $reset_id]);
    }
}
