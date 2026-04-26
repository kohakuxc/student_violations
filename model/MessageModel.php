<?php
require_once __DIR__ . '/../config/db_connection.php';

class MessageModel
{
    private $conn;

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

    // Get or create conversation between student and their assigned officer
    public function getOrCreateConversation($student_id, $officer_id, $initiated_by_role = 'student')
    {
        try {
            // Check if conversation exists
            $query = "SELECT conversation_id FROM conversations 
                      WHERE student_id = ? AND officer_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $student_id, (int) $officer_id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                return (int) $result['conversation_id'];
            }

            // Create new conversation
            $query = "INSERT INTO conversations (student_id, officer_id, initiated_by_role, created_at, updated_at)
                      VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                      RETURNING conversation_id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $student_id, (int) $officer_id, (string) $initiated_by_role]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                $conversation_id = (int) $result['conversation_id'];

                // Add participants
                $this->addParticipant($conversation_id, $student_id, 'student');
                $this->addParticipant($conversation_id, $officer_id, 'officer');

                return $conversation_id;
            }

            return null;
        } catch (Exception $e) {
            error_log("Error getting/creating conversation: " . $e->getMessage());
            return null;
        }
    }

    // Add participant to conversation
    private function addParticipant($conversation_id, $user_id, $user_role)
    {
        try {
            $query = "INSERT INTO conversation_participants (conversation_id, user_id, user_role, created_at, updated_at)
                      VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                      ON CONFLICT (conversation_id, user_id, user_role) DO NOTHING";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);
        } catch (Exception $e) {
            error_log("Error adding participant: " . $e->getMessage());
            return false;
        }
    }

    // Send message in conversation
    public function sendMessage($conversation_id, $sender_id, $sender_role, $message_body, $attachment_path = null, $attachment_filename = null)
    {
        try {
            $this->conn->beginTransaction();

            // Insert message
            $query = "INSERT INTO messages (conversation_id, sender_id, sender_role, message_body, attachment_path, attachment_filename, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                      RETURNING message_id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                (int) $conversation_id,
                (int) $sender_id,
                (string) $sender_role,
                (string) $message_body,
                (string) ($attachment_path ?? ''),
                (string) ($attachment_filename ?? '')
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $message_id = $result ? (int) $result['message_id'] : null;

            // Update conversation last_message_at
            $query = "UPDATE conversations 
                      SET last_message_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE conversation_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $conversation_id]);

            // Increment unread count for recipient
            $recipient_role = $sender_role === 'student' ? 'officer' : 'student';
            $query = "UPDATE conversation_participants 
                      SET unread_count = unread_count + 1, updated_at = CURRENT_TIMESTAMP
                      WHERE conversation_id = ? AND user_role = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $conversation_id, (string) $recipient_role]);

            $this->conn->commit();
            return $message_id;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error sending message: " . $e->getMessage());
            return null;
        }
    }

    // Get conversation messages
    public function getConversationMessages($conversation_id, $limit = 50, $offset = 0)
    {
        try {
            $query = "SELECT message_id, sender_id, sender_role, message_body, attachment_path, attachment_filename, 
                             is_read, read_at, created_at
                      FROM messages
                      WHERE conversation_id = ?
                      ORDER BY created_at DESC
                      LIMIT ? OFFSET ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $conversation_id, (int) $limit, (int) $offset]);
            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Reverse to get chronological order
            return array_reverse($messages);
        } catch (Exception $e) {
            error_log("Error getting conversation messages: " . $e->getMessage());
            return [];
        }
    }

    // Mark message as read
    public function markMessageAsRead($message_id)
    {
        try {
            $query = "UPDATE messages 
                      SET is_read = true, read_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE message_id = ?";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([(int) $message_id]);
        } catch (Exception $e) {
            error_log("Error marking message as read: " . $e->getMessage());
            return false;
        }
    }

    // Mark all messages in conversation as read by participant
    public function markConversationAsRead($conversation_id, $user_id, $user_role)
    {
        try {
            $this->conn->beginTransaction();

            // Mark all unread messages as read
            $query = "UPDATE messages 
                      SET is_read = true, read_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE conversation_id = ? AND is_read = false 
                      AND sender_role != ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $conversation_id, (string) $user_role]);

            // Reset unread count for participant
            $query = "UPDATE conversation_participants 
                      SET unread_count = 0, updated_at = CURRENT_TIMESTAMP
                      WHERE conversation_id = ? AND user_id = ? AND user_role = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error marking conversation as read: " . $e->getMessage());
            return false;
        }
    }

    // Get conversations for user
    public function getUserConversations($user_id, $user_role, $limit = 20, $offset = 0)
    {
        try {
            if ($user_role === 'student') {
                $query = "SELECT c.conversation_id, c.student_id, c.officer_id, c.subject, c.last_message_at, 
                                 c.is_archived_by_student, c.created_at,
                                 o.name as officer_name,
                                 COALESCE(cp.unread_count, 0) as unread_count
                          FROM conversations c
                          JOIN officers o ON c.officer_id = o.officer_id
                          LEFT JOIN conversation_participants cp ON c.conversation_id = cp.conversation_id 
                                 AND cp.user_id = ? AND cp.user_role = 'student'
                          WHERE c.student_id = ? AND c.is_archived_by_student = false
                          ORDER BY c.last_message_at DESC NULLS LAST, c.created_at DESC
                          LIMIT ? OFFSET ?";
            } else {
                $query = "SELECT c.conversation_id, c.student_id, c.officer_id, c.subject, c.last_message_at,
                                 c.is_archived_by_officer, c.created_at,
                                 COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), CAST(st.student_id AS VARCHAR(50))) as student_name,
                                 COALESCE(cp.unread_count, 0) as unread_count
                          FROM conversations c
                          JOIN students st ON c.student_id = st.student_id
                          LEFT JOIN student_information si ON st.student_id = si.student_id
                          LEFT JOIN conversation_participants cp ON c.conversation_id = cp.conversation_id 
                                 AND cp.user_id = ? AND cp.user_role = 'officer'
                          WHERE c.officer_id = ? AND c.is_archived_by_officer = false
                          ORDER BY c.last_message_at DESC NULLS LAST, c.created_at DESC
                          LIMIT ? OFFSET ?";
            }

            $stmt = $this->conn->prepare($query);
            $params = $user_role === 'student' 
                ? [(int) $user_id, (int) $user_id, (int) $limit, (int) $offset]
                : [(int) $user_id, (int) $user_id, (int) $limit, (int) $offset];

            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user conversations: " . $e->getMessage());
            return [];
        }
    }

    // Get total unread count for user
    public function getTotalUnreadCount($user_id, $user_role)
    {
        try {
            $query = "SELECT SUM(unread_count) as total_unread 
                      FROM conversation_participants 
                      WHERE user_id = ? AND user_role = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $user_id, (string) $user_role]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['total_unread'] ?? 0);
        } catch (Exception $e) {
            error_log("Error getting total unread count: " . $e->getMessage());
            return 0;
        }
    }

    // Archive conversation
    public function archiveConversation($conversation_id, $user_role)
    {
        try {
            $archive_column = $user_role === 'student' ? 'is_archived_by_student' : 'is_archived_by_officer';
            $query = "UPDATE conversations 
                      SET $archive_column = true, updated_at = CURRENT_TIMESTAMP
                      WHERE conversation_id = ?";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([(int) $conversation_id]);
        } catch (Exception $e) {
            error_log("Error archiving conversation: " . $e->getMessage());
            return false;
        }
    }
}
?>
