<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['officer_id']) && !isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/MessageModel.php';
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/TextGuard.php';
require_once __DIR__ . '/../helper/RateLimiter.php';

$action = $_GET['action'] ?? null;
$messageModel = new MessageModel();

$writeActions = ['sendMessage', 'markConversationAsRead', 'getOrCreateConversation', 'archiveConversation'];
if (in_array($action, $writeActions, true)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    csrfRequireValidToken($token);
}

if (!($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

function ensureConversationParticipant(PDO $conn, $conversation_id, $user_id, $user_role)
{
    $query = "SELECT COUNT(*) AS cnt
              FROM conversation_participants
              WHERE conversation_id = ? AND user_id = ? AND user_role = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (((int) ($row['cnt'] ?? 0)) > 0) {
        return true;
    }

    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $insert = $conn->prepare(
            "INSERT INTO conversation_participants (conversation_id, user_id, user_role, created_at, updated_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON CONFLICT (conversation_id, user_id, user_role) DO NOTHING"
        );
        return $insert->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);
    }

    $insert = $conn->prepare(
        "INSERT INTO conversation_participants (conversation_id, user_id, user_role, created_at, updated_at)
         VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    );
    return $insert->execute([(int) $conversation_id, (int) $user_id, (string) $user_role]);
}

function userCanAccessConversation(PDO $conn, $conversation_id, $user_id, $user_role)
{
    $query = "SELECT student_id, officer_id, other_officer_id, conversation_kind
              FROM conversations
              WHERE conversation_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([(int) $conversation_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }

    if ($user_role === 'student' && (int) $row['student_id'] !== (int) $user_id) {
        return false;
    }
    if ($user_role === 'officer') {
        $matchesPrimaryOfficer = (int) $row['officer_id'] === (int) $user_id;
        $matchesSecondaryOfficer = (int) ($row['other_officer_id'] ?? 0) === (int) $user_id;
        if (!$matchesPrimaryOfficer && !$matchesSecondaryOfficer) {
            return false;
        }
    }

    if ($user_role === 'student' && (string) ($row['conversation_kind'] ?? 'student_officer') === 'officer_officer') {
        return false;
    }

    ensureConversationParticipant($conn, $conversation_id, $user_id, $user_role);
    return true;
}

function hasStudentOfficerAppointmentPair(PDO $conn, $student_id, $officer_id)
{
    $query = "SELECT COUNT(*) AS cnt
              FROM appointments
              WHERE student_id = ? AND officer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([(int) $student_id, (int) $officer_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ((int) ($row['cnt'] ?? 0)) > 0;
}

try {
    $user_id = $_SESSION['officer_id'] ?? $_SESSION['student_id'];
    $user_role = isset($_SESSION['officer_id']) ? 'officer' : 'student';

    switch ($action) {
        case 'getConversations':
            $limit = max(1, (int) ($_GET['limit'] ?? 20));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            $conversations = $messageModel->getUserConversations($user_id, $user_role, $limit, $offset);
            echo json_encode(['success' => true, 'data' => $conversations]);
            break;

        case 'getConversation':
            $conversation_id = (int) ($_GET['conversation_id'] ?? 0);
            if (!$conversation_id) {
                throw new Exception('Conversation ID required');
            }

            if (!userCanAccessConversation($conn, $conversation_id, $user_id, $user_role)) {
                throw new Exception('Unauthorized conversation access');
            }

            $limit = max(1, (int) ($_GET['limit'] ?? 50));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            $messages = $messageModel->getConversationMessages($conversation_id, $limit, $offset);
            echo json_encode(['success' => true, 'data' => $messages]);
            break;

        case 'sendMessage':
            $conversation_id = (int) ($_POST['conversation_id'] ?? 0);
            $message_body = trim((string) ($_POST['message_body'] ?? ''));
            $honeypot = trim((string) ($_POST['contact_website'] ?? ''));
            
            if ($honeypot !== '') {
                throw new Exception('Submission flagged as spam.');
            }

            if (!$conversation_id || empty($message_body)) {
                throw new Exception('Conversation ID and message body required');
            }

            if (!userCanAccessConversation($conn, $conversation_id, $user_id, $user_role)) {
                throw new Exception('Unauthorized conversation access');
            }

            if (!rateLimitCheck('message_send_' . $user_role . '_' . $user_id, 12, 60)) {
                throw new Exception('You are sending messages too quickly. Please wait a moment.');
            }

            $validation = validateFreeText($message_body, 2, 1000);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            $message_id = $messageModel->sendMessage(
                $conversation_id,
                $user_id,
                $user_role,
                $validation['value'],
                null,
                null,
                $validation['self_harm']
            );

            if (!$message_id) {
                throw new Exception('Failed to send message');
            }

            echo json_encode(['success' => true, 'message_id' => $message_id]);
            break;

        case 'markConversationAsRead':
            $conversation_id = (int) ($_POST['conversation_id'] ?? 0);
            if (!$conversation_id) {
                throw new Exception('Conversation ID required');
            }

            if (!userCanAccessConversation($conn, $conversation_id, $user_id, $user_role)) {
                throw new Exception('Unauthorized conversation access');
            }

            $result = $messageModel->markConversationAsRead($conversation_id, $user_id, $user_role);
            $unread_count = $messageModel->getTotalUnreadCount($user_id, $user_role);

            echo json_encode([
                'success' => $result,
                'unread_count' => $unread_count
            ]);
            break;

        case 'getTotalUnreadCount':
            $unread_count = $messageModel->getTotalUnreadCount($user_id, $user_role);
            echo json_encode([
                'success' => true,
                'unread_count' => $unread_count
            ]);
            break;

        case 'getAvailableOfficers':
            if ($user_role !== 'student') {
                throw new Exception('Only students can request available officers');
            }
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $query = "(SELECT officer_id, name FROM officers WHERE COALESCE(is_active, false) = true AND COALESCE(is_superadmin, false) = true LIMIT 1)
                          UNION ALL
                          (SELECT officer_id, name FROM officers WHERE COALESCE(is_active, false) = true AND COALESCE(is_superadmin, false) = false)
                          ORDER BY name ASC";
            } else {
                $query = "(SELECT TOP 1 officer_id, name FROM officers WHERE ISNULL(is_active, 0) = 1 AND ISNULL(is_superadmin, 0) = 1)
                          UNION ALL
                          (SELECT officer_id, name FROM officers WHERE ISNULL(is_active, 0) = 1 AND ISNULL(is_superadmin, 0) = 0)
                          ORDER BY name ASC";
            }
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $officers]);
            break;

        case 'getAvailableRecipients':
            if ($user_role !== 'officer') {
                throw new Exception('Only officers can request these recipients');
            }

            $recipient_type = (string) ($_GET['recipient_type'] ?? 'students');
            if (!in_array($recipient_type, ['students', 'admins'], true)) {
                throw new Exception('Invalid recipient type');
            }

            $recipients = $messageModel->getAvailableRecipients($recipient_type, $user_id);
            echo json_encode(['success' => true, 'data' => $recipients]);
            break;

        case 'getOrCreateConversation':
            $officer_id = (int) ($_POST['officer_id'] ?? 0);
            $student_id = (int) ($_POST['student_id'] ?? 0);
            $recipient_type = (string) ($_POST['recipient_type'] ?? 'officer');
            $recipient_id = (int) ($_POST['recipient_id'] ?? 0);

            if ($user_role === 'student') {
                if (!$officer_id || !$student_id) {
                    throw new Exception('Officer ID and student ID required');
                }

                $student_id = (int) $_SESSION['student_id'];
                $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'pgsql') {
                    $query = "SELECT COUNT(*) AS cnt FROM officers WHERE officer_id = ? AND COALESCE(is_active, false) = true";
                } else {
                    $query = "SELECT COUNT(*) AS cnt FROM officers WHERE officer_id = ? AND ISNULL(is_active, 0) = 1";
                }
                $stmt = $conn->prepare($query);
                $stmt->execute([(int) $officer_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (((int) ($row['cnt'] ?? 0)) === 0) {
                    throw new Exception('Invalid officer selection.');
                }

                $conversation_id = $messageModel->getOrCreateConversation($student_id, $officer_id, $user_role);
            } else {
                if (!in_array($recipient_type, ['student', 'officer'], true) || !$recipient_id) {
                    throw new Exception('Recipient type and recipient ID required');
                }

                if ($recipient_type === 'student') {
                    $query = "SELECT COUNT(*) AS cnt FROM students WHERE student_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([(int) $recipient_id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (((int) ($row['cnt'] ?? 0)) === 0) {
                        throw new Exception('Invalid student selection.');
                    }

                    $conversation_id = $messageModel->getOrCreateConversation($recipient_id, $user_id, 'officer');
                } else {
                    if ($recipient_id === (int) $user_id) {
                        throw new Exception('You cannot start a conversation with yourself.');
                    }

                    $query = "SELECT COUNT(*) AS cnt
                              FROM officers
                              WHERE officer_id = ?
                                AND COALESCE(is_active, false) = true
                                AND (COALESCE(is_admin, false) = true OR COALESCE(is_superadmin, false) = true)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([(int) $recipient_id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (((int) ($row['cnt'] ?? 0)) === 0) {
                        throw new Exception('Invalid admin or superadmin selection.');
                    }

                    $conversation_id = $messageModel->getOrCreateConversation(0, $user_id, 'officer', $recipient_id);
                }
            }

            if (!$conversation_id) {
                throw new Exception('Failed to get or create conversation');
            }

            echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
            break;

        case 'archiveConversation':
            $conversation_id = (int) ($_POST['conversation_id'] ?? 0);
            if (!$conversation_id) {
                throw new Exception('Conversation ID required');
            }

            if (!userCanAccessConversation($conn, $conversation_id, $user_id, $user_role)) {
                throw new Exception('Unauthorized conversation access');
            }

            $result = $messageModel->archiveConversation($conversation_id, $user_role);
            echo json_encode(['success' => $result]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>
