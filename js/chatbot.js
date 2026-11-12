(function () {
    'use strict';

    if (window.__eduPulseChatbotInitialized) return;
    window.__eduPulseChatbotInitialized = true;

    const API_URL = './backend/api/ask_edupulse.php';
    const STORAGE_KEY = 'edupulse_chat_history_v1';
    const STUDENT_KEY = 'edupulse_chat_student_v1';
    const DEFAULT_SUGGESTIONS = [
        'Show my academic summary',
        'Check my attendance risk',
        'What is my CGPA?',
        'Show my current courses'
    ];

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatReplyHtml(text) {
        const safe = escapeHtml(text);
        return safe
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/_(.+?)_/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    function currentTimeLabel() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function loadHistory() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : null;
            return Array.isArray(parsed) ? parsed : [];
        } catch (err) {
            return [];
        }
    }

    function saveHistory(messages) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.slice(-30)));
        } catch (err) {
            // Ignore storage quota errors
        }
    }

    function initChatbot() {
        const root = document.createElement('div');
        root.className = 'edupulse-chatbot-root';
        root.innerHTML = `
            <div class="edupulse-chat-panel" id="eduPulseChatPanel" role="dialog" aria-label="Ask EduPulse Academic Assistant" aria-hidden="true">
                <div class="edupulse-chat-header">
                    <div class="edupulse-chat-brand">
                        <div class="edupulse-chat-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="edupulse-chat-title">
                            <h6>Ask EduPulse</h6>
                            <span>Academic Assistance Chatbot</span>
                        </div>
                    </div>
                    <div class="edupulse-chat-actions">
                        <button type="button" class="edupulse-icon-btn" id="eduPulseClearChat" title="Clear conversation" aria-label="Clear conversation">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button type="button" class="edupulse-icon-btn" id="eduPulseCloseChat" title="Close" aria-label="Close chat">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="edupulse-chat-messages" id="eduPulseChatMessages"></div>
                <div class="edupulse-suggestions" id="eduPulseSuggestions"></div>
                <form class="edupulse-chat-form" id="eduPulseChatForm">
                    <input type="text" class="edupulse-chat-input" id="eduPulseChatInput" placeholder="Ask about CGPA, attendance, courses..." maxlength="500" autocomplete="off">
                    <button type="submit" class="edupulse-send-btn" id="eduPulseSendBtn" aria-label="Send message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
            <button type="button" class="edupulse-chat-fab" id="eduPulseChatFab" aria-expanded="false" aria-controls="eduPulseChatPanel">
                <i class="fas fa-comment-dots"></i>
                <span class="edupulse-fab-label">Ask EduPulse</span>
            </button>
        `;
        document.body.appendChild(root);

        const panel = document.getElementById('eduPulseChatPanel');
        const fab = document.getElementById('eduPulseChatFab');
        const closeBtn = document.getElementById('eduPulseCloseChat');
        const clearBtn = document.getElementById('eduPulseClearChat');
        const messagesEl = document.getElementById('eduPulseChatMessages');
        const suggestionsEl = document.getElementById('eduPulseSuggestions');
        const form = document.getElementById('eduPulseChatForm');
        const input = document.getElementById('eduPulseChatInput');
        const sendBtn = document.getElementById('eduPulseSendBtn');

        let history = loadHistory();
        let isSending = false;

        function scrollToBottom() {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function appendMessageElement(role, text, time) {
            const row = document.createElement('div');
            row.className = `edupulse-msg-row ${role}`;

            const bubble = document.createElement('div');
            bubble.className = 'edupulse-msg-bubble';
            if (role === 'bot') {
                bubble.innerHTML = formatReplyHtml(text);
            } else {
                bubble.textContent = text;
            }

            const timestamp = document.createElement('div');
            timestamp.className = 'edupulse-msg-time';
            timestamp.textContent = time || currentTimeLabel();

            row.appendChild(bubble);
            row.appendChild(timestamp);
            messagesEl.appendChild(row);
            scrollToBottom();
        }

        function renderSuggestions(list) {
            const items = Array.isArray(list) && list.length > 0 ? list : DEFAULT_SUGGESTIONS;
            suggestionsEl.innerHTML = '';
            items.forEach(prompt => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'edupulse-chip';
                chip.textContent = prompt;
                chip.addEventListener('click', () => sendQuestion(prompt));
                suggestionsEl.appendChild(chip);
            });
        }

        function seedWelcomeMessage() {
            const welcome = {
                role: 'bot',
                text: "Hi! I'm **Ask EduPulse**, your academic assistant.\nAsk me about your **CGPA & semester grades**, **attendance status & recovery**, **enrolled courses & faculty**, or **extracurricular points**.",
                time: currentTimeLabel()
            };
            history = [welcome];
            saveHistory(history);
            messagesEl.innerHTML = '';
            appendMessageElement(welcome.role, welcome.text, welcome.time);
            renderSuggestions(DEFAULT_SUGGESTIONS);
        }

        function renderInitialMessages() {
            if (history.length === 0) {
                seedWelcomeMessage();
                return;
            }
            messagesEl.innerHTML = '';
            history.forEach(item => appendMessageElement(item.role, item.text, item.time));
            renderSuggestions(DEFAULT_SUGGESTIONS);
        }

        function togglePanel(forceOpen) {
            const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !panel.classList.contains('open');
            panel.classList.toggle('open', shouldOpen);
            panel.setAttribute('aria-hidden', String(!shouldOpen));
            fab.setAttribute('aria-expanded', String(shouldOpen));
            if (shouldOpen) {
                scrollToBottom();
                setTimeout(() => input.focus(), 120);
            }
        }

        function showTypingIndicator() {
            const row = document.createElement('div');
            row.className = 'edupulse-msg-row bot';
            row.id = 'eduPulseTypingRow';
            row.innerHTML = `
                <div class="edupulse-msg-bubble">
                    <div class="edupulse-typing" aria-label="EduPulse is typing">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
            messagesEl.appendChild(row);
            scrollToBottom();
        }

        function removeTypingIndicator() {
            const el = document.getElementById('eduPulseTypingRow');
            if (el) el.remove();
        }

        async function sendQuestion(text) {
            const clean = String(text || '').trim();
            if (!clean || isSending) return;

            isSending = true;
            sendBtn.disabled = true;
            input.value = '';

            const userMsg = { role: 'user', text: clean, time: currentTimeLabel() };
            history.push(userMsg);
            saveHistory(history);
            appendMessageElement(userMsg.role, userMsg.text, userMsg.time);
            showTypingIndicator();

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message: clean })
                });

                const data = await response.json().catch(() => null);
                removeTypingIndicator();

                if (response.status === 401) {
                    const authMsg = {
                        role: 'bot',
                        text: 'Your student session has expired. Please sign in again on the login page to continue.',
                        time: currentTimeLabel()
                    };
                    history.push(authMsg);
                    saveHistory(history);
                    appendMessageElement(authMsg.role, authMsg.text, authMsg.time);
                    return;
                }

                if (!response.ok || !data || !data.success) {
                    const errText = (data && data.message) ? data.message : 'Unable to fetch your academic details right now. Please try again.';
                    const errMsg = { role: 'bot', text: errText, time: currentTimeLabel() };
                    history.push(errMsg);
                    saveHistory(history);
                    appendMessageElement(errMsg.role, errMsg.text, errMsg.time);
                    return;
                }

                const replyText = data.data && data.data.reply ? data.data.reply : 'No response received.';
                const activeStudent = data.data && data.data.meta ? data.data.meta.student_name : null;
                if (activeStudent) {
                    const prevStudent = sessionStorage.getItem(STUDENT_KEY);
                    if (prevStudent && prevStudent !== activeStudent) {
                        history = [userMsg];
                        messagesEl.innerHTML = '';
                        appendMessageElement(userMsg.role, userMsg.text, userMsg.time);
                    }
                    sessionStorage.setItem(STUDENT_KEY, activeStudent);
                }
                const botMsg = { role: 'bot', text: replyText, time: currentTimeLabel() };
                history.push(botMsg);
                saveHistory(history);
                appendMessageElement(botMsg.role, botMsg.text, botMsg.time);
                renderSuggestions(data.data ? data.data.suggestions : DEFAULT_SUGGESTIONS);
            } catch (err) {
                removeTypingIndicator();
                const netMsg = {
                    role: 'bot',
                    text: 'Connection error while contacting Ask EduPulse. Please check that the server is running and try again.',
                    time: currentTimeLabel()
                };
                history.push(netMsg);
                saveHistory(history);
                appendMessageElement(netMsg.role, netMsg.text, netMsg.time);
            } finally {
                isSending = false;
                sendBtn.disabled = false;
                input.focus();
            }
        }

        fab.addEventListener('click', () => togglePanel());
        closeBtn.addEventListener('click', () => togglePanel(false));
        clearBtn.addEventListener('click', () => {
            seedWelcomeMessage();
            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'clear' })
            }).catch(() => {});
        });
        const logoutBtn = document.querySelector('.logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                try {
                    sessionStorage.removeItem(STORAGE_KEY);
                    sessionStorage.removeItem(STUDENT_KEY);
                } catch (err) {
                    // Ignore storage errors
                }
            });
        }
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            sendQuestion(input.value);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('open')) {
                togglePanel(false);
            }
        });

        renderInitialMessages();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChatbot);
    } else {
        initChatbot();
    }
})();

