import { API, fetchJson } from './api-client.js';
import { formatTime, shortenMessage } from './chat-utils.js';

const messagesContainer = document.getElementById('chatMessages');
const form              = document.getElementById('chatForm');
const messageInput      = document.getElementById('messageInput');
const conversationItems = document.getElementById('conversationItems');
const activeBotName     = document.getElementById('activeBotName');
const chatHeader        = document.getElementById('chatHeader');
const chatEmptyState    = document.getElementById('chatEmptyState');

let currentUserName     = localStorage.getItem('chatty_name') || 'Anonymous';
let currentUserId       = null;
let activeConversationId = 0;

/** When true, periodic polling skips refresh so an open edit UI is not wiped. */
let suppressMessagePoll = false;

// ─── Show / hide the chat UI based on whether a conversation is open ─────────
//
// When no conversation is selected we hide the header, messages, and composer
// and show a centred placeholder instead.

function setChatActive(active) {
    chatEmptyState.classList.toggle('chat-ui-hidden', active);
    chatHeader.classList.toggle('chat-ui-hidden', !active);
    messagesContainer.classList.toggle('chat-ui-hidden', !active);
    form.classList.toggle('chat-ui-hidden', !active);
}

// ─── Close all open message menus ────────────────────────────────────────────

function closeAllMessageMenus() {
    document.querySelectorAll('.message-menu-dropdown.is-open').forEach((dd) => {
        dd.classList.remove('is-open');
        const wrap = dd.closest('.message-bubble-wrap');
        if (wrap) wrap.classList.remove('menu-open');
        const btn = wrap?.querySelector('.message-menu-trigger');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    });
}

// ─── Load current user from /api/me ──────────────────────────────────────────

async function initUser() {
    try {
        const { data } = await fetchJson(API.me);
        if (data.loggedIn && data.name) {
            currentUserName = data.name;
            if (data.id != null) currentUserId = Number(data.id);
            const nameEl = document.getElementById('sidebarUserName');
            const roleEl = document.getElementById('sidebarUserRole');
            if (nameEl) nameEl.textContent = data.name;
            if (roleEl) roleEl.textContent = data.email || 'Logged in';
        }
    } catch (e) {
        console.error('Could not load user', e);
    }
}

// ─── Render user list in sidebar ─────────────────────────────────────────────
//
// Each item shows the other user's name + last message preview (if any DM
// exists).  Clicking opens the existing conversation or creates a new one.

function renderUsers(users) {
    if (!conversationItems) return;
    conversationItems.innerHTML = '';

    if (users.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'conv-empty';
        empty.textContent = 'No other users yet.';
        conversationItems.appendChild(empty);
        return;
    }

    users.forEach((u) => {
        const item = document.createElement('div');
        item.className = 'conv-item' + (u.conversation_id === activeConversationId && activeConversationId !== 0 ? ' active' : '');
        item.dataset.userId = String(u.id);

        const img = document.createElement('img');
        img.className = 'conv-avatar';
        img.src = '../../assets/avatar.png';
        img.alt = '';
        img.width = 42;
        img.height = 42;

        const content = document.createElement('div');
        content.className = 'conv-content';

        const name = document.createElement('div');
        name.className = 'conv-name';
        name.textContent = u.name;

        const preview = document.createElement('div');
        preview.className = 'conv-preview';
        preview.textContent = u.last_message
            ? shortenMessage(u.last_message)
            : 'No messages yet';
        // Keep a reference so we can update the preview after sending.
        if (u.conversation_id) {
            preview.dataset.previewFor = String(u.conversation_id);
        }

        content.appendChild(name);
        content.appendChild(preview);

        const time = document.createElement('div');
        time.className = 'conv-time';
        time.textContent = u.last_message_at ? formatTime(u.last_message_at) : '';
        if (u.conversation_id) {
            time.dataset.timeFor = String(u.conversation_id);
        }

        item.appendChild(img);
        item.appendChild(content);
        item.appendChild(time);

        item.addEventListener('click', async () => {
            suppressMessagePoll = false;

            // If a DM already exists, open it directly.
            // If not, ask the server to create one.
            let convId = u.conversation_id;
            if (!convId) {
                const { res, data } = await fetchJson(API.conversations, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ user_id: u.id }),
                });
                if (!res.ok) return;
                convId = data.conversation_id;
                // Store so subsequent clicks skip the POST.
                u.conversation_id = convId;
                preview.dataset.previewFor = String(convId);
                time.dataset.timeFor       = String(convId);
            }

            activeConversationId = convId;
            if (activeBotName) activeBotName.textContent = u.name;
            setChatActive(true);

            // Re-render to move the active highlight.
            renderUsers(users);
            await loadMessages(activeConversationId);
        });

        conversationItems.appendChild(item);
    });
}

// ─── Fetch user list ─────────────────────────────────────────────────────────

async function loadUsers() {
    const { res, data } = await fetchJson(API.users);
    if (!res.ok) {
        if (res.status === 401) window.location.href = '../html/login.html';
        return;
    }
    const list = Array.isArray(data) ? data : [];

    // Auto-open the first conversation that already has messages.
    const firstActive = list.find(u => u.conversation_id);
    if (firstActive && activeConversationId === 0) {
        activeConversationId = firstActive.conversation_id;
        if (activeBotName) activeBotName.textContent = firstActive.name;
        setChatActive(true);
    }

    renderUsers(list);
    return list;
}

// ─── Load messages for a conversation ────────────────────────────────────────

async function loadMessages(conversationId = activeConversationId) {
    try {
        const url = new URL(API.messages, window.location.href);
        if (conversationId) url.searchParams.set('conversation_id', String(conversationId));
        const { res, data } = await fetchJson(url.toString());

        if (!res.ok) {
            if (res.status === 401) window.location.href = '../html/login.html';
            return;
        }
        const list = Array.isArray(data) ? data : [];

        messagesContainer.innerHTML = '';

        let lastMsg = null;
        list.forEach(msg => {
            lastMsg = msg;
            const uid   = msg.user_id != null ? Number(msg.user_id) : null;
            const isOwn = currentUserId != null && uid != null && uid === currentUserId;

            const row = document.createElement('div');
            row.className = 'message ' + (isOwn ? 'outgoing' : 'incoming');
            row.dataset.messageId = msg.id != null ? String(msg.id) : '';

            if (!isOwn) {
                const wrap = document.createElement('div');
                wrap.className = 'message-avatar-wrap avatar-36';
                const img = document.createElement('img');
                img.className = 'message-avatar';
                img.src = '../../assets/avatar.png';
                img.alt = '';
                img.width = 36;
                img.height = 36;
                const dot = document.createElement('span');
                dot.className = 'avatar-online';
                wrap.appendChild(img);
                wrap.appendChild(dot);
                row.appendChild(wrap);
            }

            const content = document.createElement('div');
            content.className = 'message-content';

            const meta = document.createElement('div');
            meta.className = 'message-name';
            meta.textContent = msg.name;
            content.appendChild(meta);

            if (isOwn && msg.id != null) {
                const bubbleWrap = document.createElement('div');
                bubbleWrap.className = 'message-bubble-wrap';

                const menuBtn = document.createElement('button');
                menuBtn.type = 'button';
                menuBtn.className = 'message-menu-trigger';
                menuBtn.setAttribute('aria-label', 'Message options');
                menuBtn.setAttribute('aria-haspopup', 'true');
                menuBtn.setAttribute('aria-expanded', 'false');
                menuBtn.innerHTML =
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';

                const dropdown = document.createElement('div');
                dropdown.className = 'message-menu-dropdown';
                dropdown.setAttribute('role', 'menu');

                const editItem = document.createElement('button');
                editItem.type = 'button';
                editItem.className = 'message-menu-item';
                editItem.setAttribute('role', 'menuitem');
                editItem.textContent = 'Edit';

                const delItem = document.createElement('button');
                delItem.type = 'button';
                delItem.className = 'message-menu-item message-menu-item-danger';
                delItem.setAttribute('role', 'menuitem');
                delItem.textContent = 'Delete';

                dropdown.appendChild(editItem);
                dropdown.appendChild(delItem);

                const bubble = document.createElement('div');
                bubble.className = 'message-bubble';
                bubble.textContent = msg.message;

                bubbleWrap.appendChild(bubble);
                bubbleWrap.appendChild(menuBtn);
                bubbleWrap.appendChild(dropdown);
                content.appendChild(bubbleWrap);

                menuBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const wasOpen = dropdown.classList.contains('is-open');
                    closeAllMessageMenus();
                    if (!wasOpen) {
                        dropdown.classList.add('is-open');
                        bubbleWrap.classList.add('menu-open');
                        menuBtn.setAttribute('aria-expanded', 'true');
                    }
                });

                editItem.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeAllMessageMenus();

                    suppressMessagePoll = true;
                    const editWrap = document.createElement('div');
                    editWrap.className = 'message-edit-wrap';
                    const ta = document.createElement('textarea');
                    ta.className = 'message-edit-input';
                    ta.rows = 3;
                    ta.value = msg.message;
                    const btnRow = document.createElement('div');
                    btnRow.className = 'message-edit-buttons';
                    const saveBtn = document.createElement('button');
                    saveBtn.type = 'button';
                    saveBtn.className = 'message-action-btn primary';
                    saveBtn.textContent = 'Save';
                    const cancelBtn = document.createElement('button');
                    cancelBtn.type = 'button';
                    cancelBtn.className = 'message-action-btn';
                    cancelBtn.textContent = 'Cancel';

                    const restoreBubble = () => {
                        editWrap.replaceWith(bubble);
                        bubbleWrap.classList.remove('is-editing');
                        suppressMessagePoll = false;
                    };

                    saveBtn.addEventListener('click', async () => {
                        const text = ta.value.trim();
                        if (!text) return;
                        const { res, data } = await fetchJson(API.message(msg.id), {
                            method:  'PATCH',
                            headers: { 'Content-Type': 'application/json' },
                            body:    JSON.stringify({ message: text }),
                        });
                        if (res.status === 401) { window.location.href = '../html/login.html'; return; }
                        if (!res.ok) { alert(data?.error ?? 'Could not save'); return; }
                        suppressMessagePoll = false;
                        await loadMessages(conversationId);
                    });

                    cancelBtn.addEventListener('click', restoreBubble);

                    btnRow.appendChild(saveBtn);
                    btnRow.appendChild(cancelBtn);
                    editWrap.appendChild(ta);
                    editWrap.appendChild(btnRow);
                    bubbleWrap.classList.add('is-editing');
                    bubble.replaceWith(editWrap);
                    ta.focus();
                });

                delItem.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeAllMessageMenus();
                    if (!confirm('Delete this message?')) return;
                    const { res, data } = await fetchJson(API.message(msg.id), { method: 'DELETE' });
                    if (res.status === 401) { window.location.href = '../html/login.html'; return; }
                    if (!res.ok) { alert(data?.error ?? 'Could not delete'); return; }
                    suppressMessagePoll = false;
                    await loadMessages(conversationId);
                });
            } else {
                const bubble = document.createElement('div');
                bubble.className = 'message-bubble';
                bubble.textContent = msg.message;
                content.appendChild(bubble);
            }

            const time = document.createElement('div');
            time.className = 'message-time';
            time.textContent = formatTime(msg.created_at);
            content.appendChild(time);

            row.appendChild(content);
            messagesContainer.appendChild(row);
        });

        // Update the sidebar preview text for the active conversation.
        if (lastMsg && conversationId) {
            const previewEl = document.querySelector(`[data-preview-for="${conversationId}"]`);
            const timeEl    = document.querySelector(`[data-time-for="${conversationId}"]`);
            if (previewEl) previewEl.textContent = shortenMessage(lastMsg.message);
            if (timeEl)    timeEl.textContent    = formatTime(lastMsg.created_at);
        }

        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    } catch (e) {
        console.error('Failed to load messages:', e);
    }
}

// ─── Send a message ───────────────────────────────────────────────────────────

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const message = messageInput.value.trim();
    if (!message) return;

    const formData = new FormData();
    formData.append('name', currentUserName);
    formData.append('message', message);
    if (activeConversationId) formData.append('conversation_id', String(activeConversationId));

    const { res: sendRes } = await fetchJson(API.send, { method: 'POST', body: formData });
    if (sendRes.status === 401) {
        window.location.href = '../html/login.html';
        return;
    }

    messageInput.value = '';
    messageInput.focus();
    suppressMessagePoll = false;
    await loadMessages(activeConversationId);
});

// ─── Polling ──────────────────────────────────────────────────────────────────

function pollMessagesIfIdle() {
    if (suppressMessagePoll) return;
    if (activeConversationId === 0) return;   // no conversation open — nothing to poll
    loadMessages(activeConversationId);
}

// ─── Close menus on outside click / Escape ───────────────────────────────────

document.addEventListener('click', (e) => {
    if (!e.target.closest('.message-bubble-wrap')) closeAllMessageMenus();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllMessageMenus();
});

// ─── Bootstrap ───────────────────────────────────────────────────────────────

(async function init() {
    setChatActive(false);           // start hidden — no conversation selected yet
    await initUser();
    await loadUsers();
    if (activeConversationId !== 0) {
        await loadMessages(activeConversationId);
    }
    setInterval(pollMessagesIfIdle, 5000);
})();


