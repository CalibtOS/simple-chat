import { API, fetchJson } from './api-client.js';
import { formatTime, shortenMessage } from './chat-utils.js';

const messagesContainer   = document.getElementById('chatMessages');
const form                = document.getElementById('chatForm');
const messageInput        = document.getElementById('messageInput');
const conversationItems   = document.getElementById('conversationItems');
const activeBotName       = document.getElementById('activeBotName');
const chatHeader          = document.getElementById('chatHeader');
const chatEmptyState      = document.getElementById('chatEmptyState');
const chatHeaderOnlineDot = document.getElementById('chatHeaderOnlineDot');
const chatHeaderStatus    = document.getElementById('chatHeaderStatus');

let currentUserName      = localStorage.getItem('chatty_name') || 'Anonymous';
let currentUserId        = null;
let activeConversationId = 0;
let activeUserId         = null;   // id of the person whose conversation is open

/** When true, periodic polling skips refresh so an open edit UI is not wiped. */
let suppressMessagePoll = false;

function normalizeMessageList(data) {
    const raw = data?.messages ?? data?.data?.messages ?? data?.data ?? data ?? [];
    const list = Array.isArray(raw)
        ? raw
        : (raw && typeof raw === 'object' ? Object.values(raw) : []);

    return list
        .filter((msg) => msg && typeof msg === 'object')
        .map((msg) => ({
            id: msg.id ?? msg.message_id ?? null,
            user_id: msg.user_id ?? msg.userId ?? null,
            name: msg.name ?? msg.sender_name ?? 'Unknown',
            message: msg.message ?? msg.content ?? msg.text ?? '',
            created_at: msg.created_at ?? msg.createdAt ?? msg.sent_at ?? '',
            read_at: msg.read_at ?? msg.readAt ?? null,
        }))
        .sort((a, b) => {
            const aId = Number(a.id ?? 0);
            const bId = Number(b.id ?? 0);
            return aId - bId;
        });
}

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

// ─── Presence helpers ────────────────────────────────────────────────────────

/**
 * Convert a MySQL datetime string ("2024-01-15 14:30:00") into a human-readable
 * "last seen" label.  MySQL uses a space separator, not T, so we normalise it
 * before passing to the Date constructor.
 */
function formatLastSeen(lastSeenAt) {
    if (!lastSeenAt) return 'Last seen: a long time ago';

    const seen    = new Date(lastSeenAt.replace(' ', 'T'));
    const diffSec = Math.floor((Date.now() - seen.getTime()) / 1000);
    const diffMin = Math.floor(diffSec / 60);

    // Under a minute
    if (diffSec < 60) return 'Last seen just now';

    // Under an hour — "Last seen 3min ago"
    if (diffMin < 60) return `Last seen ${diffMin}min ago`;

    // Format a clock time like "10:00 PM"
    const timeStr = seen.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });

    // Same calendar day — "Last seen at 10:00 PM"
    const now = new Date();
    const sameDay = seen.getFullYear() === now.getFullYear()
                 && seen.getMonth()    === now.getMonth()
                 && seen.getDate()     === now.getDate();
    if (sameDay) return `Last seen at ${timeStr}`;

    // Yesterday — "Last seen yesterday at 10:00 PM"
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    const wasYesterday = seen.getFullYear() === yesterday.getFullYear()
                      && seen.getMonth()    === yesterday.getMonth()
                      && seen.getDate()     === yesterday.getDate();
    if (wasYesterday) return `Last seen yesterday at ${timeStr}`;

    // Older — "Last seen Jan 15"
    return `Last seen ${seen.toLocaleDateString([], { month: 'short', day: 'numeric' })}`;
}

/**
 * Update the chat header's online dot and status line to reflect a user's
 * current presence.  Called when opening a conversation and on each 30-second
 * presence refresh.
 */
function updateChatHeader(user) {
    if (chatHeaderOnlineDot) chatHeaderOnlineDot.hidden = !user.is_online;
    if (chatHeaderStatus) {
        chatHeaderStatus.textContent = user.is_online ? 'Online' : formatLastSeen(user.last_seen_at);
        chatHeaderStatus.dataset.online = user.is_online ? '1' : '0';
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

        // Avatar wrapper — needed so the online dot can be positioned absolutely.
        const avatarWrap = document.createElement('div');
        avatarWrap.className = 'conv-avatar-wrap';

        const img = document.createElement('img');
        img.className = 'conv-avatar';
        img.src = '../../assets/avatar.png';
        img.alt = '';
        img.width = 42;
        img.height = 42;
        avatarWrap.appendChild(img);

        // Only render the green dot when the user is actually online.
        if (u.is_online) {
            const dot = document.createElement('span');
            dot.className = 'avatar-online';
            avatarWrap.appendChild(dot);
        }

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

        item.appendChild(avatarWrap);
        item.appendChild(content);
        item.appendChild(time);

        item.addEventListener('click', async () => {
            suppressMessagePoll = false;

            let convId = u.conversation_id;
            if (!convId) {
                const { res, data } = await fetchJson(API.conversations, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ user_id: u.id }),
                });
                if (!res.ok) return;
                convId = data.conversation_id;
                u.conversation_id = convId;
                preview.dataset.previewFor = String(convId);
                time.dataset.timeFor       = String(convId);
            }

            activeConversationId = convId;
            activeUserId         = u.id;
            if (activeBotName) activeBotName.textContent = u.name;
            updateChatHeader(u);
            setChatActive(true);

            renderUsers(users);
            await loadMessages(activeConversationId);
        });

        conversationItems.appendChild(item);
    });

    // If a conversation is already open, keep the header presence in sync
    // (this runs on every loadUsers call, including the 30-second refresh).
    if (activeUserId !== null) {
        const activeUser = users.find(u => u.id === activeUserId);
        if (activeUser) updateChatHeader(activeUser);
    }
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
        activeUserId         = firstActive.id;
        if (activeBotName) activeBotName.textContent = firstActive.name;
        updateChatHeader(firstActive);
        setChatActive(true);
    }

    renderUsers(list);
    return list;
}

// ─── Pagination state ─────────────────────────────────────────────────────────
//
// These variables track where we are in the message history for the currently
// open conversation:
//
//   oldestMessageId  — id of the oldest message in the DOM; passed as before_id
//                      when fetching an older page.
//   newestMessageId  — id of the newest message in the DOM; passed as after_id
//                      when polling for new messages.
//   hasMoreMessages  — true when the server says there are older messages.
//   isLoadingMore    — guards against triggering a second fetch while one is
//                      already in flight (the scroll event fires continuously).
//
// All four reset when switching conversations or doing a full reload.

let oldestMessageId = 0;
let newestMessageId = 0;
let hasMoreMessages = false;
let isLoadingMore   = false;

// ─── Build a single message row DOM element ──────────────────────────────────
//
// Extracted into its own function so loadMessages, appendNewMessages, and
// loadMoreMessages can all reuse it without duplicating the DOM-building code.

function buildMessageRow(msg) {
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
                await loadMessages(activeConversationId);
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
            await loadMessages(activeConversationId);
        });
    } else {
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = msg.message;
        content.appendChild(bubble);
    }

    const timeRow = document.createElement('div');
    timeRow.className = 'message-time';
    timeRow.textContent = formatTime(msg.created_at);

    // Double-tick read receipt — only on outgoing messages with a real id.
    if (isOwn && msg.id != null) {
        const ticks = document.createElement('span');
        ticks.className = 'message-ticks' + (msg.read_at ? ' ticks-read' : '');
        ticks.innerHTML =
            '<svg width="16" height="11" viewBox="0 0 16 11" fill="none">' +
            '<path d="M1 6L4.5 10L10 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<path d="M6 6L9.5 10L15 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>';
        timeRow.appendChild(ticks);
    }

    content.appendChild(timeRow);

    row.appendChild(content);
    return row;
}

// ─── Spinner helpers ──────────────────────────────────────────────────────────
//
// The spinner lives at the very top of the messages container while an older
// page is being fetched.  It is inserted/removed programmatically — no button.

function showSpinner() {
    if (messagesContainer.querySelector('.load-more-spinner')) return;
    const el = document.createElement('div');
    el.className = 'load-more-spinner';
    messagesContainer.insertBefore(el, messagesContainer.firstChild);
}

function hideSpinner() {
    messagesContainer.querySelector('.load-more-spinner')?.remove();
}

// ─── Update tick colours for already-rendered outgoing messages ───────────────
//
// When a poll response includes read_through = N it means the other person has
// read all of the current user's messages up to and including ID N.
// We walk the DOM and flip .ticks-read onto any tick element whose message id
// is <= N.  This avoids re-fetching the full message list just to update colours.

function updateReadThroughs(readThrough) {
    if (readThrough == null) return;
    document.querySelectorAll('.message.outgoing[data-message-id]').forEach((el) => {
        const id = parseInt(el.dataset.messageId, 10);
        if (id <= readThrough) {
            el.querySelector('.message-ticks')?.classList.add('ticks-read');
        }
    });
}

// ─── Initial / full-reload: fetch the latest 20 messages ─────────────────────
//
// Called when opening a conversation, or after an edit/delete (to re-sync the
// DOM with the database).  Wipes the container and resets pagination state.

async function loadMessages(conversationId = activeConversationId) {
    try {
        const url = new URL(API.messages, window.location.href);
        if (conversationId) url.searchParams.set('conversation_id', String(conversationId));
        const { res, data } = await fetchJson(url.toString());

        if (!res.ok) {
            if (res.status === 401) window.location.href = '../html/login.html';
            return;
        }

        const list = normalizeMessageList(data);
        const resolvedConversationId = Number(data?.conversation_id ?? conversationId ?? 0);
        if (resolvedConversationId > 0) {
            activeConversationId = resolvedConversationId;
            setChatActive(true);
        }

        // Reset pagination state for this conversation.
        oldestMessageId = 0;
        newestMessageId = 0;
        hasMoreMessages = data?.has_more ?? false;
        isLoadingMore   = false;
        updateReadThroughs(data?.read_through ?? null);

        messagesContainer.innerHTML = '';

        list.forEach(msg => messagesContainer.appendChild(buildMessageRow(msg)));

        if (list.length > 0) {
            oldestMessageId = list[0].id;
            newestMessageId = list[list.length - 1].id;
        }

        // Update the sidebar preview for the active conversation.
        const lastMsg = list[list.length - 1];
        if (lastMsg && resolvedConversationId > 0) {
            const previewEl = document.querySelector(`[data-preview-for="${resolvedConversationId}"]`);
            const timeEl    = document.querySelector(`[data-time-for="${resolvedConversationId}"]`);
            if (previewEl) previewEl.textContent = shortenMessage(lastMsg.message);
            if (timeEl)    timeEl.textContent    = formatTime(lastMsg.created_at);
        }

        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    } catch (e) {
        console.error('Failed to load messages:', e);
    }
}

// ─── Load older messages (triggered by scrolling to the top) ─────────────────
//
// Called automatically by the scroll listener when the user reaches the top of
// the visible messages.  Shows a spinner, fetches the previous page, then swaps
// the spinner out for the new messages.
//
// Scroll trick: we snapshot scrollHeight while the spinner is already in the
// DOM (so its height is included), then after removing the spinner and
// prepending messages we add the net height change to scrollTop.  The net is
// (messages_height − spinner_height), which keeps the same message in view.

async function loadMoreMessages() {
    if (!hasMoreMessages || oldestMessageId === 0 || isLoadingMore) return;
    isLoadingMore = true;

    showSpinner();

    try {
        const url = new URL(API.messages, window.location.href);
        url.searchParams.set('conversation_id', String(activeConversationId));
        url.searchParams.set('before_id', String(oldestMessageId));

        // Run the fetch and a minimum display timer in parallel.
        // Promise.all waits for BOTH — so if the fetch finishes in 50 ms the
        // spinner still shows for the full 600 ms, but a slow fetch won't add
        // any extra wait on top of its real duration.
        const minVisible = new Promise(resolve => setTimeout(resolve, 600));
        const [{ res, data }] = await Promise.all([fetchJson(url.toString()), minVisible]);

        if (!res.ok) { hideSpinner(); return; }

        const list = normalizeMessageList(data);
        hasMoreMessages = data?.has_more ?? false;

        // Snapshot height now — spinner is visible, messages not yet added.
        const prevScrollHeight = messagesContainer.scrollHeight;

        // Swap spinner for messages in one pass.
        hideSpinner();
        if (list.length > 0) {
            const fragment = document.createDocumentFragment();
            list.forEach(msg => fragment.appendChild(buildMessageRow(msg)));
            messagesContainer.insertBefore(fragment, messagesContainer.firstChild);
            oldestMessageId = list[0].id;
        }

        // Restore the viewport: net delta = messages added − spinner removed.
        messagesContainer.scrollTop += messagesContainer.scrollHeight - prevScrollHeight;
    } catch (e) {
        console.error('Failed to load more messages:', e);
        hideSpinner();
    } finally {
        isLoadingMore = false;
    }
}

// ─── Poll for new messages ────────────────────────────────────────────────────
//
// Instead of re-fetching all messages every 5 seconds (which would wipe any
// history the user loaded with "Load more"), we only ask for messages that
// arrived after the newest one we already have (after_id = newestMessageId).
// New messages are appended to the bottom.
//
// Auto-scroll: we scroll to the bottom only if the user was already near it —
// a tolerance of 80 px.  This lets the user scroll up to read older messages
// without being yanked back down every time a new message arrives.

async function pollForNewMessages() {
    if (suppressMessagePoll) return;
    if (activeConversationId === 0) return;
    if (newestMessageId === 0) return;

    try {
        const url = new URL(API.messages, window.location.href);
        url.searchParams.set('conversation_id', String(activeConversationId));
        url.searchParams.set('after_id', String(newestMessageId));
        const { res, data } = await fetchJson(url.toString());

        if (!res.ok) return;

        updateReadThroughs(data?.read_through ?? null);

        const list = normalizeMessageList(data);
        if (list.length === 0) return;

        // Were we already at (or near) the bottom before the new messages?
        const atBottom =
            messagesContainer.scrollHeight - messagesContainer.scrollTop
            - messagesContainer.clientHeight < 80;

        list.forEach(msg => messagesContainer.appendChild(buildMessageRow(msg)));
        newestMessageId = list[list.length - 1].id;

        // Update the sidebar preview.
        const lastMsg = list[list.length - 1];
        if (lastMsg) {
            const previewEl = document.querySelector(
                `[data-preview-for="${activeConversationId}"]`
            );
            const timeEl = document.querySelector(
                `[data-time-for="${activeConversationId}"]`
            );
            if (previewEl) previewEl.textContent = shortenMessage(lastMsg.message);
            if (timeEl)    timeEl.textContent    = formatTime(lastMsg.created_at);
        }

        if (atBottom) messagesContainer.scrollTop = messagesContainer.scrollHeight;
    } catch (e) {
        console.error('Failed to poll messages:', e);
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

    const { res: sendRes, data: sendData } = await fetchJson(API.send, { method: 'POST', body: formData });
    if (sendRes.status === 401) {
        window.location.href = '../html/login.html';
        return;
    }
    if (!sendRes.ok) {
        alert(sendData?.error ?? 'Could not send message.');
        return;
    }

    const sentConversationId = Number(sendData?.conversation_id ?? 0);
    if (sentConversationId > 0) activeConversationId = sentConversationId;

    messageInput.value = '';
    messageInput.focus();
    suppressMessagePoll = false;
    await loadMessages(activeConversationId);
    await loadUsers();
});

// ─── Typing indicators ───────────────────────────────────────────────────────
//
// Sending side:
//   Every keystroke resets a 3-second timer.  While typing we send a heartbeat
//   to the server at most once every 2 seconds (the `heartbeatSent` flag acts
//   as a simple cooldown).  When the timer fires the user has stopped typing —
//   we just let the server-side row expire naturally (5-second threshold).
//
// Receiving side:
//   Every 2 seconds we ask the server who is currently typing in the open
//   conversation.  Anyone whose heartbeat is <5 s old shows up.  We turn the
//   result into a human-readable label ("Ferhat is typing…") and show/hide
//   the indicator element accordingly.

const typingIndicator = document.getElementById('typingIndicator');
const typingLabel     = document.getElementById('typingLabel');

let heartbeatSent  = false;   // cooldown flag — limits sends to once per 2 s
let stopTypingTimer = null;   // cleared on every keystroke

messageInput.addEventListener('input', () => {
    if (activeConversationId === 0) return;

    // Send a heartbeat if the cooldown has expired.
    if (!heartbeatSent) {
        heartbeatSent = true;
        fetchJson(API.typing, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ conversation_id: activeConversationId }),
        }).then(({ res, data }) => console.log('[typing send]', res.status, data));
        setTimeout(() => { heartbeatSent = false; }, 2000);
    }

    // Reset the stop-typing timer on every keystroke.
    clearTimeout(stopTypingTimer);
    stopTypingTimer = setTimeout(() => {
        // User paused — nothing to do; the server row expires after 5 s.
        heartbeatSent = false;
    }, 3000);
});

async function pollTyping() {
    if (activeConversationId === 0) return;

    const url = new URL(API.typing, window.location.href);
    url.searchParams.set('conversation_id', String(activeConversationId));
    const { res, data } = await fetchJson(url.toString());
    console.log('[typing poll]', res.status, data);
    if (!res.ok) return;

    const typers = data?.typing ?? [];

    if (typers.length === 0) {
        typingIndicator.hidden = true;
        return;
    }

    // Build a readable label: "Alice is typing…" / "Alice and Bob are typing…"
    const names = typers.map(t => t.name);
    const verb  = names.length === 1 ? 'is' : 'are';
    const who   = names.length === 1
        ? names[0]
        : names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1];

    typingLabel.textContent  = `${who} ${verb} typing`;
    typingIndicator.hidden   = false;
    console.log('[typing] showing indicator for:', names);
}

// ─── Scroll-to-top → load older messages ─────────────────────────────────────
//
// When the user scrolls within 80 px of the top of the message list, we
// automatically fetch the previous page.  The isLoadingMore flag makes sure
// we start at most one fetch per scroll interaction.

messagesContainer.addEventListener('scroll', () => {
    if (!hasMoreMessages || isLoadingMore) return;
    if (messagesContainer.scrollTop < 80) loadMoreMessages();
});

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
    await loadMessages(activeConversationId);
    setInterval(pollForNewMessages, 2000);
    setInterval(pollTyping, 2000);
    // Refresh the user list every 30 seconds so online dots and "last seen"
    // labels stay current without a full page reload.
    // Keep conversation previews snappy even when another chat is open.
    setInterval(loadUsers, 3000);
})();


