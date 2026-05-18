<?php
require_once __DIR__ . '/../config/db_connection.php';

class NotificationModel
{
    private $conn;

    private function recipientColumn($recipientRole)
    {
        return $recipientRole === 'student' ? 'student_id' : 'officer_id';
    }

    private function resolveConnection()
    {
        global $conn;

        if ($conn instanceof PDO) {
            return $conn;
        }

        $dbFile = __DIR__ . '/../config/db_connection.php';
        $scopedConn = (static function ($file) {
            require $file;
            return (isset($conn) && $conn instanceof PDO) ? $conn : null;
        })($dbFile);

        if ($scopedConn instanceof PDO) {
            return $scopedConn;
        }

        throw new Exception('Database connection is not initialized.');
    }

    public function __construct()
    {
        $this->conn = $this->resolveConnection();
    }

    // Create a notification
    public function createNotification($officer_id, $notification_type, $title, $message = null, $target_id = null, $target_url = null)
    {
        return $this->createNotificationForRecipient('officer', $officer_id, $notification_type, $title, $message, $target_id, $target_url);
    }

    public function createNotificationForRecipient($recipientRole, $recipientId, $notification_type, $title, $message = null, $target_id = null, $target_url = null)
    {
        try {
            $recipientColumn = $this->recipientColumn($recipientRole);
            $query = "INSERT INTO notifications ({$recipientColumn}, notification_type, title, message, target_id, target_url, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                      RETURNING notification_id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                (int) $recipientId,
                (string) $notification_type,
                (string) $title,
                (string) ($message ?? ''),
                (int) ($target_id ?? null),
                (string) ($target_url ?? '')
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? (int) $result['notification_id'] : null;
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return null;
        }
    }

    // Get recent notifications for officer (unread first, with limit)
    public function getRecentNotifications($officer_id, $limit = 12)
    {
        return $this->getRecentNotificationsForRecipient('officer', $officer_id, $limit);
    }

    public function getRecentNotificationsForRecipient($recipientRole, $recipientId, $limit = 12, $allowedTypes = null)
    {
        try {
            $recipientColumn = $this->recipientColumn($recipientRole);
            $query = "SELECT notification_id, notification_type, title, message, target_id, target_url, 
                             is_read, read_at, created_at,
                             CAST(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - created_at)) AS INTEGER) as seconds_ago
                      FROM notifications
                      WHERE {$recipientColumn} = ?";

            $params = [(int) $recipientId];

            if (is_array($allowedTypes) && !empty($allowedTypes)) {
                $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
                $query .= " AND notification_type IN ({$placeholders})";
                foreach ($allowedTypes as $type) {
                    $params[] = (string) $type;
                }
            }

            $query .= " ORDER BY is_read ASC, created_at DESC LIMIT ?";
            $params[] = (int) $limit;

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent notifications: " . $e->getMessage());
            return [];
        }
    }

    // Get unread count for officer
    public function getUnreadCount($officer_id)
    {
        return $this->getUnreadCountForRecipient('officer', $officer_id);
    }

    public function getUnreadCountForRecipient($recipientRole, $recipientId, $allowedTypes = null)
    {
        try {
            $recipientColumn = $this->recipientColumn($recipientRole);
            $query = "SELECT COUNT(*) as unread_count FROM notifications 
                      WHERE {$recipientColumn} = ? AND is_read = false";

            $params = [(int) $recipientId];

            if (is_array($allowedTypes) && !empty($allowedTypes)) {
                $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
                $query .= " AND notification_type IN ({$placeholders})";
                foreach ($allowedTypes as $type) {
                    $params[] = (string) $type;
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['unread_count'] ?? 0);
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }

    // Mark notification as read
    public function markAsRead($notification_id, $officer_id)
    {
        return $this->markAsReadForRecipient('officer', $notification_id, $officer_id);
    }

    public function markAsReadForRecipient($recipientRole, $notification_id, $recipientId)
    {
        try {
            $recipientColumn = $this->recipientColumn($recipientRole);
            $query = "UPDATE notifications 
                      SET is_read = true, read_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE notification_id = ? AND {$recipientColumn} = ?";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([(int) $notification_id, (int) $recipientId]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    // Mark all notifications as read for officer
    public function markAllAsRead($officer_id)
    {
        return $this->markAllAsReadForRecipient('officer', $officer_id);
    }

    public function markAllAsReadForRecipient($recipientRole, $recipientId, $allowedTypes = null)
    {
        try {
            $recipientColumn = $this->recipientColumn($recipientRole);
            $query = "UPDATE notifications 
                      SET is_read = true, read_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE {$recipientColumn} = ? AND is_read = false";

            $params = [(int) $recipientId];

            if (is_array($allowedTypes) && !empty($allowedTypes)) {
                $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
                $query .= " AND notification_type IN ({$placeholders})";
                foreach ($allowedTypes as $type) {
                    $params[] = (string) $type;
                }
            }

            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    // Get only for admins - all officer changes
    public function getSystemSettingsNotifications($limit = 8)
    {
        try {
            $query = "SELECT notification_id, notification_type, title, message, target_id, target_url, 
                             is_read, read_at, created_at,
                             CAST(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - created_at)) AS INTEGER) as seconds_ago
                      FROM notifications
                      WHERE notification_type = 'settings_changed'
                      ORDER BY created_at DESC
                      LIMIT ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting system settings notifications: " . $e->getMessage());
            return [];
        }
    }

    // Get pending appointment notifications for officers
    public function getPendingAppointmentNotifications($limit = 8)
    {
        try {
            $query = "SELECT notification_id, notification_type, title, message, target_id, target_url, 
                             is_read, read_at, created_at,
                             CAST(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - created_at)) AS INTEGER) as seconds_ago
                      FROM notifications
                      WHERE notification_type LIKE 'appointment_%'
                      ORDER BY created_at DESC
                      LIMIT ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting appointment notifications: " . $e->getMessage());
            return [];
        }
    }

    // Delete old notifications (older than 90 days)
    public function deleteOldNotifications($days = 90)
    {
        try {
            $query = "DELETE FROM notifications 
                      WHERE created_at < CURRENT_TIMESTAMP - INTERVAL ? DAY";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([(int) $days]);
        } catch (Exception $e) {
            error_log("Error deleting old notifications: " . $e->getMessage());
            return false;
        }
    }

    // Check if admin can see all settings changes
    public function isAdmin($officer_id)
    {
        try {
            $query = "SELECT is_admin FROM officers WHERE officer_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $officer_id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (bool) ($result['is_admin'] ?? false);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
