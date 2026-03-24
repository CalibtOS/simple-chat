---
name: Backend Learning Next Steps
overview: Evaluate current progress (auth + per-user chat APIs + frontend/backend separation) and outline a 2–4 week plan (mix of features + fundamentals + API testing) to deepen conceptual backend understanding using your existing simple-chat project.
todos:
  - id: trace-flows
    content: Trace register/login/send-message request lifecycles and document method/URL/session/SQL/response for each.
    status: pending
  - id: postman-collection
    content: Build a Postman collection covering login/logout/me/messages-list/messages-send + negative tests; ensure cookies/sessions work.
    status: pending
  - id: sql-exercises
    content: Do SQL exercises on users/messages (filters, counts, joins, indexes conceptually) using phpMyAdmin + add FK for messages.user_id -> users.id.
    status: pending
  - id: add-foreign-key
    content: Add a real foreign key messages.user_id -> users.id (ON DELETE CASCADE) and decide how to handle existing NULL/legacy rows.
    status: pending
  - id: feature-conversations
    content: Implement Conversations (multiple conversations) + assign a default bot per conversation (Ahmed/Islam/...) and extend APIs accordingly.
    status: pending
  - id: feature-edit-delete
    content: Implement Edit/Delete messages with authorization (only owner can edit/delete) and extend APIs accordingly.
    status: completed
  - id: api-hardening
    content: Standardize API response shapes/status codes and review a basic security checklist (input validation, auth boundaries, SQL safety).
    status: pending
isProject: false
---

# Next-step backend learning plan (simple-chat)

## Current progress (what you can already do)

- **HTTP + PHP basics**: handle GET/POST, read form fields, return JSON, use redirects.
- **Auth with sessions**: `login.php` sets `$_SESSION`, `logout.php` clears it; APIs use session to identify the user.
- **API-driven UI**: frontend calls JSON endpoints (`api_me`, `api_messages`, `api_send`) and handles `401`.
- **Database integration**: create users, hash passwords, store messages, filter by `user_id`.
- **Project structure**: separation of **front-end** (`front-end/html`, `front-end/js`, `front-end/css`) and **back-end** (`back-end`, `back-end/apis`).

## Main learning goal for next step

Build a stronger mental model of the back-end process by repeatedly practicing:

- **Request lifecycle**: browser/Postman → HTTP request → PHP routing/entrypoint → auth/session → DB query → response.
- **State**: sessions/cookies vs database state.
- **API contracts**: inputs, outputs, status codes, error shapes.
- **Debugging**: isolating problems with Postman and logs.
- **Security basics**: validation, password hashing, SQL injection risks, auth boundaries.

## 2–4 week curriculum (with your 4 hours/day)

### Module A — Request lifecycle + cookies/sessions (2–3 days)

- Trace 3 flows end-to-end:
  - **Register**: `front-end/html/register.html` → POST `back-end/register.php` → redirect.
  - **Login**: `front-end/html/login.html` → POST `back-end/login.php` → session cookie → redirect.
  - **Send message**: `front-end/html/chat.html` → `front-end/js/chat.js` → POST `back-end/apis/api_send.php` → DB insert.
- Write down (in your own notes) for each step:
  - Request URL + method
  - Where the session is read/written
  - What SQL runs
  - What response is returned (redirect vs JSON)

### Module B — Postman mastery (3–5 days)

- Create a Postman **Collection** for your app:
  - `Auth/Login` (POST)
  - `Auth/Logout` (GET)
  - `Me` (GET)
  - `Messages/List` (GET)
  - `Messages/Send` (POST)
- Learn cookie/session handling in Postman:
  - Use Postman’s **cookie jar** (or manually copy the `PHPSESSID` cookie) so authenticated API calls work.
  - Add tests to assert `status=200/401` and validate JSON shape.
- Add a “negative testing” folder:
  - send message without login → expect 401
  - send empty message → expect 400
  - invalid login → expect redirect + error param

### Module C — SQL deepening using your real schema (4–6 days)

- Build conceptual comfort with:
  - **Primary keys / foreign keys** (`users.id` ↔ `messages.user_id`)
  - **JOINs**: load user info with messages (even if UI doesn’t show it yet)
  - **Indexes**: why `messages(user_id, created_at)` matters
  - **Query correctness**: filtering by `user_id` is the boundary for “my messages only”
- Add the missing relationship in MySQL (so the database enforces correctness):
  - Ensure `messages.user_id` is the same type as `users.id` (typically `INT`)
  - Add an index on `messages.user_id` (typical/required for FK performance)
  - Add a foreign key with **ON DELETE CASCADE** (your choice)
  - Decide what to do with existing legacy rows where `messages.user_id` is `NULL` (keep as “pre-auth history”, delete them, or map them)
- Exercises (run in phpMyAdmin SQL tab):
  - show last 20 messages for a user
  - count messages per user
  - find users with zero messages

### Module D — Add one feature to learn concepts (3–6 days)

You chose to implement both, in this order (recommended):

- **Step 1: Conversations (multiple chats) + per-conversation auto-reply bot**
  - Goal: a user can have **many conversations**, and each conversation is assigned a **default bot user**
  (e.g., Ahmed for conversation 1, Islam for conversation 2, etc.).
  - Suggested schema:
    - `conversations(id, title, created_by_user_id, bot_user_id, created_at)`
    - `conversation_members(id, conversation_id, user_id, created_at)`
    - `messages(id, conversation_id, user_id, name, message, created_at, ...)`
  - Core concepts:
    - data modeling + normalization
    - authorization boundary: user can only access conversations they are a member of
    - joins: conversations ↔ members ↔ messages ↔ users
    - API design: list conversations, get messages by conversation, send message to a conversation
- **Step 2: Message edit/delete**
  - concepts: authorization (“only message owner can edit/delete”), status codes, idempotency.

### Module E — Backend quality & security basics (2–4 days)

- Standardize API error shape:
  - always JSON, consistent `{error: string}`
  - always set meaningful HTTP status codes
- Security checklist practice:
  - validate inputs server-side
  - avoid trusting user-sent `name` for identity (session is the source of truth)
  - understand why prepared statements are safer than string escaping

## What “good” looks like at the end

- You can explain any request as: **route → auth → validation → DB query → response**.
- You can test every endpoint in Postman including logged-in flows (cookie handling).
- You can confidently change the DB schema to add a new feature and update APIs accordingly.

## Suggested working routine (4 hours/day)

- 60–90 min: fundamentals (HTTP/session/SQL reading + small exercises)
- 90–120 min: implement one small change/feature
- 30–60 min: Postman tests + negative cases
- 10 min: write a short summary note (“what I learned today”)

