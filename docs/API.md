# Simple Chat API Contract (Phase 1)

Base URL examples use `http://localhost/simple-chat`.

## Error shape

For API routes (`/api/*`), errors use:

```json
{ "error": "..." }
```

with suitable HTTP status codes (`400`, `401`, `403`, `404`, `405`, `500`).

## Auth routes (redirect style)

- `POST /auth/login` -> processes login, redirects to chat or login with error.
- `POST /auth/register` -> processes registration, redirects accordingly.
- `GET /auth/logout` -> clears session and redirects to login.

## JSON routes

- `GET /api/me`
  - `200`: `{ loggedIn: true, id, name, email }` or `{ loggedIn: false, name: null }`

- `GET /api/conversations`
  - `401` if not logged in
  - `200`: array of conversations with bot and last message data

- `GET /api/messages?conversation_id=<id>`
  - `401` if not logged in
  - `403` if not conversation member
  - `200`: array of messages

- `POST /api/send`
  - body: `message`, optional `conversation_id`, optional `name`
  - `400` if message missing
  - `401` if not logged in
  - `403` if forbidden conversation
  - `200`: `{ success: true, conversation_id }`

- `POST /api/message/edit`
  - body: `message_id`, `message`
  - `400` for invalid input
  - `401` if not logged in
  - `403` if not owner / not allowed
  - `404` if message missing
  - `200`: `{ success: true, id }`

- `POST /api/message/delete`
  - body: `message_id`
  - `400` for invalid input
  - `401` if not logged in
  - `403` if not owner / not allowed
  - `404` if message missing
  - `200`: `{ success: true, id }`

## Not found behavior

- Unknown `/api/*` routes return:
  - status `404`
  - `{ "error": "Not Found" }`

