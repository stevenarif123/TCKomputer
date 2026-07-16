<?php
/**
 * Live Chat Storefront Widget
 * Floating chat widget for buyer pages.
 */
$isLoggedIn = isset($_SESSION['customer_id']) ? 'true' : 'false';
$customerName = isset($_SESSION['customer_profile']['name']) ? $_SESSION['customer_profile']['name'] : '';
$customerPhone = isset($_SESSION['customer_profile']['phone']) ? $_SESSION['customer_profile']['phone'] : '';
$customerEmail = isset($_SESSION['customer_profile']['email']) ? $_SESSION['customer_profile']['email'] : '';
$currentPage = basename($_SERVER['PHP_SELF']);
$csrfToken = isset($csrfToken) ? $csrfToken : (function_exists('generateCSRFToken') ? generateCSRFToken() : '');
?>

<!-- Floating Chat Button -->
<button id="chat-widget-button" class="fixed bottom-6 z-[90] w-14 h-14 rounded-full bg-[#0061A4] hover:bg-[#004e85] text-white flex items-center justify-center shadow-lg transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none <?= ($currentPage === 'index.php') ? 'right-28' : 'right-6' ?>" title="Chat dengan CS TCKomputer">
    <span class="material-symbols-outlined text-2xl" id="chat-btn-icon">chat</span>
    <span id="chat-widget-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
</button>

<!-- Chat Widget Panel -->
<div id="chat-widget-panel" class="fixed bottom-24 z-[100] w-[380px] h-[500px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col hidden overflow-hidden transition-all duration-300 transform scale-95 opacity-0 <?= ($currentPage === 'index.php') ? 'right-28' : 'right-6' ?>">
    <!-- Header -->
    <div class="bg-[#0061A4] text-white px-4 py-3.5 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-pulse"></span>
            <div>
                <h4 class="text-sm font-bold tracking-tight">Dukungan Live Chat</h4>
                <p class="text-[10px] text-white/70 leading-none">Online - CS TCKomputer</p>
            </div>
        </div>
        <button id="chat-widget-close" class="text-white/80 hover:text-white transition-colors focus:outline-none">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>

    <!-- Pre-chat Form -->
    <div id="chat-widget-preform" class="flex-grow p-5 flex flex-col justify-center overflow-y-auto space-y-4">
        <div class="text-center space-y-1.5 mb-2">
            <h5 class="text-slate-800 font-bold text-sm">Ada yang bisa kami bantu?</h5>
            <p class="text-xs text-slate-500 leading-relaxed">Silakan isi data diri Anda untuk memulai percakapan dengan tim Customer Service kami.</p>
        </div>
        <form id="chat-init-form" class="space-y-3.5">
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Nama Lengkap</label>
                <input type="text" id="chat-anon-name" placeholder="Contoh: Budi Santoso" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs outline-none focus:border-[#0061A4]" />
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Nomor HP (WhatsApp)</label>
                <input type="tel" id="chat-anon-phone" placeholder="Contoh: 08123456789" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs outline-none focus:border-[#0061A4]" />
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Email (Opsional)</label>
                <input type="email" id="chat-anon-email" placeholder="Contoh: budi@gmail.com" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs outline-none focus:border-[#0061A4]" />
            </div>
            <button type="submit" class="w-full py-2.5 bg-[#0061A4] hover:bg-[#004e85] text-white rounded-lg text-xs font-bold transition-all shadow-md mt-2 flex items-center justify-center gap-1.5">
                Mulai Chat <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </button>
        </form>
    </div>

    <!-- Chat Messages Log (Hidden by default) -->
    <div id="chat-widget-messages" class="flex-grow p-4 overflow-y-auto bg-slate-50 space-y-3 hidden flex flex-col">
        <!-- Message bubbles appended here -->
        <div class="text-center py-2">
            <span class="text-[10px] bg-slate-200 text-slate-600 px-2.5 py-1 rounded-full uppercase tracking-wider font-semibold">Percakapan Dimulai</span>
        </div>
    </div>

    <!-- Input Area (Hidden by default) -->
    <div id="chat-widget-input-area" class="p-3 border-t border-slate-200 bg-white flex gap-2 items-center hidden shrink-0">
        <textarea id="chat-widget-input" placeholder="Tulis pesan..." rows="1" class="flex-grow border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-[#0061A4] resize-none max-h-16 outline-none" style="font-family: inherit;"></textarea>
        <button id="chat-widget-send" class="w-8 h-8 rounded-lg bg-[#0061A4] hover:bg-[#004e85] text-white flex items-center justify-center transition-all shrink-0 focus:outline-none active:scale-90" title="Kirim Pesan">
            <span class="material-symbols-outlined text-md">send</span>
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const isUserLoggedIn = <?= $isLoggedIn ?>;
    const defaultName = "<?= sanitizeOutput($customerName) ?>";
    const defaultPhone = "<?= sanitizeOutput($customerPhone) ?>";
    const defaultEmail = "<?= sanitizeOutput($customerEmail) ?>";
    const csrfToken = "<?= $csrfToken ?>";

    const chatButton = document.getElementById('chat-widget-button');
    const chatPanel = document.getElementById('chat-widget-panel');
    const chatClose = document.getElementById('chat-widget-close');
    const chatPreform = document.getElementById('chat-widget-preform');
    const chatInitForm = document.getElementById('chat-init-form');
    const chatMessages = document.getElementById('chat-widget-messages');
    const chatInputArea = document.getElementById('chat-widget-input-area');
    const chatInput = document.getElementById('chat-widget-input');
    const chatSend = document.getElementById('chat-widget-send');
    const chatBadge = document.getElementById('chat-widget-badge');
    const chatBtnIcon = document.getElementById('chat-btn-icon');

    let sessionToken = localStorage.getItem('chat_session_token') || '';
    let lastMessageId = 0;
    let pollInterval = null;
    let isOpen = false;
    let unreadCount = 0;

    // Toggle Chat Panel
    chatButton.addEventListener('click', () => {
        if (isOpen) {
            closeChatPanel();
        } else {
            openChatPanel();
        }
    });

    chatClose.addEventListener('click', closeChatPanel);

    function openChatPanel() {
        chatPanel.classList.remove('hidden');
        setTimeout(() => {
            chatPanel.classList.remove('scale-95', 'opacity-0');
            chatPanel.classList.add('scale-100', 'opacity-100');
        }, 10);
        isOpen = true;
        chatBtnIcon.textContent = 'keyboard_arrow_down';
        unreadCount = 0;
        chatBadge.classList.add('hidden');
        chatBadge.textContent = '0';
        
        // Auto scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Initialize Chat Flow
        initChatFlow();
    }

    function closeChatPanel() {
        chatPanel.classList.remove('scale-100', 'opacity-100');
        chatPanel.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            chatPanel.classList.add('hidden');
        }, 300);
        isOpen = false;
        chatBtnIcon.textContent = 'chat';
    }

    // Auto-expand textarea
    chatInput.addEventListener('input', () => {
        chatInput.style.height = 'auto';
        chatInput.style.height = (chatInput.scrollHeight) + 'px';
    });

    // Check if session token exists or user is logged in
    function initChatFlow() {
        if (isUserLoggedIn) {
            showChatLayout();
            startPolling();
        } else if (sessionToken) {
            showChatLayout();
            startPolling();
        } else {
            // Show Pre-Chat Form, pre-populate if data exists in localStorage
            showPrechatForm();
        }
    }

    function showPrechatForm() {
        chatPreform.classList.remove('hidden');
        chatMessages.classList.add('hidden');
        chatInputArea.classList.add('hidden');
        
        const storedName = localStorage.getItem('chat_user_name') || '';
        const storedPhone = localStorage.getItem('chat_user_phone') || '';
        const storedEmail = localStorage.getItem('chat_user_email') || '';

        document.getElementById('chat-anon-name').value = storedName;
        document.getElementById('chat-anon-phone').value = storedPhone;
        document.getElementById('chat-anon-email').value = storedEmail;
    }

    function showChatLayout() {
        chatPreform.classList.add('hidden');
        chatMessages.classList.remove('hidden');
        chatInputArea.classList.remove('hidden');
    }

    // Handle Form Init Submission
    chatInitForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('chat-anon-name').value.trim();
        const phone = document.getElementById('chat-anon-phone').value.trim();
        const email = document.getElementById('chat-anon-email').value.trim();

        const formData = new FormData();
        formData.append('name', name);
        formData.append('phone', phone);
        formData.append('email', email);
        formData.append('csrf_token', csrfToken);

        try {
            const res = await fetch('actions/chat-send.php?action=init', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                sessionToken = data.session_token;
                localStorage.setItem('chat_session_token', sessionToken);
                localStorage.setItem('chat_user_name', name);
                localStorage.setItem('chat_user_phone', phone);
                if (email) localStorage.setItem('chat_user_email', email);

                showChatLayout();
                
                // Add a welcome system message
                appendSystemMessage("Terima kasih! Sesi chat Anda telah aktif. Silakan kirimkan pertanyaan Anda.");
                
                startPolling();
            } else {
                alert(data.message || 'Gagal memulai chat.');
            }
        } catch (err) {
            console.error(err);
            alert('Terjadi kesalahan koneksi server.');
        }
    });

    // Send Message
    async function handleSendMessage() {
        const message = chatInput.value.trim();
        if (!message) return;

        chatInput.value = '';
        chatInput.style.height = 'auto';

        const formData = new FormData();
        formData.append('message', message);
        formData.append('session_token', sessionToken);
        formData.append('csrf_token', csrfToken);

        // Pre-append user message instantly for premium responsive feel
        const tempId = 'temp-' + Date.now();
        appendMessage({
            id: tempId,
            sender_type: 'user',
            sender_name: 'Anda',
            message: message,
            time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
        });

        try {
            const res = await fetch('actions/chat-send.php?action=send', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                // If failed, mark temporary message with error
                const tempMsg = document.getElementById(`msg-${tempId}`);
                if (tempMsg) {
                    tempMsg.querySelector('.msg-status').textContent = 'error_outline';
                    tempMsg.querySelector('.msg-status').classList.add('text-red-500');
                    tempMsg.title = data.message;
                }
            } else {
                // Replace temp ID with actual ID on poll logic later, or update status
                const tempMsg = document.getElementById(`msg-${tempId}`);
                if (tempMsg) {
                    tempMsg.id = `msg-${data.message_id}`;
                    const statusIcon = tempMsg.querySelector('.msg-status');
                    if (statusIcon) statusIcon.textContent = 'done';
                }
                if (data.message_id > lastMessageId) {
                    lastMessageId = data.message_id;
                }
            }
        } catch (err) {
            console.error(err);
            const tempMsg = document.getElementById(`msg-${tempId}`);
            if (tempMsg) {
                tempMsg.querySelector('.msg-status').textContent = 'error_outline';
                tempMsg.querySelector('.msg-status').classList.add('text-red-500');
            }
        }
    }

    chatSend.addEventListener('click', handleSendMessage);
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        }
    });

    // Start Polling for messages
    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        
        // Immediate poll
        pollMessages();
        
        pollInterval = setInterval(pollMessages, 3000);
    }

    async function pollMessages() {
        try {
            const res = await fetch(`actions/chat-poll.php?session_token=${sessionToken}&after_id=${lastMessageId}`);
            const data = await res.json();

            if (data.success && data.messages && data.messages.length > 0) {
                let hasNewNonSelfMessages = false;

                data.messages.forEach(msg => {
                    // Check if message is already rendered (e.g. from user instant send)
                    if (!document.getElementById(`msg-${msg.id}`)) {
                        appendMessage(msg);
                        if (msg.sender_type !== 'user') {
                            hasNewNonSelfMessages = true;
                        }
                    }
                    if (msg.id > lastMessageId) {
                        lastMessageId = msg.id;
                    }
                });

                if (hasNewNonSelfMessages && !isOpen) {
                    // Increment badge if panel is closed
                    unreadCount += data.messages.filter(m => m.sender_type !== 'user').length;
                    chatBadge.textContent = unreadCount;
                    chatBadge.classList.remove('hidden');
                }
            }
        } catch (err) {
            console.error('Error polling chat messages:', err);
        }
    }

    // Message append helper
    function appendMessage(msg) {
        const isSelf = msg.sender_type === 'user';
        const msgDiv = document.createElement('div');
        msgDiv.id = `msg-${msg.id}`;
        msgDiv.className = `flex flex-col max-w-[80%] ${isSelf ? 'self-end items-end' : 'self-start items-start'}`;

        let bubbleClass = '';
        let senderText = '';
        
        if (isSelf) {
            bubbleClass = 'bg-[#0061A4] text-white rounded-2xl rounded-tr-none px-3.5 py-2';
        } else if (msg.sender_type === 'ai') {
            bubbleClass = 'bg-teal-50 text-teal-900 border border-teal-100 rounded-2xl rounded-tl-none px-3.5 py-2';
            senderText = `<span class="text-[9px] font-black uppercase text-teal-700 bg-teal-100 px-1.5 py-0.5 rounded mr-1">AI</span> `;
        } else if (msg.sender_type === 'system') {
            appendSystemMessage(msg.message);
            return;
        } else { // admin
            bubbleClass = 'bg-slate-100 text-slate-800 rounded-2xl rounded-tl-none px-3.5 py-2 border border-slate-200/50';
            senderText = `<span class="text-[9px] font-bold text-slate-500 mr-1">${msg.sender_name}</span> `;
        }

        msgDiv.innerHTML = `
            ${senderText ? `<div class="text-[10px] text-slate-500 mb-0.5 flex items-center">${senderText}</div>` : ''}
            <div class="${bubbleClass} shadow-sm break-words text-xs leading-relaxed">
                ${msg.message.replace(/\n/g, '<br>')}
            </div>
            <div class="text-[9px] text-slate-400 mt-1 flex items-center gap-1">
                <span>${msg.time}</span>
                ${isSelf ? `<span class="material-symbols-outlined text-[10px] msg-status">done</span>` : ''}
            </div>
        `;

        chatMessages.appendChild(msgDiv);
        
        // Auto scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function appendSystemMessage(text) {
        const systemDiv = document.createElement('div');
        systemDiv.className = 'text-center py-1.5';
        systemDiv.innerHTML = `
            <span class="text-[9px] bg-slate-200/80 text-slate-600 px-3 py-1 rounded-full font-medium leading-normal">${text}</span>
        `;
        chatMessages.appendChild(systemDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Auto-resume polling on load if token exists (polls silently to maintain badge count)
    if (sessionToken || isUserLoggedIn) {
        // Poll once to check for unread messages
        pollMessages();
        // Start polling silently in the background
        pollInterval = setInterval(pollMessages, 5000); // slower polling when closed
    }
});
</script>
