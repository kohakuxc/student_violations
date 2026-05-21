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

    .modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-backdrop.active {
        display: flex;
    }

    .modal-content {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 16px;
        color: #0f172a;
    }

    .officers-list {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 350px;
        overflow-y: auto;
    }

    .recipient-tabs {
        display: flex;
        gap: 8px;
        margin: 0 0 16px;
    }

    .recipient-tabs button {
        flex: 1;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #334155;
        border-radius: 999px;
        padding: 8px 10px;
        font-weight: 700;
        cursor: pointer;
    }

    .recipient-tabs button.active {
        background: #0b5793;
        color: #fff;
        border-color: #0b5793;
    }

    .officers-list li {
        padding: 10px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid #e5e7eb;
        margin-bottom: 8px;
    }

    .officers-list li:hover {
        background: #eff6ff;
        border-color: #0b5793;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .modal-actions button {
        flex: 1;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-weight: 600;
        cursor: pointer;
    }

    .modal-actions button.btn-close {
        background: #f1f5f9;
    }

    .modal-actions button.btn-close:hover {
        background: #e2e8f0;
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
            <span id="messageUnreadTotal" class="badge" style="background: #374151;">💬</span>
        </div>

        <div class="messages-actions">
            <button type="button" id="startConversationBtn" class="btn-start-chat">
                + New Conversation
            </button>
        </div>

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
            <input type="text" id="messageHoneypot" name="contact_website" value="" style="display:none" tabindex="-1" autocomplete="off">
            <textarea id="messageInput" placeholder="Type your message..." disabled></textarea>
            <button type="submit" id="sendBtn" disabled>Send</button>
        </form>
    </section>
</div>

<!-- New Conversation Modal -->
<div id="newConversationModal" class="modal-backdrop">
    <div class="modal-content">
        <div class="modal-header text-white" style="border-radius: 15px;">Start a New Conversation</div>
        <?php if ($isOfficer): ?>
            <div class="recipient-tabs" id="recipientTabs">
                <button style="border-radius: 15px;" type="button" class="active" data-recipient-type="students">Students</button>
                <button style="border-radius: 15px;" type="button" data-recipient-type="admins">Admins / Superadmins</button>
            </div>
        <?php endif; ?>
        <ul id="officersList" class="officers-list"></ul>
        <div class="modal-actions">
            <button type="button" class="btn-close" id="closeModalBtn">Cancel</button>
        </div>
    </div>
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
    const newConversationModal = document.getElementById('newConversationModal');
    const officersListEl = document.getElementById('officersList');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const recipientTabsEl = document.getElementById('recipientTabs');

    let conversations = [];
    let selectedConversationId = initiallySelectedConversationId || 0;
    let activeRecipientType = 'students';

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
            const title = c.conversation_title || (currentUserRole === 'officer' ? (c.student_name || ('Conversation #' + c.conversation_id)) : (c.officer_name || ('Conversation #' + c.conversation_id)));
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
            ? (selected.conversation_title || (currentUserRole === 'officer'
                ? (selected.student_name || ('Conversation #' + selected.conversation_id))
                : (selected.officer_name || ('Conversation #' + selected.conversation_id))))
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
            message_body: text,
            contact_website: document.getElementById('messageHoneypot')?.value || ''
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

    function setActiveRecipientTab(recipientType) {
        activeRecipientType = recipientType;
        if (recipientTabsEl) {
            recipientTabsEl.querySelectorAll('button').forEach(button => {
                button.classList.toggle('active', button.dataset.recipientType === recipientType);
            });
        }
    }

    async function loadRecipientList(recipientType) {
        const endpoint = currentUserRole === 'student'
            ? 'api/messages.php?action=getAvailableOfficers'
            : 'api/messages.php?action=getAvailableRecipients&recipient_type=' + encodeURIComponent(recipientType);

        const res = await fetch(endpoint);
        const data = await res.json();

        if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
            alert('No recipients available at this time.');
            return;
        }

        const recipients = data.data;
        officersListEl.innerHTML = recipients.map(recipient => {
            const recipientId = Number(recipient.officer_id || recipient.student_id || 0);
            return `
                <li data-recipient-id="${recipientId}">
                    ${escapeHtml(recipient.name || ('Recipient #' + recipientId))}
                </li>
            `;
        }).join('');

        officersListEl.querySelectorAll('li').forEach(item => {
            item.addEventListener('click', async function () {
                const recipientId = Number(this.dataset.recipientId || 0);
                if (recipientId <= 0) return;

                newConversationModal.classList.remove('active');
                await startConversationWithRecipient(recipientType, recipientId);
            });
        });

        newConversationModal.classList.add('active');
    }

    async function startConversation() {
        if (currentUserRole === 'student') {
            await loadRecipientList('officer');
            return;
        }

        setActiveRecipientTab('students');
        await loadRecipientList('students');
    }

    async function startConversationWithRecipient(recipientType, recipientId) {
        if (recipientId <= 0) {
            return;
        }

        const apiRecipientType = recipientType === 'students' ? 'student' : 'officer';
        const body = currentUserRole === 'student'
            ? new URLSearchParams({
                officer_id: String(recipientId),
                student_id: String(studentId)
            })
            : new URLSearchParams({
                recipient_type: apiRecipientType,
                recipient_id: String(recipientId)
            });

        try {
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
        } catch (err) {
            alert(err.message || 'Unable to start conversation right now.');
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
                alert('Unable to load officers right now.');
            });
        });
    }

    if (recipientTabsEl) {
        recipientTabsEl.querySelectorAll('button').forEach(button => {
            button.addEventListener('click', async function () {
                const recipientType = String(this.dataset.recipientType || 'students');
                setActiveRecipientTab(recipientType);
                await loadRecipientList(recipientType);
            });
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function () {
            newConversationModal.classList.remove('active');
        });
    }

    newConversationModal.addEventListener('click', function (e) {
        if (e.target === newConversationModal) {
            newConversationModal.classList.remove('active');
        }
    });

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
