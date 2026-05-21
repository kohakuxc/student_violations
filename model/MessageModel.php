<?php
require_once __DIR__ . '/../config/db_connection.php';

class MessageModel
{
    private $conn;
    private $driverName;

    private function driverName()
    {
        if ($this->driverName === null) {
            $this->driverName = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        }
        return $this->driverName;
    }

    private function isPgsql()
    {
        return $this->driverName() === 'pgsql';
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

    public function getAvailableStudentsForConversation()
    {
        try {
            if ($this->isPgsql()) {
                $query = "SELECT s.student_id,
                                 COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), CAST(s.student_id AS VARCHAR(50))) as name,
                                 si.student_num,
                                 si.email
                          FROM students s
                          LEFT JOIN student_information si ON si.student_id = s.student_id
                          ORDER BY name ASC";
            } else {
                $query = "SELECT s.student_id,
                                 COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), CAST(s.student_id AS VARCHAR(50))) as name,
                                 si.student_num,
                                 si.email
                          FROM students s
                          LEFT JOIN student_information si ON si.student_id = s.student_id
                          ORDER BY name ASC";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(
                \PDO::FETCH_ASSOC
            );
        } catch (Exception $e) {
            error_log("Error getting available students: " . $e->getMessage());
            return [];
        }
    }

    public function getAvailableAdminRecipients($current_officer_id)
    {
        try {
            $query = "SELECT officer_id,
                             COALESCE(NULLIF(TRIM(COALESCE(name, '')), ''), COALESCE(username, CAST(officer_id AS VARCHAR(50)))) as name,
                             username,
                             is_admin,
                             is_superadmin
                      FROM officers
                      WHERE COALESCE(is_active, false) = true
                        AND (COALESCE(is_admin, false) = true OR COALESCE(is_superadmin, false) = true)
                        AND officer_id <> ?
                      ORDER BY COALESCE(is_superadmin, false) DESC,
                               COALESCE(is_admin, false) DESC,
                               name ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $current_officer_id]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting admin recipients: " . $e->getMessage());
            return [];
        }
    }

    public function getAvailableRecipients($recipient_type, $current_officer_id = null)
    {
        if ($recipient_type === 'students') {
            return $this->getAvailableStudentsForConversation();
        }

        if ($recipient_type === 'admins') {
            return $this->getAvailableAdminRecipients($current_officer_id ?? 0);
        }

        return [];
    }

    // Get or create conversation between student and their assigned officer
    public function getOrCreateConversation($student_id, $officer_id, $initiated_by_role = 'student', $other_officer_id = null)
    {
        try {
            $conversationKind = ($initiated_by_role === 'officer' && $other_officer_id !== null)
                ? 'officer_officer'
                : 'student_officer';

            if ($conversationKind === 'officer_officer') {
                $primaryOfficerId = min((int) $officer_id, (int) $other_officer_id);
                $secondaryOfficerId = max((int) $officer_id, (int) $other_officer_id);

                $query = "SELECT conversation_id FROM conversations
                          WHERE conversation_kind = 'officer_officer'
                            AND officer_id = ?
                            AND other_officer_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$primaryOfficerId, $secondaryOfficerId]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result) {
                    return (int) $result['conversation_id'];
                }

                $query = "INSERT INTO conversations (student_id, officer_id, other_officer_id, conversation_kind, initiated_by_role, created_at, updated_at)
                          VALUES (NULL, ?, ?, 'officer_officer', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$primaryOfficerId, $secondaryOfficerId, (string) $initiated_by_role]);
            } else {
                $query = "SELECT conversation_id FROM conversations
                          WHERE conversation_kind = 'student_officer'
                            AND student_id = ? AND officer_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([(int) $student_id, (int) $officer_id]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result) {
                    return (int) $result['conversation_id'];
                }

                $query = "INSERT INTO conversations (student_id, officer_id, other_officer_id, conversation_kind, initiated_by_role, created_at, updated_at)
                          VALUES (?, ?, NULL, 'student_officer', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

                $stmt = $this->conn->prepare($query);
                $stmt->execute([(int) $student_id, (int) $officer_id, (string) $initiated_by_role]);
            }

            if ($this->isPgsql()) {
                $conversation_id = (int) $this->conn->lastInsertId('conversations_conversation_id_seq');
            } else {
                $conversation_id = (int) $this->conn->lastInsertId();
            }

            if ($conversation_id > 0) {
                // Add participants
                if ($conversationKind === 'officer_officer') {
                    $this->addParticipant($conversation_id, $primaryOfficerId, 'officer');
                    $this->addParticipant($conversation_id, $secondaryOfficerId, 'officer');
                } else {
                    $this->addParticipant($conversation_id, $student_id, 'student');
                    $this->addParticipant($conversation_id, $officer_id, 'officer');
                }

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
            if ($this->isPgsql()) {
                $query = "INSERT INTO conversation_participants (conversation_id, user_id, user_role, created_at, updated_at)
                          VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                          ON CONFLICT (conversation_id, user_id, user_role) DO NOTHING";
                $stmt = $this->conn->prepare($query);
                return $stmt->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);
            }

            $check = $this->conn->prepare(
                "SELECT COUNT(*) AS cnt FROM conversation_participants WHERE conversation_id = ? AND user_id = ? AND user_role = ?"
            );
            $check->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);
            $exists = (int) ($check->fetchColumn() ?? 0);
            if ($exists > 0) {
                return true;
            }

            $stmt = $this->conn->prepare(
                "INSERT INTO conversation_participants (conversation_id, user_id, user_role, created_at, updated_at)
                 VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            return $stmt->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);
        } catch (Exception $e) {
            error_log("Error adding participant: " . $e->getMessage());
            return false;
        }
    }

    // Send message in conversation
    public function sendMessage($conversation_id, $sender_id, $sender_role, $message_body, $attachment_path = null, $attachment_filename = null, $is_self_harm = false)
    {
        try {
            $this->conn->beginTransaction();

            // Insert message
            $query = "INSERT INTO messages (conversation_id, sender_id, sender_role, message_body, attachment_path, attachment_filename, is_self_harm, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

            $stmt = $this->conn->prepare($query);
            $isSelfHarmParam = $is_self_harm ? 1 : 0;

            $stmt->execute([
                (int) $conversation_id,
                (int) $sender_id,
                (string) $sender_role,
                (string) $message_body,
                (string) ($attachment_path ?? ''),
                (string) ($attachment_filename ?? ''),
                $isSelfHarmParam,
            ]);

            if ($this->isPgsql()) {
                $message_id = (int) $this->conn->lastInsertId('messages_message_id_seq');
            } else {
                $message_id = (int) $this->conn->lastInsertId();
            }

            // Update conversation last_message_at
            $query = "UPDATE conversations 
                      SET last_message_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE conversation_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $conversation_id]);

                // Increment unread count for every participant except the sender.
            $query = "UPDATE conversation_participants 
                      SET unread_count = unread_count + 1, updated_at = CURRENT_TIMESTAMP
                      WHERE conversation_id = ? AND NOT (user_role = ? AND user_id = ?)";
            $stmt = $this->conn->prepare($query);
                $stmt->execute([(int) $conversation_id, (string) $sender_role, (int) $sender_id]);

            $this->conn->commit();
            return $message_id > 0 ? $message_id : null;
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
            if ($this->isPgsql()) {
                $query = "SELECT message_id, sender_id, sender_role, message_body, attachment_path, attachment_filename, 
                                 is_read, read_at, created_at
                          FROM messages
                          WHERE conversation_id = ?
                          ORDER BY created_at DESC
                          LIMIT ? OFFSET ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([(int) $conversation_id, (int) $limit, (int) $offset]);
            } else {
                $query = "SELECT message_id, sender_id, sender_role, message_body, attachment_path, attachment_filename,
                                 is_read, read_at, created_at
                          FROM messages
                          WHERE conversation_id = ?
                          ORDER BY created_at DESC
                          OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([(int) $conversation_id, (int) $offset, (int) $limit]);
            }
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
                      AND NOT (sender_role = ? AND sender_id = ?)";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int) $conversation_id, (string) $user_role, (int) $user_id]);

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
            $isStudent = $user_role === 'student';
            $baseSelect = $isStudent
                ? "SELECT c.conversation_id, c.student_id, c.officer_id, c.other_officer_id, c.conversation_kind, c.subject, c.last_message_at,
                          c.is_archived_by_student, c.created_at,
                          COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(o.name, ''), ''))), ''), CAST(c.officer_id AS VARCHAR(50))) as officer_name,
                          COALESCE(cp.unread_count, 0) as unread_count
                   FROM conversations c
                   LEFT JOIN officers o ON c.officer_id = o.officer_id
                   LEFT JOIN conversation_participants cp ON c.conversation_id = cp.conversation_id
                          AND cp.user_id = ? AND cp.user_role = 'student'
                   WHERE c.student_id = ? AND COALESCE(c.conversation_kind, 'student_officer') = 'student_officer' AND c.is_archived_by_student = false"
                : "SELECT c.conversation_id, c.student_id, c.officer_id, c.other_officer_id, c.conversation_kind, c.subject, c.last_message_at,
                          c.is_archived_by_officer, c.created_at,
                          COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(sti.last_name, ''), ', ', COALESCE(sti.first_name, '')))), ','), CAST(st.student_id AS VARCHAR(50))) as student_name,
                          COALESCE(NULLIF(TRIM(COALESCE(primary_o.name, '')), ''), COALESCE(primary_o.username, CAST(primary_o.officer_id AS VARCHAR(50)))) as officer_name,
                          COALESCE(NULLIF(TRIM(COALESCE(secondary_o.name, '')), ''), COALESCE(secondary_o.username, CAST(secondary_o.officer_id AS VARCHAR(50)))) as other_officer_name,
                          COALESCE(cp.unread_count, 0) as unread_count
                   FROM conversations c
                   LEFT JOIN students st ON c.student_id = st.student_id
                   LEFT JOIN student_information sti ON st.student_id = sti.student_id
                   LEFT JOIN officers primary_o ON c.officer_id = primary_o.officer_id
                   LEFT JOIN officers secondary_o ON c.other_officer_id = secondary_o.officer_id
                   LEFT JOIN conversation_participants cp ON c.conversation_id = cp.conversation_id
                          AND cp.user_id = ? AND cp.user_role = 'officer'
                   WHERE (c.officer_id = ? OR c.other_officer_id = ?) AND COALESCE(c.is_archived_by_officer, false) = false";

            if ($this->isPgsql()) {
                $query = $baseSelect . " ORDER BY c.last_message_at DESC NULLS LAST, c.created_at DESC LIMIT ? OFFSET ?";
                $stmt = $this->conn->prepare($query);
                if ($isStudent) {
                    $stmt->execute([(int) $user_id, (int) $user_id, (int) $limit, (int) $offset]);
                } else {
                    $stmt->execute([(int) $user_id, (int) $user_id, (int) $user_id, (int) $limit, (int) $offset]);
                }
            } else {
                $query = $baseSelect . " ORDER BY CASE WHEN c.last_message_at IS NULL THEN 1 ELSE 0 END,
                                          c.last_message_at DESC, c.created_at DESC
                                          OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                $stmt = $this->conn->prepare($query);
                if ($isStudent) {
                    $stmt->execute([(int) $user_id, (int) $user_id, (int) $offset, (int) $limit]);
                } else {
                    $stmt->execute([(int) $user_id, (int) $user_id, (int) $user_id, (int) $offset, (int) $limit]);
                }
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!$isStudent) {
                foreach ($rows as &$row) {
                    $kind = (string) ($row['conversation_kind'] ?? 'student_officer');
                    if ($kind === 'officer_officer') {
                        if ((int) ($row['officer_id'] ?? 0) === (int) $user_id) {
                            $row['conversation_title'] = (string) ($row['other_officer_name'] ?? $row['other_officer_id'] ?? 'Officer');
                            $row['counterpart_id'] = (int) ($row['other_officer_id'] ?? 0);
                        } else {
                            $row['conversation_title'] = (string) ($row['officer_name'] ?? $row['officer_id'] ?? 'Officer');
                            $row['counterpart_id'] = (int) ($row['officer_id'] ?? 0);
                        }
                        $row['counterpart_role'] = 'officer';
                    } else {
                        $row['conversation_title'] = (string) ($row['student_name'] ?? $row['student_id'] ?? 'Student');
                        $row['counterpart_id'] = (int) ($row['student_id'] ?? 0);
                        $row['counterpart_role'] = 'student';
                    }
                }
                unset($row);
            } else {
                foreach ($rows as &$row) {
                    $row['conversation_title'] = (string) ($row['officer_name'] ?? $row['officer_id'] ?? 'Officer');
                    $row['counterpart_id'] = (int) ($row['officer_id'] ?? 0);
                    $row['counterpart_role'] = 'officer';
                }
                unset($row);
            }

            return $rows;
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
