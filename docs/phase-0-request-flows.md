# Phase 0 - Request Flows and Core Terms

This file locks the mental model for this project.

## Flow A: Auth redirect flow (`POST /auth/login`)

1. Browser submits login form from `front-end/html/login.html` to `/auth/login`.
2. Apache rewrite routes request to `back-end/index.php`.
3. Router matches `auth/login` and requires `back-end/login.php`.
4. `login.php` loads `bootstrap.php`, validates credentials, writes session.
5. On success, it returns `Location: ../front-end/html/chat.html` redirect.

Key point: this is a redirect-style endpoint, not a JSON API endpoint.

## Flow B: API JSON flow (`GET /api/messages?conversation_id=...`)

1. Front-end JS (`front-end/js/chat.js`) calls `/api/messages`.
2. Apache rewrite routes request to `back-end/index.php`.
3. Router matches `api/messages` and requires `back-end/api_messages.php`.
4. `api_messages.php` checks auth and conversation membership.
5. It queries DB and returns JSON messages.

Key point: this is a JSON API endpoint with status-code-based errors.

## Vocabulary (project-specific)

- Endpoint: public route + method contract (`GET /api/messages`).
- HTTP request: one actual call to an endpoint (with query, headers, cookies).
- Entrypoint: first executed server file for incoming HTTP request (`back-end/index.php`).
- Handler: file that performs route logic (`back-end/api_messages.php`, `back-end/login.php`, etc).
- Redirect: response instructing browser to make another request (`Location` header).
- Internal routing: `index.php` selecting and requiring a handler in same request.

