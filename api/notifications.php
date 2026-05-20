<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['officer_id']) && !isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/NotificationModel.php';
require_once __DIR__ . '/../helper/CsrfHelper.php';

$action = $_GET['action'] ?? null;
$notificationModel = new NotificationModel();

$isOfficer = isset($_SESSION['officer_id']);
$recipientRole = $isOfficer ? 'officer' : 'student';
$recipientId = $isOfficer ? (int) $_SESSION['officer_id'] : (int) $_SESSION['student_id'];
$studentAllowedTypes = [
    'appointment_pending',
    'appointment_approved',
    'appointment_rejected',
    'appointment_rescheduled',
    'appointment_completed',
    'appointment_cancelled'
];

$allowedTypes = $isOfficer ? null : $studentAllowedTypes;
$writeActions = ['markAsRead', 'markAllAsRead'];
if (in_array($action, $writeActions, true)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    csrfRequireValidToken($token);
}

try {
    switch ($action) {
        case 'getRecent':
            $limit = max(1, (int) ($_GET['limit'] ?? 12));
            $notifications = $notificationModel->getRecentNotificationsForRecipient($recipientRole, $recipientId, $limit, $allowedTypes);
            $unread_count = $notificationModel->getUnreadCountForRecipient($recipientRole, $recipientId, $allowedTypes);

            echo json_encode([
                'success' => true,
                'data' => $notifications,
                'unread_count' => $unread_count
            ]);
            break;

        case 'markAsRead':
            $notification_id = (int) ($_POST['notification_id'] ?? 0);
            if (!$notification_id) {
                throw new Exception('Notification ID required');
            }

            $result = $notificationModel->markAsReadForRecipient($recipientRole, $notification_id, $recipientId);
            $unread_count = $notificationModel->getUnreadCountForRecipient($recipientRole, $recipientId, $allowedTypes);

            echo json_encode([
                'success' => $result,
                'unread_count' => $unread_count
            ]);
            break;

        case 'markAllAsRead':
            $result = $notificationModel->markAllAsReadForRecipient($recipientRole, $recipientId, $allowedTypes);
            echo json_encode([
                'success' => $result,
                'unread_count' => 0
            ]);
            break;

        case 'getUnreadCount':
            $unread_count = $notificationModel->getUnreadCountForRecipient($recipientRole, $recipientId, $allowedTypes);
            echo json_encode([
                'success' => true,
                'unread_count' => $unread_count
            ]);
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
