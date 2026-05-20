<?php
// view/partials/layout_top.php
$pageTitle = $pageTitle ?? 'Violations Management System';
$styleVersion = @filemtime(__DIR__ . '/../../css/style.css') ?: time();
$appointmentsStyleVersion = @filemtime(__DIR__ . '/../../css/appointments.css') ?: time();

require_once __DIR__ . '/../../config/system_settings.php';
require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../model/NotificationModel.php';
require_once __DIR__ . '/../../model/MessageModel.php';
require_once __DIR__ . '/../../helper/CsrfHelper.php';
require_once __DIR__ . '/../../helper/AuthHelper.php';

$notifications = [];
$unreadCount = 0;
$officerMessageUnreadCount = 0;

if (isset($_SESSION['officer_id'])) {
    try {
        $notificationModel = new NotificationModel();
        $messageModel = new MessageModel();
        $notifications = $notificationModel->getRecentNotifications((int) $_SESSION['officer_id'], 12);
        $unreadCount = $notificationModel->getUnreadCount((int) $_SESSION['officer_id']);
        $officerMessageUnreadCount = $messageModel->getTotalUnreadCount((int) $_SESSION['officer_id'], 'officer');
    } catch (Exception $e) {
        $notifications = [];
        $unreadCount = 0;
        $officerMessageUnreadCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($pageTitle); ?> - Student Violations System</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />

    <!-- Your custom CSS -->
    <link rel="stylesheet" href="css/style.css?v=<?php echo urlencode((string) $styleVersion); ?>" />

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Appointment System CSS -->
    <link rel="stylesheet" href="css/appointments.css?v=<?php echo urlencode((string) $appointmentsStyleVersion); ?>" />

    <!-- FIX: Override excessive margins -->
    <style>
        .app-content {
            padding: 20px !important;
            margin: 0 !important;
            min-height: calc(100vh - 70px);
        }
        
        .container-fluid {
            margin: 0 !important;
            padding: 0 15px !important;
        }
        
        h2:first-of-type {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        .notification-btn {
            width: 50px;
            height: 50px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #0b5793;
            border-radius: 50%;
            cursor: pointer;
            transition-duration: .3s;
            border: none;
        }

        .notification-btn .bell {
            width: 18px;
        }

        .notification-btn .bell path {
            fill: #fff;
        }

        

        .notification-btn:hover .bell {
            animation: bellRing 0.9s both;
        }

        @keyframes bellRing {

            0%,
            100% {
                transform-origin: top;
            }

            15% {
                transform: rotateZ(10deg);
            }

            30% {
                transform: rotateZ(-10deg);
            }

            45% {
                transform: rotateZ(5deg);
            }

            60% {
                transform: rotateZ(-5deg);
            }

            75% {
                transform: rotateZ(2deg);
            }
        }

        .notification-btn:active {
            transform: scale(0.8);
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 19px;
            height: 19px;
            padding: 0 6px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #0b5793;
        }

        .notification-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .notification-modal.open {
            display: flex;
        }

        .notification-dialog {
            width: min(760px, 100%);
            max-height: 85vh;
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.25);
        }

        .notification-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .notification-header h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
        }

        .notification-close {
            border: 0;
            background: #f1f5f9;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            color: #334155;
        }

        .notification-close:hover {
            background: #e2e8f0;
        }

        .notification-body {
            padding: 16px 20px 20px;
            display: grid;
            gap: 18px;
        }

        .notification-section-title {
            margin: 0 0 8px;
            font-size: 0.95rem;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .notification-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 10px;
        }

        .notification-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .notification-item.unread {
            background: #eff6ff;
            border-color: #0b5793;
        }

        .notification-item.read {
            opacity: 0.75;
        }

        .notification-item-link {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            text-decoration: none;
            color: #0f172a;
            cursor: pointer;
        }

        .notification-item-link:hover {
            background: #eff6ff;
            border-radius: 10px;
        }

        .notification-item-icon {
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e0f2fe;
            border-radius: 8px;
            color: #0369a1;
            font-size: 14px;
        }

        .notification-item.unread .notification-item-icon {
            background: #0b5793;
            color: #fff;
        }

        .notification-item-content {
            flex: 1;
            min-width: 0;
        }

        .notification-item-title {
            display: block;
            font-weight: 700;
            font-size: 0.9rem;
            color: #0f172a;
            word-break: break-word;
        }

        .notification-item-meta {
            display: block;
            margin-top: 4px;
            font-size: 0.8rem;
            color: #64748b;
        }

        .notification-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .notification-action-btn {
            border: 0;
            background: #e0f2fe;
            color: #0369a1;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .notification-action-btn:hover {
            background: #0369a1;
            color: #fff;
        }

        .notification-empty {
            margin: 0;
            font-size: 0.92rem;
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 12px;
        }

        .sidebar-unread-badge {
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            background: #0b5793;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <button class="sidebar-hamburger" type="button" id="sidebarClose" aria-label="Close menu">
                    ☰
                </button>
            </div>

            <nav class="sidebar-nav">
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'dashboard' ? 'active' : ''; ?>" 
                   href="index.php?page=dashboard">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'add_violation' ? 'active' : ''; ?>" 
                   href="index.php?page=add_violation">
                    <i class="fas fa-plus-circle"></i> Add New Record
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'search_student' ? 'active' : ''; ?>" 
                   href="index.php?page=search_student">
                    <i class="fas fa-search"></i> Search Violations
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'all_violations' ? 'active' : ''; ?>" 
                   href="index.php?page=all_violations">
                    <i class="fas fa-list"></i> View All Violations
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'officer_appointments' ? 'active' : ''; ?>" 
                   href="index.php?page=officer_appointments">
                    <i class="fas fa-clipboard-list"></i> Appointments
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'messages' ? 'active' : ''; ?>" 
                   href="index.php?page=messages">
                    <i class="fas fa-comments"></i> Messages
                    <span class="sidebar-unread-badge" id="sidebarMessageBadgeOfficer" <?php echo $officerMessageUnreadCount > 0 ? '' : 'style="display:none;"'; ?>>
                        <?php echo (int) min($officerMessageUnreadCount, 99); ?>
                    </span>
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'settings' ? 'active' : ''; ?>" 
                   href="index.php?page=settings">
                    <i class="fas fa-gear"></i> Settings
                </a>
                <?php if (canImportExcel()): ?>
                    <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'import_violations' ? 'active' : ''; ?>" 
                       href="index.php?page=import_violations">
                        <i class="fas fa-file-excel"></i> Excel Import
                    </a>
                <?php endif; ?>
                <?php if (isSuperAdminUser()): ?>
                    <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'admin_management' ? 'active' : ''; ?>" 
                       href="index.php?page=admin_management">
                        <i class="fas fa-user-shield"></i> Superadmin
                    </a>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <div class="app-main">
            <header class="topbar" >
                <button class="sidebar-hamburger" type="button" id="sidebarToggle" aria-label="Toggle menu">
                    ☰
                </button>

                <a class="topbar-title" href="index.php?page=dashboard">Violations Management System</a>

                <div class="topbar-right">
                    <?php if (isset($_SESSION['officer_id'])): ?>
                        <button class="notification-btn" type="button" id="notificationBellBtn" aria-label="Open notifications">
                            <svg viewBox="0 0 448 512" class="bell" aria-hidden="true" focusable="false">
                                <path d="M224 0c-17.7 0-32 14.3-32 32V49.9C119.5 61.4 64 124.2 64 200v33.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V200c0-75.8-55.5-138.6-128-150.1V32c0-17.7-14.3-32-32-32zm0 96h8c57.4 0 104 46.6 104 104v33.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V200c0-57.4 46.6-104 104-104h8zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"></path>
                            </svg>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge" id="notificationBadge"><?php echo (int) min($unreadCount, 99); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['name'])): ?>
                        <span class="welcome-text"><b>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></b></span>
                    <?php endif; ?>
                    <a href="index.php?page=logout" class="btn btn-small btn-danger">Logout</a>
                </div>
            </header>

            <?php if (isset($_SESSION['officer_id'])): ?>
                <div class="notification-modal" id="notificationModal" aria-hidden="true">
                    <div class="notification-dialog" role="dialog" aria-modal="true" aria-label="Recent notifications">
                        <div class="notification-header">
                            <h5>Recent Notifications</h5>
                            <div class="notification-header-actions">
                                <button type="button" class="notification-action-btn" id="markAllReadBtn" title="Mark all as read">
                                    <i class="fas fa-check-double"></i>
                                </button>
                                <button type="button" class="notification-close" id="notificationCloseBtn" aria-label="Close notifications">&times;</button>
                            </div>
                        </div>
                        <div class="notification-body" id="notificationBodyContainer">
                            <div class="text-center" style="padding: 40px 20px;">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    (function () {
                        const bellBtn = document.getElementById('notificationBellBtn');
                        const modal = document.getElementById('notificationModal');
                        const closeBtn = document.getElementById('notificationCloseBtn');
                        const markAllReadBtn = document.getElementById('markAllReadBtn');
                        const notificationBadge = document.getElementById('notificationBadge');
                        const notificationBodyContainer = document.getElementById('notificationBodyContainer');

                        if (!bellBtn || !modal || !closeBtn) {
                            return;
                        }

                        // Helper to format relative time
                        function formatRelativeTime(secondsAgo) {
                            if (secondsAgo < 60) return 'just now';
                            if (secondsAgo < 3600) return Math.floor(secondsAgo / 60) + 'm ago';
                            if (secondsAgo < 86400) return Math.floor(secondsAgo / 3600) + 'h ago';
                            if (secondsAgo < 604800) return Math.floor(secondsAgo / 86400) + 'd ago';
                            return Math.floor(secondsAgo / 604800) + 'w ago';
                        }

                        // Helper to get notification icon
                        function getNotificationIcon(type) {
                            switch (type) {
                                case 'appointment_request': return 'fa-calendar-plus';
                                case 'appointment_approved': return 'fa-calendar-check';
                                case 'appointment_rejected': return 'fa-calendar-times';
                                case 'appointment_rescheduled': return 'fa-calendar-alt';
                                case 'appointment_completed': return 'fa-check-circle';
                                case 'settings_changed': return 'fa-cog';
                                default: return 'fa-bell';
                            }
                        }

                        // Render notifications
                        function renderNotifications(notifications) {
                            if (!Array.isArray(notifications) || notifications.length === 0) {
                                notificationBodyContainer.innerHTML = '<p class="notification-empty">No notifications</p>';
                                return;
                            }

                            const html = notifications.map(n => `
                                <div class="notification-item ${n.is_read ? 'read' : 'unread'}" data-id="${n.notification_id}">
                                    <a href="${escapeHtml(n.target_url || '#')}" class="notification-item-link">
                                        <div class="notification-item-icon">
                                            <i class="fas ${getNotificationIcon(n.notification_type)}"></i>
                                        </div>
                                        <div class="notification-item-content">
                                            <span class="notification-item-title">${escapeHtml(n.title || '')}</span>
                                            <span class="notification-item-meta">${formatRelativeTime(n.seconds_ago || 0)}</span>
                                        </div>
                                    </a>
                                </div>
                            `).join('');

                            notificationBodyContainer.innerHTML = html;

                            // Add click handlers to mark as read
                            notificationBodyContainer.querySelectorAll('.notification-item').forEach(item => {
                                item.addEventListener('click', function (e) {
                                    const id = this.dataset.id;
                                    if (!this.classList.contains('read')) {
                                        markNotificationAsRead(id);
                                    }
                                });
                            });
                        }

                        // Fetch notifications
                        function fetchNotifications() {
                            fetch('api/notifications.php?action=getRecent&limit=12')
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success) {
                                        renderNotifications(data.data);
                                        updateBadge(data.unread_count);
                                    }
                                })
                                .catch(err => console.error('Error fetching notifications:', err));
                        }

                        // Mark notification as read
                        function markNotificationAsRead(notificationId) {
                            fetch('api/notifications.php?action=markAsRead', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                },
                                body: 'notification_id=' + notificationId
                            })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success) {
                                        const item = document.querySelector('[data-id="' + notificationId + '"]');
                                        if (item) item.classList.add('read');
                                        updateBadge(data.unread_count);
                                    }
                                })
                                .catch(err => console.error('Error marking notification:', err));
                        }

                        // Mark all as read
                        function markAllAsRead() {
                            fetch('api/notifications.php?action=markAllAsRead', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                }
                            })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success) {
                                        document.querySelectorAll('.notification-item').forEach(item => item.classList.add('read'));
                                        updateBadge(0);
                                    }
                                })
                                .catch(err => console.error('Error marking all as read:', err));
                        }

                        // Update badge
                        function updateBadge(count) {
                            if (notificationBadge) {
                                if (count > 0) {
                                    notificationBadge.textContent = Math.min(count, 99);
                                    notificationBadge.style.display = 'inline-flex';
                                } else {
                                    notificationBadge.style.display = 'none';
                                }
                            }
                        }

                        // Modal controls
                        function openModal() {
                            modal.classList.add('open');
                            modal.setAttribute('aria-hidden', 'false');
                            fetchNotifications();
                        }

                        function closeModal() {
                            modal.classList.remove('open');
                            modal.setAttribute('aria-hidden', 'true');
                        }

                        // Event listeners
                        bellBtn.addEventListener('click', openModal);
                        closeBtn.addEventListener('click', closeModal);
                        if (markAllReadBtn) markAllReadBtn.addEventListener('click', markAllAsRead);

                        modal.addEventListener('click', function (event) {
                            if (event.target === modal) {
                                closeModal();
                            }
                        });

                        document.addEventListener('keydown', function (event) {
                            if (event.key === 'Escape' && modal.classList.contains('open')) {
                                closeModal();
                            }
                        });

                        // Real-time polling (every 30 seconds)
                        setInterval(() => {
                            if (modal.classList.contains('open')) {
                                fetchNotifications();
                            }
                        }, 30000);

                        const sidebarMessageBadge = document.getElementById('sidebarMessageBadgeOfficer');

                        function updateMessageSidebarBadge(count) {
                            if (!sidebarMessageBadge) {
                                return;
                            }

                            if (count > 0) {
                                sidebarMessageBadge.textContent = Math.min(count, 99);
                                sidebarMessageBadge.style.display = 'inline-flex';
                            } else {
                                sidebarMessageBadge.style.display = 'none';
                            }
                        }

                        function refreshMessageSidebarBadge() {
                            fetch('api/messages.php?action=getTotalUnreadCount')
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success) {
                                        updateMessageSidebarBadge(Number(data.unread_count || 0));
                                    }
                                })
                                .catch(() => { });
                        }

                        refreshMessageSidebarBadge();
                        setInterval(refreshMessageSidebarBadge, 15000);

                        // Helper to escape HTML
                        function escapeHtml(str) {
                            if (!str) return '';
                            return String(str)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                        }
                    })();
                </script>
            <?php endif; ?>

            <main class="app-content">
