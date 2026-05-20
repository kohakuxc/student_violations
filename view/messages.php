<?php
if (!isset($_SESSION['officer_id']) && !isset($_SESSION['student_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once __DIR__ . '/../model/MessageModel.php';
require_once __DIR__ . '/../model/AppointmentModel.php';

$isOfficer = isset($_SESSION['officer_id']);
$userRole = $isOfficer ? 'officer' : 'student';
$userId = $isOfficer ? (int) $_SESSION['officer_id'] : (int) $_SESSION['student_id'];

$pageTitle = 'Messages';
if ($isOfficer) {
    include __DIR__ . '/partials/layout_top.php';
} else {
    include __DIR__ . '/partials/student_layout_top.php';
}

$messageModel = new MessageModel();
$appointmentModel = new AppointmentModel();
$conversations = $messageModel->getUserConversations($userId, $userRole, 50, 0);

$selectedConversationId = (int) ($_GET['conversation_id'] ?? 0);
if ($selectedConversationId <= 0 && !empty($conversations)) {
    $selectedConversationId = (int) $conversations[0]['conversation_id'];
}

$assignedOfficerId = 0;
$assignedOfficerName = '';
if (!$isOfficer) {
    $appointments = $appointmentModel->getStudentAppointments($userId);
    foreach ($appointments as $appointment) {
        if (!empty($appointment['officer_id'])) {
            $assignedOfficerId = (int) $appointment['officer_id'];
            $assignedOfficerName = (string) ($appointment['officer_name'] ?? 'Assigned Officer');
            break;
        }
    }
}
?>

<style>
    .messages-page {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 16px;
        min-height: calc(100vh - 160px);
    }

    .messages-panel,
    .chat-panel {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
    }

    .messages-header,
    .chat-header {
        padding: 14px 16px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .messages-header h3,
    .chat-header h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .conversation-list {
        list-style: none;
        margin: 0;
        padding: 8px;
        display: grid;
        gap: 8px;
        max-height: calc(100vh - 260px);
        overflow: auto;
    }

    .conversation-item {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .conversation-item:hover {
        border-color: #0b5793;
        background: #eff6ff;
    }

    .conversation-item.active {
        border-color: #0b5793;
        background: #dbeafe;
    }

    .conversation-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        font-weight: 700;
        color: #0f172a;
        font-size: 0.92rem;
    }

    .unread-pill {
        min-width: 22px;
        height: 22px;
        border-radius: 999px;
        background: #0b5793;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0 6px;
    }

    .conversation-meta {
        margin-top: 6px;
        font-size: 0.78rem;
        color: #64748b;
    }

    .chat-body {
        height: calc(100vh - 330px);
        overflow: auto;
        padding: 12px;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        display: grid;
        gap: 10px;
        align-content: start;
    }

    .chat-empty {
        color: #64748b;
        font-size: 0.95rem;
        text-align: center;
        margin-top: 40px;
    }

    .msg {
        max-width: 78%;
        padding: 10px 12px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }

    .msg.mine {
        justify-self: end;
        background: #0b5793;
        color: #fff;
        border-bottom-right-radius: 4px;
    }

    .msg.theirs {
        justify-self: start;
        background: #fff;
        color: #0f172a;
        border-bottom-left-radius: 4px;
    }

    .msg-time {
        margin-top: 5px;
        font-size: 0.72rem;
        opacity: 0.8;
    }

    .chat-compose {
        border-top: 1px solid #e5e7eb;
        padding: 12px;
        display: flex;
        gap: 10px;
    }

    .chat-compose textarea {
        flex: 1;
        resize: none;
        min-height: 42px;
        max-height: 110px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 0.92rem;
    }

    .chat-compose button {
        border: 0;
        border-radius: 10px;
        background: #0b5793;
        color: #fff;
        font-weight: 700;
        padding: 0 16px;
    }

    .messages-actions {
        padding: 8px 12px;
    }

    .btn-start-chat {
        width: 100%;
        border: 1px dashed #0b5793;
        color: #0b5793;
        background: #eff6ff;
        border-radius: 10px;
        padding: 8px 10px;
        font-weight: 700;
        cursor: pointer;
    }

    @media (max-width: 980px) {
        .messages-page {
            grid-template-columns: 1fr;
        }

        .chat-body {
            height: 360px;
        }

        .conversation-list {
            max-height: 280px;
        }
    }
</style>

<div class="messages-page">
    <section class="messages-panel">
        <div class="messages-header">
            <h3>Conversations</h3>
            <span id="messageUnreadTotal" class="badge bg-primary">0</span>
        </div>

        <?php if (!$isOfficer && $assignedOfficerId > 0): ?>
            <div class="messages-actions">
                <button type="button" id="startConversationBtn" class="btn-start-chat">
                    Start chat with <?php echo htmlspecialchars($assignedOfficerName ?: 'Assigned Officer'); ?>
                </button>
            </div>
        <?php endif; ?>

        <ul id="conversationList" class="conversation-list"></ul>
    </section>

    <section class="chat-panel">
        <div class="chat-header">
            <h3 id="chatTitle">Select a conversation</h3>
            <small id="chatMeta" style="color:#64748b"></small>
        </div>
        <div id="chatBody" class="chat-body">
            <div class="chat-empty">No messages yet.</div>
        </div>
        <form id="chatForm" class="chat-compose">
            <textarea id="messageInput" placeholder="Type your message..." disabled></textarea>
            <button type="submit" id="sendBtn" disabled>Send</button>
        </form>
    </section>
</div>

<script>
(function () {
    const currentUserId = <?php echo (int) $userId; ?>;
    const currentUserRole = <?php echo json_encode($userRole); ?>;
    const studentId = <?php echo (int) ($isOfficer ? 0 : $userId); ?>;
    const assignedOfficerId = <?php echo (int) $assignedOfficerId; ?>;
    const initiallySelectedConversationId = <?php echo (int) $selectedConversationId; ?>;

    const conversationListEl = document.getElementById('conversationList');
    const chatBodyEl = document.getElementById('chatBody');
    const chatFormEl = document.getElementById('chatForm');
    const messageInputEl = document.getElementById('messageInput');
    const sendBtnEl = document.getElementById('sendBtn');
    const chatTitleEl = document.getElementById('chatTitle');
    const chatMetaEl = document.getElementById('chatMeta');
    const unreadTotalEl = document.getElementById('messageUnreadTotal');
    const startConversationBtn = document.getElementById('startConversationBtn');

    let conversations = [];
    let selectedConversationId = initiallySelectedConversationId || 0;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function formatDate(input) {
        if (!input) return '';
        const d = new Date(input);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleString();
    }

    function totalUnread(items) {
        return items.reduce((sum, c) => sum + Number(c.unread_count || 0), 0);
    }

    function renderConversations() {
        if (!conversations.length) {
            conversationListEl.innerHTML = '<li class="chat-empty">No conversations yet.</li>';
            unreadTotalEl.textContent = '0';
            return;
        }

        unreadTotalEl.textContent = String(totalUnread(conversations));

        conversationListEl.innerHTML = conversations.map(c => {
            const title = currentUserRole === 'officer' ? (c.student_name || ('Student #' + c.student_id)) : (c.officer_name || ('Officer #' + c.officer_id));
            const unread = Number(c.unread_count || 0);
            return `
                <li class="conversation-item ${Number(c.conversation_id) === Number(selectedConversationId) ? 'active' : ''}" data-id="${Number(c.conversation_id)}">
                    <div class="conversation-title">
                        <span>${escapeHtml(title)}</span>
                        ${unread > 0 ? `<span class="unread-pill">${Math.min(unread, 99)}</span>` : ''}
                    </div>
                    <div class="conversation-meta">${escapeHtml(formatDate(c.last_message_at || c.created_at))}</div>
                </li>
            `;
        }).join('');

        conversationListEl.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', function () {
                const id = Number(this.dataset.id || 0);
                if (id > 0) {
                    openConversation(id);
                }
            });
        });
    }

    async function fetchConversations() {
        const res = await fetch('api/messages.php?action=getConversations&limit=50');
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to load conversations');
        }

        conversations = Array.isArray(data.data) ? data.data : [];

        if (!selectedConversationId && conversations.length > 0) {
            selectedConversationId = Number(conversations[0].conversation_id || 0);
        }

        renderConversations();
    }

    async function fetchMessages(conversationId) {
        const res = await fetch('api/messages.php?action=getConversation&conversation_id=' + encodeURIComponent(conversationId));
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to load messages');
        }

        const messages = Array.isArray(data.data) ? data.data : [];
        if (!messages.length) {
            chatBodyEl.innerHTML = '<div class="chat-empty">No messages yet. Start the conversation.</div>';
            return;
        }

        chatBodyEl.innerHTML = messages.map(m => {
            const mine = m.sender_role === currentUserRole;
            return `
                <div class="msg ${mine ? 'mine' : 'theirs'}">
                    <div>${escapeHtml(m.message_body || '')}</div>
                    <div class="msg-time">${escapeHtml(formatDate(m.created_at))}</div>
                </div>
            `;
        }).join('');

        chatBodyEl.scrollTop = chatBodyEl.scrollHeight;
    }

    async function markConversationAsRead(conversationId) {
        const body = new URLSearchParams({ conversation_id: String(conversationId) });
        await fetch('api/messages.php?action=markConversationAsRead', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: body.toString()
        });
    }

    async function openConversation(conversationId) {
        selectedConversationId = Number(conversationId || 0);

        const selected = conversations.find(c => Number(c.conversation_id) === selectedConversationId);
        const title = selected
            ? (currentUserRole === 'officer'
                ? (selected.student_name || ('Student #' + selected.student_id))
                : (selected.officer_name || ('Officer #' + selected.officer_id)))
            : 'Conversation';

        chatTitleEl.textContent = title;
        chatMetaEl.textContent = selected && selected.last_message_at ? 'Last activity: ' + formatDate(selected.last_message_at) : '';

        messageInputEl.disabled = false;
        sendBtnEl.disabled = false;

        await fetchMessages(selectedConversationId);
        await markConversationAsRead(selectedConversationId);
        await fetchConversations();
    }

    async function sendMessage(text) {
        if (!window.confirm('Send this message?')) {
            return;
        }
        const body = new URLSearchParams({
            conversation_id: String(selectedConversationId),
            message_body: text
        });

        const res = await fetch('api/messages.php?action=sendMessage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: body.toString()
        });
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to send message');
        }
    }

    async function startConversation() {
        if (currentUserRole !== 'student' || assignedOfficerId <= 0 || studentId <= 0) {
            return;
        }
        if (!window.confirm('Start a new conversation with your assigned officer?')) {
            return;
        }

        const body = new URLSearchParams({
            officer_id: String(assignedOfficerId),
            student_id: String(studentId)
        });

        const res = await fetch('api/messages.php?action=getOrCreateConversation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: body.toString()
        });
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Unable to start conversation right now.');
            return;
        }

        selectedConversationId = Number(data.conversation_id || 0);
        await fetchConversations();
        if (selectedConversationId > 0) {
            await openConversation(selectedConversationId);
        }
    }

    chatFormEl.addEventListener('submit', async function (e) {
        e.preventDefault();
        const text = messageInputEl.value.trim();
        if (!text || selectedConversationId <= 0) {
            return;
        }

        sendBtnEl.disabled = true;
        try {
            await sendMessage(text);
            messageInputEl.value = '';
            await fetchConversations();
            await fetchMessages(selectedConversationId);
        } catch (err) {
            alert(err.message || 'Failed to send message');
        } finally {
            sendBtnEl.disabled = false;
        }
    });

    if (startConversationBtn) {
        startConversationBtn.addEventListener('click', function () {
            startConversation().catch(() => {
                alert('Unable to start conversation right now.');
            });
        });
    }

    async function init() {
        try {
            await fetchConversations();
            if (selectedConversationId > 0) {
                await openConversation(selectedConversationId);
            }
        } catch (err) {
            conversationListEl.innerHTML = '<li class="chat-empty">Unable to load messages right now.</li>';
            chatBodyEl.innerHTML = '<div class="chat-empty">Unable to load messages right now.</div>';
        }
    }

    init();

    setInterval(async function () {
        try {
            await fetchConversations();
            if (selectedConversationId > 0) {
                await fetchMessages(selectedConversationId);
            }
        } catch (err) {
            // silent polling errors
        }
    }, 15000);
})();
</script>

<?php
if ($isOfficer) {
    include __DIR__ . '/partials/layout_bottom.php';
} else {
    include __DIR__ . '/partials/student_layout_bottom.php';
}
?>
