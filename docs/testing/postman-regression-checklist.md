# Phase 6 - Postman Regression Checklist

Run this checklist before/after each backend refactor.

## Auth/session

- `POST /auth/login` with valid credentials -> redirects to chat
- `GET /api/me` after login -> `200`, `loggedIn: true`
- `GET /auth/logout` -> redirects to login
- `GET /api/me` after logout -> `200`, `loggedIn: false`

## Conversations/messages

- `GET /api/conversations` while logged in -> `200`, array
- `GET /api/messages?conversation_id=<valid>` -> `200`, array
- `GET /api/messages?conversation_id=<invalid/not-member>` -> `403`

## Send/edit/delete

- `POST /api/send` with message + conversation_id -> `200`, `{success:true}`
- `POST /api/message/edit` for owned message -> `200`
- `POST /api/message/edit` for non-owned message -> `403`
- `POST /api/message/delete` for owned message -> `200`
- `POST /api/message/delete` for non-owned message -> `403`

## Negative checks

- Any `/api/*` while logged out -> `401` (except `/api/me` which may return `loggedIn:false`)
- Unknown API route, e.g. `GET /api/does-not-exist` -> `404` and `{error:"Not Found"}`
- Wrong method, e.g. `GET /api/send` -> `405`

## Learning task

For each failed check:

1. Write expected status/body
2. Write actual status/body
3. Trace flow in router (`back-end/index.php`) to find mismatch
4. Note root cause in your daily notes

