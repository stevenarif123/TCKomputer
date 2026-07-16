<?php
/**
 * Admin Panel Live Chat Management
 * Main chat interface for admins to see, search, and reply to client chats.
 */
$pageTitle = "Live Chat";
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h2>Live Chat Pelanggan</h2>
</div>

<div class="grid-2col-asym-rev chat-layout-grid">
    <!-- Left Column: Sesi Chat -->
    <div class="admin-card chat-sidebar-card">
        <div class="chat-sessions-header">
            <div class="search-wrapper">
                <span class="material-symbols-outlined search-icon">search</span>
                <input type="text" id="session-search" placeholder="Cari nama / HP..." class="form-input">
            </div>
            <div class="chat-filter-tabs">
                <button class="filter-tab active" data-status="active">Aktif</button>
                <button class="filter-tab" data-status="closed">Ditutup</button>
                <button class="filter-tab" data-status="">Semua</button>
            </div>
        </div>
        <div id="sessions-container" class="sessions-scrollable">
            <div class="chat-loading">Memuat sesi chat...</div>
        </div>
    </div>

    <!-- Right Column: Percakapan Aktif -->
    <div class="admin-card chat-main-card" id="conversation-panel">
        <div id="conversation-empty" class="conversation-empty">
            <span class="material-symbols-outlined" style="font-size: 64px; color: var(--admin-border);">chat</span>
            <p style="margin-top: 15px; color: var(--admin-text-muted); font-weight: 500;">Pilih percakapan di sebelah kiri untuk mulai membaca dan membalas pesan.</p>
        </div>
        
        <div id="conversation-active" class="conversation-active hidden">
            <!-- Header Percakapan -->
            <div class="conversation-header">
                <div class="user-meta">
                    <div class="user-avatar" id="active-user-avatar">?</div>
                    <div>
                        <div class="user-title-row">
                            <h4 id="active-user-name">Nama Pelanggan</h4>
                            <span id="active-user-reg-badge" class="badge">Anonim</span>
                        </div>
                        <div class="user-sub-row">
                            <span id="active-user-phone">08xxxxx</span>
                            <span class="bullet-separator">•</span>
                            <span id="active-user-email" class="user-email-text">email@domain.com</span>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="btn-close-session" class="btn btn-sm btn-danger">
                        <span class="material-symbols-outlined" style="font-size: 16px;">cancel</span> Tutup Chat
                    </button>
                </div>
            </div>

            <!-- Log Pesan -->
            <div class="conversation-messages" id="conversation-messages">
                <!-- Pesan di-render via JS -->
            </div>

            <!-- Quick Replies -->
            <div class="quick-replies-container">
                <span class="quick-replies-label">Balasan Cepat:</span>
                <div class="quick-replies" id="quick-replies-list">
                    <button class="btn btn-outline btn-sm quick-reply-btn">Halo, ada yang bisa kami bantu?</button>
                    <button class="btn btn-outline btn-sm quick-reply-btn">Pesanan Anda sedang kami proses.</button>
                    <button class="btn btn-outline btn-sm quick-reply-btn">Mohon tunggu sebentar, kami cek.</button>
                    <button class="btn btn-outline btn-sm quick-reply-btn">Terima kasih!</button>
                </div>
            </div>

            <!-- Input Balasan -->
            <div class="conversation-input-area">
                <textarea id="chat-reply-input" class="form-textarea" placeholder="Tulis balasan... (Ctrl+Enter untuk kirim)" rows="1" style="min-height: 44px; padding-top: 11px;"></textarea>
                <button id="chat-reply-send" class="btn btn-primary" style="height: 44px; width: 44px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: var(--admin-radius-sm);">
                    <span class="material-symbols-outlined">send</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let activeSessionId = null;
    let lastMessageId = 0;
    let pollInterval = null;
    let listInterval = null;
    let currentFilterStatus = 'active';
    let searchQuery = '';

    const sessionsContainer = document.getElementById('sessions-container');
    const conversationEmpty = document.getElementById('conversation-empty');
    const conversationActive = document.getElementById('conversation-active');
    const activeUserName = document.getElementById('active-user-name');
    const activeUserAvatar = document.getElementById('active-user-avatar');
    const activeUserRegBadge = document.getElementById('active-user-reg-badge');
    const activeUserPhone = document.getElementById('active-user-phone');
    const activeUserEmail = document.getElementById('active-user-email');
    const conversationMessages = document.getElementById('conversation-messages');
    const chatReplyInput = document.getElementById('chat-reply-input');
    const chatReplySend = document.getElementById('chat-reply-send');
    const btnCloseSession = document.getElementById('btn-close-session');
    const sessionSearch = document.getElementById('session-search');
    const filterTabs = document.querySelectorAll('.filter-tab');

    // Request Notification permission
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Load session list on start
    fetchSessions();
    listInterval = setInterval(fetchSessions, 4000);

    // Search filter
    sessionSearch.addEventListener('input', (e) => {
        searchQuery = e.target.value.trim();
        fetchSessions();
    });

    // Tab filter status
    filterTabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            filterTabs.forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            currentFilterStatus = e.target.getAttribute('data-status');
            fetchSessions();
        });
    });

    // Fetch sessions list
    async function fetchSessions() {
        try {
            const res = await fetch(`chat-api.php?action=sessions&status=${currentFilterStatus}&search=${encodeURIComponent(searchQuery)}`);
            const data = await res.json();

            if (data.success) {
                renderSessionsList(data.sessions);
            }
        } catch (err) {
            console.error('Error fetching sessions:', err);
        }
    }

    // Play notification tone
    function playNotificationSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 830; // Hz beep
            osc.type = 'sine';
            gain.gain.value = 0.25;
            osc.start();
            osc.stop(ctx.currentTime + 0.15); // 150ms length
        } catch (e) {
            console.error('AudioContext play error:', e);
        }
    }

    // Render Sessions List
    function renderSessionsList(sessions) {
        if (sessions.length === 0) {
            sessionsContainer.innerHTML = '<div class="chat-empty-state">Tidak ada sesi chat.</div>';
            return;
        }

        let html = '';
        let totalUnread = 0;
        
        sessions.forEach(session => {
            const isActive = session.id == activeSessionId ? 'active' : '';
            const isUnread = session.unread_admin > 0 ? 'unread' : '';
            const initial = session.user_name.charAt(0).toUpperCase();
            
            // Check if there are newly received messages from other sessions to play sound
            const prevItem = document.getElementById(`session-${session.id}`);
            if (prevItem) {
                const prevUnread = parseInt(prevItem.getAttribute('data-unread') || '0');
                const newUnread = parseInt(session.unread_admin);
                if (newUnread > prevUnread) {
                    playNotificationSound();
                    showDesktopNotification(session.user_name, session.last_msg_text);
                }
            }

            totalUnread += parseInt(session.unread_admin);

            html += `
                <div class="chat-session-item ${isActive} ${isUnread}" id="session-${session.id}" data-unread="${session.unread_admin}" onclick="selectSession(${session.id}, '${escapeHtml(session.user_name)}', '${session.user_id ? 'Terdaftar' : 'Anonim'}', '${session.user_phone || '-'}', '${session.user_email || '-'}', '${session.status}')">
                    <div class="session-avatar">${initial}</div>
                    <div class="session-info">
                        <div class="session-name-row">
                            <span class="session-name">${escapeHtml(session.user_name)}</span>
                            <span class="session-time">${session.last_msg_time}</span>
                        </div>
                        <div class="session-msg-row">
                            <span class="session-preview">${escapeHtml(session.last_msg_text)}</span>
                            ${session.unread_admin > 0 ? `<span class="session-badge">${session.unread_admin}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });

        sessionsContainer.innerHTML = html;
        
        // Update sidebar unread badge globally if exists
        const sidebarBadge = document.querySelector('.chat-badge');
        if (sidebarBadge) {
            if (totalUnread > 0) {
                sidebarBadge.textContent = totalUnread;
                sidebarBadge.style.display = 'inline-block';
            } else {
                sidebarBadge.style.display = 'none';
            }
        }
    }

    // Escape HTML helper
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Show HTML5 desktop notification
    function showDesktopNotification(title, body) {
        if (Notification.permission === 'granted') {
            new Notification(`Pesan Baru: ${title}`, {
                body: body,
                icon: '../assets/images/placeholder.svg'
            });
        }
    }

    // Select a Session
    window.selectSession = function(id, name, type, phone, email, status) {
        activeSessionId = id;
        lastMessageId = 0;
        
        // Set active user metadata
        activeUserName.textContent = name;
        activeUserAvatar.textContent = name.charAt(0).toUpperCase();
        activeUserPhone.textContent = phone;
        activeUserEmail.textContent = email;
        
        // Registration Badge
        if (type === 'Terdaftar') {
            activeUserRegBadge.textContent = 'Terdaftar';
            activeUserRegBadge.className = 'badge badge-success';
        } else {
            activeUserRegBadge.textContent = 'Anonim';
            activeUserRegBadge.className = 'badge badge-warning';
        }

        // Show/hide close button based on status
        if (status === 'closed') {
            btnCloseSession.style.display = 'none';
            document.querySelector('.conversation-input-area').classList.add('hidden');
            document.querySelector('.quick-replies-container').classList.add('hidden');
        } else {
            btnCloseSession.style.display = 'inline-flex';
            document.querySelector('.conversation-input-area').classList.remove('hidden');
            document.querySelector('.quick-replies-container').classList.remove('hidden');
        }

        // Display panel
        conversationEmpty.classList.add('hidden');
        conversationActive.classList.remove('hidden');
        conversationMessages.innerHTML = '<div class="chat-loading">Memuat percakapan...</div>';

        // Clear active session highlight and set clicked one
        document.querySelectorAll('.chat-session-item').forEach(item => {
            item.classList.remove('active');
        });
        const clickedItem = document.getElementById(`session-${id}`);
        if (clickedItem) {
            clickedItem.classList.add('active');
            clickedItem.classList.remove('unread');
            const itemBadge = clickedItem.querySelector('.session-badge');
            if (itemBadge) itemBadge.remove();
        }

        // Start message polling
        if (pollInterval) clearInterval(pollInterval);
        pollMessages();
        pollInterval = setInterval(pollMessages, 3000);
    }

    // Poll messages for active session
    async function pollMessages() {
        if (!activeSessionId) return;

        try {
            const res = await fetch(`chat-api.php?action=messages&session_id=${activeSessionId}&after_id=${lastMessageId}`);
            const data = await res.json();

            if (data.success) {
                // If it was first load, clear loading text
                if (lastMessageId === 0) {
                    conversationMessages.innerHTML = '';
                }

                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendMessage(msg);
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                        }
                    });
                }
            }
        } catch (err) {
            console.error('Error polling messages:', err);
        }
    }

    // Append Message to UI
    function appendMessage(msg) {
        const isAdmin = msg.sender_type === 'admin';
        const isSystem = msg.sender_type === 'system';
        const isAI = msg.sender_type === 'ai';

        const msgDiv = document.createElement('div');
        msgDiv.id = `msg-${msg.id}`;

        if (isSystem) {
            msgDiv.className = 'chat-message-system';
            msgDiv.innerHTML = `<span>${escapeHtml(msg.message)}</span>`;
        } else {
            const senderClass = isAdmin ? 'message-admin' : (isAI ? 'message-ai' : 'message-user');
            let senderNameHtml = '';
            
            if (isAI) {
                senderNameHtml = '<span class="ai-badge">AI Assistant</span>';
            } else if (!isAdmin) {
                senderNameHtml = `<span class="user-name-tag">${escapeHtml(msg.sender_name)}</span>`;
            }

            msgDiv.className = `chat-message ${senderClass}`;
            msgDiv.innerHTML = `
                <div class="message-bubble">
                    ${senderNameHtml ? `<div class="message-sender">${senderNameHtml}</div>` : ''}
                    <div class="message-text">${msg.message.replace(/\n/g, '<br>')}</div>
                    <div class="message-time">${msg.time}</div>
                </div>
            `;
        }

        conversationMessages.appendChild(msgDiv);
        conversationMessages.scrollTop = conversationMessages.scrollHeight;
    }

    // Send Message
    async function sendMessage() {
        const message = chatReplyInput.value.trim();
        if (!message || !activeSessionId) return;

        chatReplyInput.value = '';
        chatReplyInput.focus();

        const formData = new FormData();
        formData.append('session_id', activeSessionId);
        formData.append('message', message);

        // Pre-append temporary message for smooth response
        const tempId = 'temp-' + Date.now();
        appendMessage({
            id: tempId,
            sender_type: 'admin',
            sender_name: 'Admin',
            message: message,
            time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
        });

        try {
            const res = await fetch('chat-api.php?action=send', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                const tempMsg = document.getElementById(`msg-${tempId}`);
                if (tempMsg) {
                    tempMsg.id = `msg-${data.message_id}`;
                }
                if (data.message_id > lastMessageId) {
                    lastMessageId = data.message_id;
                }
                // Refresh sessions list to show last message preview
                fetchSessions();
            } else {
                alert(data.message || 'Gagal mengirim pesan.');
            }
        } catch (err) {
            console.error('Error sending message:', err);
            alert('Gagal menghubungi server.');
        }
    }

    chatReplySend.addEventListener('click', sendMessage);
    chatReplyInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Close session
    btnCloseSession.addEventListener('click', async () => {
        if (!activeSessionId) return;
        if (!confirm('Yakin ingin menutup sesi chat ini? Sesi yang ditutup tidak bisa dikirimi pesan lagi.')) return;

        const formData = new FormData();
        formData.append('session_id', activeSessionId);

        try {
            const res = await fetch('chat-api.php?action=close', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                // Update local status representation
                btnCloseSession.style.display = 'none';
                document.querySelector('.conversation-input-area').classList.add('hidden');
                document.querySelector('.quick-replies-container').classList.add('hidden');
                
                // Refresh messages & sessions
                pollMessages();
                fetchSessions();
            } else {
                alert(data.message || 'Gagal menutup sesi.');
            }
        } catch (err) {
            console.error('Error closing session:', err);
        }
    });

    // Quick Replies click handler
    document.getElementById('quick-replies-list').addEventListener('click', (e) => {
        if (e.target.classList.contains('quick-reply-btn')) {
            chatReplyInput.value = e.target.textContent;
            chatReplyInput.focus();
        }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
