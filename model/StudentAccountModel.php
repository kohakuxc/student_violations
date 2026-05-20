<?php
class StudentAccountModel
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

    public function isEmailAllowed($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }
        $query = "SELECT is_enabled FROM student_accounts WHERE LOWER(email) = ? LIMIT 1";
        if (!$this->isPgsql()) {
            $query = "SELECT TOP 1 is_enabled FROM student_accounts WHERE LOWER(email) = ?";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row) && (bool) $row['is_enabled'];
    }

    public function getAccounts($limit = 200)
    {
        $query = "SELECT account_id, email, is_enabled, created_at, updated_at
                  FROM student_accounts
                  ORDER BY created_at DESC
                  LIMIT ?";
        if (!$this->isPgsql()) {
            $query = "SELECT TOP (?) account_id, email, is_enabled, created_at, updated_at
                      FROM student_accounts
                      ORDER BY created_at DESC";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->execute([(int) $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addOrEnableAccount($email, $actorId = null)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $existingQuery = "SELECT account_id FROM student_accounts WHERE LOWER(email) = ? LIMIT 1";
        if (!$this->isPgsql()) {
            $existingQuery = "SELECT TOP 1 account_id FROM student_accounts WHERE LOWER(email) = ?";
        }
        $stmt = $this->conn->prepare($existingQuery);
        $stmt->execute([$email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $this->conn->prepare("UPDATE student_accounts SET is_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE account_id = ?");
            $update->execute([
                $this->isPgsql() ? true : 1,
                (int) $existing['account_id']
            ]);
            return (int) $existing['account_id'];
        }

        $insert = $this->conn->prepare(
            "INSERT INTO student_accounts (email, is_enabled, created_by_officer_id, created_at, updated_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $insert->execute([
            $email,
            $this->isPgsql() ? true : 1,
            $actorId !== null ? (int) $actorId : null,
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function setAccountEnabled($accountId, $enabled)
    {
        $query = "UPDATE student_accounts
                  SET is_enabled = ?, updated_at = CURRENT_TIMESTAMP
                  WHERE account_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $this->isPgsql() ? (bool) $enabled : ($enabled ? 1 : 0),
            (int) $accountId
        ]);
    }
}
