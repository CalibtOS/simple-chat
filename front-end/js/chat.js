const messagesContainer = document.getElementById('chatMessages');
const form = document.getElementById('chatForm');
const messageInput = document.getElementById('messageInput');
const conversationItems = document.getElementById('conversationItems');
const activeBotName = document.getElementById('activeBotName');

let currentUserName = localStorage.getItem('chatty_name') || 'Anonymous';
let currentUserId = null;
let activeConversationId = 0;

/** When true, periodic polling skips refresh so an open edit UI is not wiped. */
let suppressMessagePoll = false;

function closeAllMessageMenus() {
    document.querySelectorAll('.message-menu-dropdown.is-open').forEach((dd) => {
        dd.classList.remove('is-open');
        const wrap = dd.closest('.message-bubble-wrap');
        if (wrap) wrap.classList.remove('menu-open');
        const btn = wrap?.querySelector('.message-menu-trigger');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    });
}

async function initUser() {
    try {
        const res = await fetch('../../back-end/apis/api_me.php');
        const data = await res.json();
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

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    const h = d.getHours();
    const m = d.getMinutes();
    const ampm = h >= 12 ? 'pm' : 'am';
    return (h % 12 || 12) + ':' + String(m).padStart(2, '0') + ampm;
}

function renderConversations(conversations) {
    if (!conversationItems) return;
    conversationItems.innerHTML = '';

    conversations.forEach((c) => {
        const item = document.createElement('div');
        item.className = 'conv-item' + (c.id === activeConversationId ? ' active' : '');
        item.dataset.conversationId = String(c.id);

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
        name.textContent = c.bot?.name || c.title || 'Conversation';

        const preview = document.createElement('div');
        preview.className = 'conv-preview';
        preview.textContent = c.last_message
            ? (c.last_message.length > 40 ? c.last_message.slice(0, 37) + '...' : c.last_message)
            : 'No messages yet';
        preview.dataset.previewFor = String(c.id);

        content.appendChild(name);
        content.appendChild(preview);

        const time = document.createElement('div');
        time.className = 'conv-time';
        time.textContent = c.last_message_at ? formatTime(c.last_message_at) : '-';
        time.dataset.timeFor = String(c.id);

        item.appendChild(img);
        item.appendChild(content);
        item.appendChild(time);

        item.addEventListener('click', async () => {
            suppressMessagePoll = false;
            activeConversationId = c.id;
            if (activeBotName) activeBotName.textContent = c.bot?.name || 'Chat';
            renderConversations(conversations);
            await loadMessages(activeConversationId);
        });

        conversationItems.appendChild(item);
    });
}

async function loadConversations() {
    const res = await fetch('../../back-end/apis/api_conversations.php');
    const data = await res.json();
    if (!res.ok) {
        if (res.status === 401) window.location.href = '../html/login.html';
        return [];
    }
    const list = Array.isArray(data) ? data : [];
    if (list.length > 0 && activeConversationId === 0) {
        activeConversationId = list[0].id;
        if (activeBotName) activeBotName.textContent = list[0]?.bot?.name || 'Chat';
    }
    renderConversations(list);
    return list;
}

async function loadMessages(conversationId = activeConversationId) {
    try {
        const url = new URL('../../back-end/apis/api_messages.php', window.location.href);
        if (conversationId) url.searchParams.set('conversation_id', String(conversationId));

        const res = await fetch(url.toString());
        const data = await res.json();

        if (!res.ok) {
            if (res.status === 401) window.location.href = '../html/login.html';
            return;
        }
        const list = Array.isArray(data) ? data : [];

        messagesContainer.innerHTML = '';

        let lastMsg = null;
        list.forEach(msg => {
            lastMsg = msg;
            const uid = msg.user_id != null ? Number(msg.user_id) : null;
            const isOwn =
                currentUserId != null && uid != null && uid === currentUserId;
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
                        const fd = new FormData();
                        fd.append('message_id', String(msg.id));
                        fd.append('message', text);
                        const res = await fetch('../../back-end/apis/api_message_edit.php', {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin',
                        });
                        if (res.status === 401) {
                            window.location.href = '../html/login.html';
                            return;
                        }
                        if (!res.ok) {
                            let err = 'Could not save';
                            try {
                                const j = await res.json();
                                if (j.error) err = j.error;
                            } catch (err) { /* ignore */ }
                            alert(err);
                            return;
                        }
                        suppressMessagePoll = false;
                        await loadMessages(conversationId);
                    });

                    cancelBtn.addEventListener('click', () => {
                        restoreBubble();
                    });

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
                    const fd = new FormData();
                    fd.append('message_id', String(msg.id));
                    const res = await fetch('../../back-end/apis/api_message_delete.php', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                    });
                    if (res.status === 401) {
                        window.location.href = '../html/login.html';
                        return;
                    }
                    if (!res.ok) {
                        let err = 'Could not delete';
                        try {
                            const j = await res.json();
                            if (j.error) err = j.error;
                        } catch (err) { /* ignore */ }
                        alert(err);
                        return;
                    }
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

        if (lastMsg && conversationId) {
            const previewEl = document.querySelector(`[data-preview-for="${conversationId}"]`);
            const timeEl = document.querySelector(`[data-time-for="${conversationId}"]`);
            if (previewEl) previewEl.textContent = lastMsg.message.length > 40 ? lastMsg.message.slice(0, 37) + '...' : lastMsg.message;
            if (timeEl) timeEl.textContent = formatTime(lastMsg.created_at);
        }

        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    } catch (e) {
        console.error('Failed to load messages:', e);
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const message = messageInput.value.trim();
    if (!message) return;

    const formData = new FormData();
    formData.append('name', currentUserName);
    formData.append('message', message);
    if (activeConversationId) formData.append('conversation_id', String(activeConversationId));

    const sendRes = await fetch('../../back-end/apis/api_send.php', { method: 'POST', body: formData });
    if (sendRes.status === 401) {
        window.location.href = '../html/login.html';
        return;
    }

    messageInput.value = '';
    messageInput.focus();
    suppressMessagePoll = false;
    await loadMessages(activeConversationId);
});

function pollMessagesIfIdle() {
    if (suppressMessagePoll) return;
    loadMessages(activeConversationId);
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.message-bubble-wrap')) {
        closeAllMessageMenus();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllMessageMenus();
});

(async function init() {
    await initUser();
    await loadConversations();
    await loadMessages(activeConversationId);
    setInterval(pollMessagesIfIdle, 5000);
})();

