# Back-End Development — Foundational Reference
### Built through the *simple-chat* project · Written for absolute beginners

---

> **How to use this document**
> Read it top-to-bottom the first time. After that, use the Table of Contents to jump back to any concept you want to revisit. Every term is explained when it first appears. Diagrams use plain text so they render in any Markdown viewer (VS Code, GitHub, Obsidian, etc.).

---

## Table of Contents

1. [How the Web Works](#1-how-the-web-works)
2. [HTTP — The Language of the Web](#2-http--the-language-of-the-web)
3. [What Lives on a Server?](#3-what-lives-on-a-server)
4. [Databases — Persistent Storage](#4-databases--persistent-storage)
5. [What is an API?](#5-what-is-an-api)
6. [PHP as a Back-End Language](#6-php-as-a-back-end-language)
7. [The MVC Architecture Pattern](#7-the-mvc-architecture-pattern)
8. [Design Patterns Used in This Project](#8-design-patterns-used-in-this-project)
9. [Authentication & Sessions](#9-authentication--sessions)
10. [The Middleware Pipeline](#10-the-middleware-pipeline)
11. [Feature: Edit & Delete Messages](#11-feature-edit--delete-messages)
12. [Feature: Settings & Profile Update](#12-feature-settings--profile-update)
13. [Feature: Real Users & Conversations](#13-feature-real-users--conversations)
14. [Feature: Password Reset by Email](#14-feature-password-reset-by-email)
15. [Feature: Two-Factor Authentication (2FA)](#15-feature-two-factor-authentication-2fa)
16. [Security Fundamentals](#16-security-fundamentals)
17. [Complete Application Architecture](#17-complete-application-architecture)
18. [Glossary of Terms](#18-glossary-of-terms)

---

## 1. How the Web Works

### The Client–Server Model

Every interaction on the web involves two parties:

- **Client** — the program making the request (almost always a web browser like Chrome or Firefox, but also mobile apps, scripts, etc.)
- **Server** — the program listening for requests, doing work, and sending back a response

```
┌─────────────────────────────────────────────────────────────┐
│                     THE INTERNET                            │
│                                                             │
│   ┌──────────────┐   1. REQUEST    ┌──────────────────┐    │
│   │              │ ──────────────► │                  │    │
│   │   BROWSER    │                 │     SERVER       │    │
│   │  (Client)    │ ◄────────────── │  (XAMPP/Apache)  │    │
│   │              │   2. RESPONSE   │                  │    │
│   └──────────────┘                 └──────────────────┘    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Key insight:** The browser never "connects" to the server permanently. Each action (loading a page, submitting a form, clicking a button) is a separate, independent request-response cycle. The server handles the request, sends back the answer, and immediately forgets about it — it has no built-in memory of who you are between requests. This is called being **stateless**.

> **Stateless** means: each request is handled in isolation. The server does not automatically remember that you logged in 5 seconds ago. This is why mechanisms like sessions and cookies were invented.

### What Happens When You Type a URL

```
You type: http://localhost/simple-chat/front-end/html/login.html
           │         │         │              │
           │         │         │              └─ The file path on the server
           │         │         └─ The project folder
           │         └─ The server address (localhost = your own machine)
           └─ The protocol (HTTP)
```

1. Browser looks up where `localhost` is (it's your own machine — 127.0.0.1)
2. Browser connects to port 80 on your machine (where Apache/XAMPP listens)
3. Browser sends an HTTP request: "Give me `/simple-chat/front-end/html/login.html`"
4. Apache finds the file and sends it back as an HTTP response
5. Browser renders the HTML

---

## 2. HTTP — The Language of the Web

HTTP (HyperText Transfer Protocol) is the set of rules browsers and servers use to communicate. Think of it as the grammar of their conversation.

### HTTP Methods (Verbs)

Every HTTP request has a **method** that describes the *intent* of the action:

| Method | Meaning | Real-world analogy |
|--------|---------|-------------------|
| `GET` | Read/fetch data | "Show me the menu" |
| `POST` | Create something new | "I'd like to place an order" |
| `PATCH` | Partially update existing data | "Actually, change the drink on my order" |
| `PUT` | Fully replace existing data | "Redo my whole order" |
| `DELETE` | Remove data | "Cancel my order" |

In our app:
- `GET /api/users` → fetch the list of users for the sidebar
- `POST /api/conversations` → create a new conversation
- `PATCH /api/settings` → update your profile
- `DELETE /api/messages/42` → delete message #42

### HTTP Request Structure

A raw HTTP request looks like this:

```
POST /simple-chat/api/auth/forgot-password HTTP/1.1
Host: localhost
Content-Type: application/json
Content-Length: 28

{"email":"you@example.com"}
```

Every request has three parts:
1. **Request line** — method + path + HTTP version
2. **Headers** — metadata (what type of content, authentication tokens, etc.)
3. **Body** — the data being sent (only for POST, PATCH, PUT — GET and DELETE have no body)

### HTTP Response Structure

A raw HTTP response looks like this:

```
HTTP/1.1 200 OK
Content-Type: application/json

{"message":"Reset link sent."}
```

Every response has:
1. **Status line** — HTTP version + status code + reason phrase
2. **Headers** — metadata about the response
3. **Body** — the actual content (HTML page, JSON data, image, etc.)

### Status Codes

Status codes are 3-digit numbers that tell the client what happened:

```
1xx  Informational   (rare, mostly internal)
2xx  Success
3xx  Redirect        (go look somewhere else)
4xx  Client error    (you did something wrong)
5xx  Server error    (we did something wrong)
```

| Code | Name | When we use it |
|------|------|---------------|
| `200` | OK | Successful GET, PATCH, DELETE |
| `201` | Created | Successful POST (new resource created) |
| `302` | Found (Redirect) | After login → redirect to chat |
| `400` | Bad Request | Malformed request body |
| `401` | Unauthorized | Not logged in |
| `403` | Forbidden | Logged in but not allowed |
| `404` | Not Found | Route doesn't exist |
| `422` | Unprocessable Entity | Validation failed (e.g. passwords don't match) |
| `500` | Internal Server Error | Unexpected crash on the server |

### Headers

Headers are key-value pairs that carry metadata. Important ones in our app:

```
Content-Type: application/json    ← tells the receiver the body is JSON
Content-Type: text/html           ← tells the receiver the body is HTML
```

**Why `Content-Type` matters in our app:**
PHP only automatically parses request bodies that come from HTML forms (`application/x-www-form-urlencoded`). When the front-end sends `Content-Type: application/json`, PHP ignores `$_POST` — we have to manually read and parse the body ourselves using `file_get_contents('php://input')`. This is exactly what our `index.php` does at the body-parsing step.

### JSON — The Universal Data Format

JSON (JavaScript Object Notation) is the standard format for sending data between a front-end and back-end today. It looks like this:

```json
{
  "id": 5,
  "name": "Mahmoud",
  "email": "m@example.com",
  "two_factor_enabled": false
}
```

Rules:
- Keys are always strings in double quotes
- Values can be: string, number, boolean (`true`/`false`), null, array `[]`, or nested object `{}`
- No trailing commas
- No comments

In PHP: `json_encode($array)` converts a PHP array to a JSON string. `json_decode($string, true)` converts a JSON string back to a PHP array.

---

## 3. What Lives on a Server?

### XAMPP — Your Local Server

XAMPP is a package that installs four programs on your machine:

```
XAMPP
├── Apache   ← Web server: listens on port 80, serves files, runs PHP
├── MySQL    ← Database server: stores and retrieves data
├── PHP      ← Language interpreter: runs your .php files
└── Mercury  ← Mail server: sends emails (we didn't configure this)
```

When you start XAMPP and visit `http://localhost/simple-chat/`, Apache receives the request. If the file is a `.php` file, Apache hands it to the PHP interpreter. PHP executes the code, produces output (usually HTML or JSON), and Apache sends that output back to the browser.

```
Browser Request
      │
      ▼
   Apache
      │
      ├─── Static file (.html, .css, .js, .png)?
      │         └──► Read file from disk → send to browser ✓
      │
      └─── PHP file (.php)?
                └──► Hand to PHP interpreter
                           │
                           ├── PHP reads the file
                           ├── PHP executes the code
                           ├── PHP may query the database
                           └── PHP produces output → Apache sends to browser ✓
```

### Our Project's File Structure

```
simple-chat/
├── .htaccess                    ← Apache rewrite rules (URL routing)
├── front-end/
│   ├── html/                    ← HTML pages (what the browser renders)
│   │   ├── login.html
│   │   ├── register.html
│   │   ├── chat.html
│   │   ├── settings.html
│   │   ├── forgot-password.html
│   │   ├── reset-password.html
│   │   └── two-factor.html
│   ├── css/                     ← Stylesheets
│   └── js/                      ← JavaScript (runs in the browser)
│       ├── api-client.js        ← Shared URL definitions + fetchJson helper
│       ├── chat.js
│       ├── settings.js
│       ├── forgot-password.js
│       ├── reset-password.js
│       └── two-factor.js
└── back-end/
    ├── index.php                ← Single entry point (the router)
    ├── bootstrap.php            ← App startup (DB, logger, helpers)
    ├── config.php               ← Database credentials
    ├── auth.php                 ← Session helpers (isLoggedIn, etc.)
    ├── .htaccess                ← Forces all API requests through index.php
    ├── core/                    ← Shared utilities
    │   ├── Logger.php
    │   ├── Mailer.php
    │   └── Totp.php
    ├── entities/                ← Plain data objects (User, Message, Bot)
    ├── dto/                     ← Response-shaped objects (MeResponse, etc.)
    ├── repositories/            ← Database access layer
    ├── services/                ← Business logic layer
    ├── controllers/             ← HTTP layer (read request, call service, send response)
    ├── middleware/              ← Pre-processing (auth check, JSON headers)
    ├── http/                    ← Request/Pipeline abstractions
    └── migrations/              ← Versioned SQL files
```

---

## 4. Databases — Persistent Storage

### Why Do We Need a Database?

Without a database, all data lives in memory (RAM). When the PHP script finishes, all variables are gone. The database is persistent storage — data survives restarts, power cuts, and new requests.

### Relational Databases

MySQL (used in our app) is a **relational database**. Data is stored in **tables** — like spreadsheets — where each row is a record and each column is a field.

**Example: the `users` table**

```
┌────┬──────────────┬───────────────────────┬──────────────────────────────┐
│ id │ name         │ email                 │ password_hash                │
├────┼──────────────┼───────────────────────┼──────────────────────────────┤
│  1 │ Alice        │ alice@example.com     │ $2y$10$abc...                │
│  2 │ Bob          │ bob@example.com       │ $2y$10$xyz...                │
│  3 │ MG Studio22  │ mgabal@calibtos.com   │ $2y$10$def...                │
└────┴──────────────┴───────────────────────┴──────────────────────────────┘
```

The `id` column is the **primary key** — a unique identifier for every row. The database auto-increments this number for each new record.

**Relationships between tables**

Tables are connected through **foreign keys** — a column in one table that stores the `id` from another table.

```
users                          messages
┌────┬──────────┐              ┌────┬─────────────────┬──────────┬─────────────┐
│ id │ name     │              │ id │ content         │ user_id  │ conv_id     │
├────┼──────────┤              ├────┼─────────────────┼──────────┼─────────────┤
│  1 │ Alice    │◄─────────────│  1 │ Hello Bob!      │    1     │      3      │
│  2 │ Bob      │◄─────────────│  2 │ Hi Alice!       │    2     │      3      │
└────┴──────────┘              └────┴─────────────────┴──────────┴─────────────┘
                                                             │
                               conversations                 │
                               ┌────┬──────────┐            │
                               │ id │ bot_id   │◄───────────┘
                               ├────┼──────────┤
                               │  3 │ NULL     │  ← user-to-user (no bot)
                               └────┴──────────┘
```

### SQL — The Database Language

SQL (Structured Query Language) is how you talk to a relational database. Key operations:

```sql
-- Read all users
SELECT * FROM users;

-- Read one user by email
SELECT * FROM users WHERE email = 'alice@example.com';

-- Insert a new user
INSERT INTO users (name, email, password_hash) VALUES ('Alice', 'a@b.com', '$2y...');

-- Update a user's name
UPDATE users SET name = 'Alicia' WHERE id = 1;

-- Delete a user
DELETE FROM users WHERE id = 1;
```

### Prepared Statements — The Safe Way to Query

Never put user input directly into an SQL string. This is called **SQL injection** and is one of the most dangerous security vulnerabilities:

```php
// ❌ DANGEROUS — SQL injection vulnerability
$query = "SELECT * FROM users WHERE email = '$email'";
// If $email is: ' OR '1'='1  → logs in as ANY user

// ✅ SAFE — prepared statement
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param('s', $email);   // 's' = string type
$stmt->execute();
```

With prepared statements, the database receives the SQL structure separately from the data. No matter what the user types, it can never change the query structure.

### Migrations — Versioning Your Database Schema

A **migration** is a SQL file that describes a change to the database structure (adding columns, creating tables, etc.). Instead of manually editing the database through phpMyAdmin every time, you write it as a file that can be:
- Run on any machine
- Tracked in version control (Git)
- Run in order (001, 002, 003...)

Our migrations:
- `001_...sql` — created users, conversations, messages, bots tables
- `002_settings_and_user_chat.sql` — added reset_token, 2FA columns, made bot_id nullable

---

## 5. What is an API?

### API — Application Programming Interface

An API is a contract. It says: "if you send me a request in this format, I will send you back a response in this format." It's the defined communication channel between your front-end (browser) and back-end (server).

### REST — The Most Common API Style

REST (Representational State Transfer) is a set of conventions for designing APIs. Our app follows REST:

1. **Resources** are nouns: `/api/users`, `/api/messages`, `/api/conversations`
2. **Actions** are HTTP methods: `GET` to read, `POST` to create, `PATCH` to update, `DELETE` to delete
3. **Responses** are in JSON
4. **Stateless** — each request carries all info needed (session cookie handles identity)

```
REST Endpoint Design:

GET    /api/users              → list all users
POST   /api/conversations      → create a conversation
GET    /api/messages           → list messages
PATCH  /api/messages/42        → edit message 42
DELETE /api/messages/42        → delete message 42
GET    /api/settings           → get my settings
PATCH  /api/settings           → update my settings
```

### The Single Entry Point Pattern

In traditional PHP, every URL maps to a separate .php file:
```
/login.php
/register.php
/messages.php
```

Our app uses a **single entry point**: every API request goes through `back-end/index.php`. The `.htaccess` file redirects all requests to this one file, which then routes them internally:

```
Browser: POST /simple-chat/api/auth/forgot-password
          │
          ▼
    Apache reads .htaccess
          │
          ▼
    back-end/index.php (the router)
          │
          ├── Parses the URL path: "api/auth/forgot-password"
          ├── Looks up the route table
          ├── Finds: POST api/auth/forgot-password → AuthController@forgotPassword
          └── Calls that controller method
```

**Why this matters:** It gives you one place to handle cross-cutting concerns — logging, authentication checks, JSON headers — instead of copy-pasting that code into every file.

---

## 6. PHP as a Back-End Language

### How PHP Executes

PHP is a **server-side** language. It runs on the server, not in the browser. The browser never sees PHP code — it only receives the output PHP produces.

```
PHP file (server-side):               Browser receives:
┌────────────────────────┐            ┌────────────────────────┐
│ <?php                  │            │ {                      │
│   $name = "Alice";     │  ───────►  │   "name": "Alice",     │
│   echo json_encode([   │            │   "id": 1              │
│     'name' => $name,   │            │ }                      │
│     'id'   => 1,       │            └────────────────────────┘
│   ]);                  │
│ ?>                     │
└────────────────────────┘
```

### PHP and Sessions

Sessions solve the stateless problem. When you log in, the server:
1. Creates a session — a temporary storage area on the server's disk
2. Gives it a unique ID (a long random string)
3. Sends that ID to the browser as a cookie (`PHPSESSID`)

On every subsequent request, the browser automatically includes that cookie. The server reads the cookie, finds the session, and knows who you are.

```
LOGIN REQUEST:
Browser ──── POST /auth/login {email, password} ────► Server
             ◄─── Set-Cookie: PHPSESSID=abc123 ──────
             ◄─── Location: chat.html ────────────────

NEXT REQUEST:
Browser ──── GET /api/users (Cookie: PHPSESSID=abc123) ────► Server
             Server reads session abc123 → {user_id: 3} ✓
             ◄───── 200 OK {users: [...]} ──────────────────
```

In PHP, the session is accessed via the `$_SESSION` superglobal array:
```php
session_start();          // Must be called before using $_SESSION

// On login:
$_SESSION['user_id'] = 3;

// On any later request:
$userId = $_SESSION['user_id'];   // Still there!

// On logout:
session_destroy();
```

### PHP Superglobals

PHP has several built-in variables that are always available:

| Variable | Contains |
|----------|---------|
| `$_GET` | URL query parameters (`?id=5` → `$_GET['id']` = `'5'`) |
| `$_POST` | Form-submitted data (only for `Content-Type: application/x-www-form-urlencoded`) |
| `$_SESSION` | Session data for the current user |
| `$_SERVER` | Server/request info (`$_SERVER['REQUEST_METHOD']`, `$_SERVER['CONTENT_TYPE']`) |
| `$_COOKIE` | Cookies sent by the browser |

---

## 7. The MVC Architecture Pattern

### What is an Architecture Pattern?

An architecture pattern is a proven way to organize your code so that:
- Each piece of code has one clear responsibility
- You can find where to make a change without reading the whole codebase
- You can change one layer without breaking others

### MVC: Model — View — Controller

MVC divides your application into three layers:

```
┌─────────────────────────────────────────────────────────────────────┐
│                           MVC PATTERN                               │
│                                                                     │
│   ┌─────────────┐     ┌─────────────────┐     ┌────────────────┐  │
│   │             │     │                 │     │                │  │
│   │    VIEW     │     │   CONTROLLER    │     │     MODEL      │  │
│   │             │     │                 │     │                │  │
│   │  What the   │◄────│  Traffic cop.   │────►│  Data & rules. │  │
│   │  user sees  │     │  Reads request, │     │  Talks to DB.  │  │
│   │  (HTML/JSON)│     │  calls model,   │     │  Validates.    │  │
│   │             │     │  sends response │     │                │  │
│   └─────────────┘     └─────────────────┘     └────────────────┘  │
│                                │                                    │
│                                │                                    │
│                         HTTP Request in                             │
└─────────────────────────────────────────────────────────────────────┘
```

- **View** — what is returned to the browser. In our app this is JSON (for APIs) or HTML files (for pages). The browser turns this into what the user sees.
- **Controller** — receives the HTTP request, extracts data from it, calls the right model/service, and builds the response. It knows *nothing* about SQL.
- **Model** — everything about data: reading from the database, validating business rules, manipulating data. It knows *nothing* about HTTP.

### How Our App Maps to MVC

Our app uses a refined version of MVC with more layers for cleaner separation:

```
HTTP Request
     │
     ▼
┌────────────┐
│ index.php  │ ← Router: "who should handle this?"
└────────────┘
     │
     ▼
┌────────────────┐
│  Middleware    │ ← Pre-processing: "is the user logged in? set JSON headers?"
└────────────────┘
     │
     ▼
┌────────────────┐
│  Controller    │ ← HTTP layer: read request body, call service, send JSON
└────────────────┘
     │
     ▼
┌────────────────┐
│   Service      │ ← Business logic: "is this email valid? does this token exist?"
└────────────────┘
     │
     ▼
┌────────────────┐
│  Repository    │ ← Database access: SQL queries only
└────────────────┘
     │
     ▼
┌────────────────┐
│    MySQL       │ ← The actual database
└────────────────┘
```

**Why so many layers?**

Imagine the settings feature. The business rule "you can't change your email to one that another user already has" — where does it live?

- Not in the Controller (it only does HTTP stuff)
- Not in the Repository (it only does SQL stuff)
- It lives in the **Service** — the business logic layer

This separation means: if you change your database from MySQL to PostgreSQL, you only rewrite the Repository. The Controller and Service don't change at all.

---

## 8. Design Patterns Used in This Project

### The Repository Pattern

A **repository** is a class whose only job is to talk to the database. It hides all SQL behind simple method names:

```php
// Without repository — SQL scattered everywhere:
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
// ... repeated in 10 different places

// With repository — SQL in one place:
$user = $userRepo->findById($id);  // clean, readable, reusable
```

Our repositories:
- `UserRepository` — all user-related SQL (find by email, update password, set 2FA secret...)
- `ConversationRepository` — all conversation SQL
- `MessageRepository` — all message SQL
- `BotRepository` — all bot SQL

### Dependency Injection

**Dependency Injection** (DI) means: instead of a class creating its own dependencies, you pass them in from outside.

```php
// ❌ Without DI — hard to test, tightly coupled:
class AuthService {
    public function login($email, $password) {
        $conn = new mysqli('localhost', 'root', '', 'chat_db');  // creates its own DB
        // ...
    }
}

// ✅ With DI — testable, flexible:
class AuthService {
    public function __construct(private UserRepository $users) {}
    // UserRepository is "injected" from outside
    // Tests can inject a fake repository
}
```

In our `index.php` dispatch section:
```php
$userRepo = new UserRepository($conn);          // create repository
$ctrl = new AuthController(
    new AuthService($userRepo),                 // inject into service
    new PasswordResetService($userRepo),        // inject into another service
    $userRepo,                                  // inject directly too
);
```

### Entities vs DTOs

**Entity** — represents a row in the database. Maps 1-to-1 with a table:
```php
class User {
    public int $id;
    public string $name;
    public string $email;
    public string $password_hash;  // ← includes sensitive data
}
```

**DTO (Data Transfer Object)** — represents what you send in a response. Shaped for the API consumer, never includes sensitive data:
```php
class MeResponse {
    public int $id;
    public string $name;
    public string $email;
    // NO password_hash — never expose this!
}
```

The pattern: Repository returns Entities (full DB row). Service or Controller converts to DTO before calling `json_response()`. The password hash never leaves the server.

### The Middleware Pipeline Pattern

**Middleware** is code that runs *before* your controller, wrapping every request that goes through it. Think of it as a series of checkpoints:

```
Request ──► [JsonMiddleware] ──► [AuthMiddleware] ──► Controller ──► Response
                │                      │
                │                      └── If not logged in: stop, return 401
                └── Set Content-Type: application/json header
```

Each middleware either:
- **Passes** the request to the next middleware (calls `$next($request)`)
- **Stops** the pipeline (returns an error response early)

This is called the **pipeline pattern** or **chain of responsibility**. It lets you add cross-cutting concerns (logging, authentication, rate limiting) without touching controller code.

---

## 9. Authentication & Sessions

### The Login Flow (Full Detail)

```
1. User fills login form (email + password)
   │
   ▼
2. Browser: POST /auth/login  {email: "a@b.com", password: "secret"}
   │
   ▼
3. Apache → PHP (index.php) → AuthController@login
   │
   ▼
4. AuthService::login($email, $password)
   ├── UserRepository::findByEmail($email)  → get User from DB
   ├── password_verify($password, $user->password_hash)
   │       └── Returns true/false  ← bcrypt comparison
   └── Returns User object (or null if invalid)
   │
   ▼
5. If 2FA enabled:
   │   $_SESSION['pending_2fa_user_id'] = $user->id
   │   → Redirect to two-factor.html
   │
   └── If 2FA not enabled:
       $_SESSION['user_id']    = $user->id
       $_SESSION['user_email'] = $user->email
       $_SESSION['user_name']  = $user->name
       → Redirect to chat.html
```

### Password Hashing

We **never** store plain-text passwords. If your database is stolen, hackers get hashes, not passwords.

```
Registration:
password = "mypassword123"
         ↓ password_hash($password, PASSWORD_DEFAULT)
stored  = "$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lkiS"

Login verification:
password_verify("mypassword123", "$2y$10$N9qo8uLOick...")  → true ✓
password_verify("wrongpassword", "$2y$10$N9qo8uLOick...")  → false ✗
```

**bcrypt** (the algorithm behind `PASSWORD_DEFAULT`) is intentionally slow to compute. This makes brute-force attacks impractical — even if hackers steal the hashes, computing billions of guesses takes too long.

### The `isLoggedIn()` Helper

```php
// back-end/auth.php
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}
```

This is used in:
- `AuthMiddleware` — stops API requests from unauthenticated users
- Controller redirects — `if (isLoggedIn()) { redirect to chat; }`

---

## 10. The Middleware Pipeline

### Our Two Middlewares

**JsonMiddleware** — runs for all API routes. Sets the response header so the browser knows to expect JSON:
```php
header('Content-Type: application/json');
$next($request);  // pass to next middleware or controller
```

**AuthMiddleware** — runs for all protected routes (anything requiring login):
```php
if (!isLoggedIn()) {
    json_response(['error' => 'Unauthorized'], 401);
    // Pipeline stops here — controller never executes
}
$next($request);  // user is logged in, proceed
```

### How the Pipeline Runs

In `index.php`, each route definition specifies which middlewares to apply:

```php
['method' => 'PATCH', 'pattern' => 'api/settings',
 'target' => 'SettingsController@update',
 'middleware' => ['json', 'auth']],   // ← both middlewares

['method' => 'POST', 'pattern' => 'api/auth/forgot-password',
 'target' => 'AuthController@forgotPassword',
 'middleware' => ['json']],            // ← only json (no auth needed for password reset)
```

The `Pipeline::run()` call wraps middlewares recursively, innermost being the controller itself.

---

## 11. Feature: Edit & Delete Messages

### What It Does

Users can edit their own messages (changing the text) or delete them entirely. Like WhatsApp — you see "edited" or the message disappears.

### The Flow

```
Edit Message:
Browser: PATCH /api/messages/42  {content: "corrected text"}
                                              │
                                        MessageController@edit
                                              │
                                        Verify message belongs to current user
                                              │
                                        MessageRepository::update(42, "corrected text")
                                              │
                                        UPDATE messages SET content=? WHERE id=? AND user_id=?
                                              │
                                        200 OK {id:42, content:"corrected text", edited:true}

Delete Message:
Browser: DELETE /api/messages/42
                    │
              MessageController@delete
                    │
              Verify message belongs to current user (authorization check)
                    │
              MessageRepository::delete(42)
                    │
              DELETE FROM messages WHERE id=? AND user_id=?
                    │
              200 OK {success: true}
```

### The Authorization Check

This is an important security concept: **authentication** (who are you?) is different from **authorization** (are you allowed to do this?).

```php
// MessageController.php (simplified)
public function edit(Request $request): void {
    $messageId = (int) $request->routeParams['id'];
    $userId = $_SESSION['user_id'];

    // AUTHORIZATION: can this user edit this specific message?
    $message = $this->msgRepo->findById($messageId);
    if ($message->userId !== $userId) {
        json_response(['error' => 'Forbidden'], 403);  // their message, not yours
    }

    // Only reach here if it's their own message
    $this->msgRepo->update($messageId, $newContent);
    json_response(['success' => true]);
}
```

Without this check, any logged-in user could edit or delete anyone else's messages just by guessing the message ID.

### Front-End Side

In `chat.js`, each message bubble renders with edit/delete buttons that are **only visible on hover** for messages sent by the current user:

```javascript
// Only show controls for your own messages
if (msg.userId === currentUserId) {
    // Render edit pencil icon + delete trash icon
}

// Edit: show an inline text input, PATCH on save
// Delete: show confirm dialog, DELETE on confirm
```

---

## 12. Feature: Settings & Profile Update

### What It Does

Users can update their display name and email address from a dedicated settings page.

### The PATCH Endpoint

`PATCH` is used instead of `POST` because we are *partially updating* an existing resource (the user's profile), not creating a new one.

### Full Request Flow

```
1. User opens settings.html
   │
   ▼
2. Browser: GET /api/settings  (with session cookie)
   │
   ▼
3. AuthMiddleware: session check ✓
   │
   ▼
4. SettingsController@show
   ├── $userId = $_SESSION['user_id']
   ├── UserRepository::findById($userId)
   └── json_response(SettingsResponse from User)
   │
   ▼
5. Browser renders the form pre-filled with current name + email

─── User edits name, clicks Save ───────────────────────────────

6. Browser: PATCH /api/settings  {"name": "New Name", "email": "new@email.com"}
   │
   ▼
7. SettingsController@update
   │
   ▼
8. SettingsService::update($userId, $name, $email)
   ├── Validate: name not empty, email valid format
   ├── UserRepository::emailTakenByOther($email, $userId)
   │       → SELECT id FROM users WHERE email=? AND id != ?
   │       → If another user has this email → throw exception
   ├── UserRepository::updateProfile($userId, $name, $email)
   │       → UPDATE users SET name=?, email=? WHERE id=?
   ├── Refresh session values (important! session data is stale after DB update)
   │       $_SESSION['user_name']  = $name
   │       $_SESSION['user_email'] = $email
   └── Return SettingsResponse (the new values)
   │
   ▼
9. json_response(200, {name: "New Name", email: "new@email.com"})
   │
   ▼
10. settings.js shows success message, updates sidebar name
```

### Why Refresh the Session?

When you update the database, the session is *not* automatically updated. It's a separate storage. If you don't refresh it, the next request to `/api/me` returns the old name from the session, even though the DB has the new one. Refreshing `$_SESSION['user_name']` immediately after updating the DB keeps them in sync.

### The Email Uniqueness Check (Excluding Self)

```sql
SELECT id FROM users WHERE email = ? AND id != ?
```

The `AND id != ?` is critical. Without it:
- You open settings, your email is `alice@example.com`
- You change only your name, keep the same email
- Server checks: "is `alice@example.com` taken?" → Yes, by YOU
- Server incorrectly rejects it → you can never save anything

By excluding your own `id` from the check, the server only returns an error if *someone else* has that email.

---

## 13. Feature: Real Users & Conversations

### What Changed

Originally, the sidebar showed bots. Every "conversation" had a `bot_id` (NOT NULL) — it required a bot. The bots would auto-reply with random messages.

After this phase:
- The sidebar shows real registered users
- Clicking a user starts (or reopens) a direct conversation with them
- No bot auto-replies

### The Database Change

```sql
-- Old: bot_id required
CREATE TABLE conversations (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL  -- ← must have a bot
);

-- New: bot_id optional
ALTER TABLE conversations MODIFY COLUMN bot_id INT NULL DEFAULT NULL;
-- bot_id = NULL means "user-to-user conversation (no bot)"
-- bot_id = 5   means "conversation with bot #5"
```

Making a column **nullable** (`NULL DEFAULT NULL`) means the value can be absent. This single change allows the same `conversations` table to serve both bot conversations and user conversations.

### The conversation_members Table

The key table that tracks who is in which conversation:

```
conversation_members
┌─────────────────┬─────────┐
│ conversation_id │ user_id │
├─────────────────┼─────────┤
│       3         │    1    │  ← Alice is in conversation 3
│       3         │    2    │  ← Bob is in conversation 3
└─────────────────┴─────────┘
```

This is a **many-to-many** relationship: one conversation can have many users, one user can be in many conversations.

### Find-or-Create Pattern

When Alice clicks on Bob, we don't want to create a new conversation every time. We use the "find-or-create" pattern:

```
Alice clicks Bob:
      │
      ▼
POST /api/conversations  {"user_id": 2}
      │
      ▼
ConversationService::getOrCreateWithUser(aliceId=1, bobId=2)
      │
      ▼
ConversationRepository::findBetweenUsers(1, 2)
      │
      ▼
  SQL query:
  SELECT c.id FROM conversations c
  JOIN conversation_members m1 ON m1.conversation_id = c.id AND m1.user_id = 1
  JOIN conversation_members m2 ON m2.conversation_id = c.id AND m2.user_id = 2
  WHERE c.bot_id IS NULL
  LIMIT 1;
      │
      ├── Found? → Return existing conversation ID ✓
      │
      └── Not found? → createUserConversation(1, 2)
               │
               ▼
           BEGIN TRANSACTION
           INSERT INTO conversations (bot_id) VALUES (NULL)  → new id = 7
           INSERT INTO conversation_members (conversation_id, user_id) VALUES (7, 1)
           INSERT INTO conversation_members (conversation_id, user_id) VALUES (7, 2)
           COMMIT
               │
               └── Return new conversation ID = 7
```

### Transactions — All or Nothing

The three INSERT statements above are wrapped in a **transaction**:

```php
$conn->begin_transaction();
try {
    // insert conversation
    // insert member 1
    // insert member 2
    $conn->commit();   // all three succeed → permanent
} catch (Exception $e) {
    $conn->rollback(); // any one fails → undo all three
}
```

Without a transaction, if the server crashed after inserting the conversation but before inserting the members, you'd have an orphaned conversation with no members. Transactions guarantee **atomicity** — either everything happens, or nothing does.

### Removing Bot Auto-Replies

In `MessageService::send()`, the old code unconditionally generated a bot reply after every message. Now it checks:

```php
// New conditional logic:
if ($conversation->botId !== null) {
    // It's a bot conversation — generate bot reply
    $botReply = $this->generateBotReply($botId);
}
// If botId is NULL, it's user-to-user — no auto-reply
```

---

## 14. Feature: Password Reset by Email

### The Problem It Solves

Users forget passwords. You can't just send them their password (it's hashed — you don't know it). Instead, you send them a temporary, single-use, time-limited link that lets them set a new one.

### The Token Concept

A **token** is a long, random, unguessable string stored in the database, linked to a user. Knowing the token proves you have access to the email address it was sent to.

```
Token properties:
- Random: bin2hex(random_bytes(32)) → 64 hex characters
- Stored in DB: reset_token column on the user's row
- Time-limited: reset_token_expires = NOW + 15 minutes
- Single-use: cleared from DB immediately after use
```

### The Complete Flow

```
STEP 1 — Request Reset
─────────────────────
User visits forgot-password.html
        │
        ▼
Enters email → clicks "Send reset link"
        │
        ▼
POST /api/auth/forgot-password  {"email": "user@example.com"}
        │
        ▼
PasswordResetService::requestReset($email)
        ├── UserRepository::findByEmail($email)
        │         → SELECT * FROM users WHERE email = ?
        │
        ├── If user found:
        │     token = bin2hex(random_bytes(32))         → "a1b2c3..."
        │     expiry = date('Y-m-d H:i:s', time()+900)  → "2026-04-04 14:30:00"
        │     UserRepository::setResetToken($userId, $token, $expiry)
        │         → UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?
        │     Mailer::send($email, "Reset your password", $resetUrl)
        │         → PHPMailer connects to Zoho SMTP → email delivered to recipient
        │
        └── Whether user found or not:
              json_response(["message" => "If that email is registered..."])
              ← ALWAYS same response (prevents user enumeration)

STEP 2 — User Clicks the Link
──────────────────────────────
User opens:
http://localhost/simple-chat/front-end/html/reset-password.html
  ?token=a1b2c3d4e5f6...
         │
         ▼
reset-password.js reads ?token= from URL
        │
        ▼
User enters new password + confirm, clicks "Update password"
        │
        ▼
POST /api/auth/reset-password
  {"token": "a1b2c3...", "password": "newpass", "password_confirm": "newpass"}
        │
        ▼
PasswordResetService::resetPassword($token, $password, $confirm)
        ├── Validate password length ≥ 6
        ├── Validate password === confirm
        ├── UserRepository::findByResetToken($token)
        │     → SELECT * FROM users WHERE reset_token = ?
        │         AND reset_token_expires > NOW()     ← expiry check!
        │
        ├── If not found or expired → throw InvalidArgumentException
        │
        ├── hash = password_hash($newPassword, PASSWORD_DEFAULT)
        ├── UserRepository::updatePassword($userId, $hash)
        │     → UPDATE users SET password_hash = ? WHERE id = ?
        └── UserRepository::clearResetToken($userId)
              → UPDATE users SET reset_token=NULL, reset_token_expires=NULL WHERE id=?
              ← Token is now gone. Link is dead. Can't be used again.
        │
        ▼
json_response(["message" => "Password updated. You can now log in."])
        │
        ▼
reset-password.js redirects to login.html after 2.5 seconds
```

### How Mailer.php Sends Emails — SMTP

When `Mailer::send()` is called, it doesn't use PHP's built-in `mail()` function. It uses **PHPMailer** — a library that speaks the SMTP protocol directly.

**What is SMTP?**

SMTP (Simple Mail Transfer Protocol) is the language email servers use to talk to each other. It is a text-based dialogue over a TCP connection:

```
Our server → Zoho: "Hello, I want to send an email"
Zoho:              "Who are you?"
Our server → Zoho: "mahmoud.gabal@calibtos.com + password"
Zoho:              "Authenticated ✓"
Our server → Zoho: "FROM: mahmoud.gabal@calibtos.com"
Our server → Zoho: "TO: ferhat@calibtos.com"
Our server → Zoho: "SUBJECT: Reset your Chatty password"
Our server → Zoho: "...body..."
Zoho:              "Message accepted. Delivering."
```

Every email you've ever sent went through a conversation like this — SMTP has worked this way since the 1980s.

**How PHPMailer fits in:**

PHPMailer hides all that SMTP dialogue behind simple PHP calls:

```php
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host     = 'smtppro.zoho.eu';
$mail->Port     = 465;
$mail->Username = 'mahmoud.gabal@calibtos.com';
$mail->Password = '...';
$mail->setFrom('mahmoud.gabal@calibtos.com', 'Chatty');
$mail->addAddress($to);
$mail->Subject  = 'Reset your Chatty password';
$mail->Body     = 'Click this link...';
$mail->send();   // ← PHPMailer handles the full SMTP dialogue internally
```

We downloaded PHPMailer manually (3 files: `PHPMailer.php`, `SMTP.php`, `Exception.php`) into `back-end/core/phpmailer/`.

**The configuration chain:**

Credentials are never hardcoded in the source. They flow like this:

```
config.local.php                       ← real credentials (NOT in Git)
       │  (sets $smtpHost, $smtpPort, $smtpUser, $smtpPass...)
       ▼
config.php                             ← normalizes with getenv() fallback
       │  $smtpHost = getenv('CHAT_SMTP_HOST') ?: ($smtpHost ?? 'sandbox...');
       ▼
global scope (PHP variables)           ← now available anywhere via global $smtpHost;
       │
       ▼
Mailer::send()                         ← declares "global $smtpHost, $smtpPort..." to read them
```

The `getenv()` fallback means the same code works in both development (reads from `config.local.php`) and production (reads from server environment variables — no secrets in files).

**The dev redirect:**

During development you might not want to accidentally email real users while testing. The redirect sends all emails to your own inbox regardless of who the recipient is:

```php
// config.local.php — add this line during development:
$smtpDevRedirect = 'mahmoud.gabal@calibtos.com';

// Mailer.php reads it:
if (!empty($smtpDevRedirect)) {
    $to = $smtpDevRedirect;   // override — all mail goes here
}
```

Remove that line (or leave it empty) when you're ready to send to real recipients.

**Port and encryption:**

| Port | Encryption | What happens |
|------|-----------|-------------|
| `587` | STARTTLS | Connects plain, then upgrades to encrypted mid-connection |
| `465` | SMTPS (SSL) | Encrypted from the very first byte |

We use port 465 (Zoho's SSL port). PHPMailer picks the right mode automatically:

```php
$mail->SMTPSecure = $smtpPort === 465
    ? PHPMailer::ENCRYPTION_SMTPS      // ← SSL, port 465
    : PHPMailer::ENCRYPTION_STARTTLS;  // ← STARTTLS, port 587
```

**Character encoding:**

Email bodies can contain special characters — em dashes, accented letters, Arabic script. Without declaring the encoding, the mail server guesses wrong and characters like `—` arrive as `â`.

```php
$mail->CharSet = PHPMailer::CHARSET_UTF8;
```

This tells the SMTP server "the body is UTF-8" and the receiving mail client renders it correctly.

---

### Security Properties of This Design

| Property | How we achieve it |
|---|---|
| Can't guess the token | `random_bytes(32)` = 256 bits of randomness |
| Token expires | `reset_token_expires > NOW()` check in SQL |
| Token is single-use | Cleared from DB immediately after successful reset |
| Can't enumerate users | Always respond with the same message whether email exists or not |
| Old password is gone | bcrypt hash is replaced, old hash is deleted |

### Output Buffering — The PHP Warning Problem

On XAMPP, `display_errors = On` is set in `php.ini` by default (useful for development — errors appear on screen). The problem: if PHP produces any warning before your JSON response, it gets mixed into the response body:

```
Warning: Some PHP notice about something...
{"message":"If that email..."}
```

The browser receives this mixed content. `JSON.parse()` fails on it → `data` is `null`. Calling `data.message` → TypeError.

This was the original bug when the password-reset endpoint was returning `null` instead of the response object. We discovered it because `forgot-password.js` was crashing with *"Cannot read properties of null (reading 'message')"*.

**Fix:** Output buffering in `index.php`:
```php
ob_start();  // Start capturing all output

// ... all the code runs ...

// In json_response():
ob_end_clean();    // Discard anything buffered (the PHP warning)
echo json_encode($data);  // Send only our clean JSON
```

`ob_start()` / `ob_end_clean()` is like recording a video but deleting the recording before broadcast — warnings are captured but never sent to the client.

---

## 15. Feature: Two-Factor Authentication (2FA)

### What 2FA Solves

A password alone can be stolen (phishing, data breach). 2FA adds a **second factor** — something you *have* (your phone) in addition to something you *know* (your password). Even if your password is stolen, the attacker needs your phone too.

### TOTP — How It Works

TOTP (Time-based One-Time Password, RFC 6238) is the algorithm behind Google Authenticator, Authy, etc. It generates a 6-digit code that changes every 30 seconds.

The key insight: **the server and your phone share a secret key**. Both independently run the same algorithm on the same input (secret + current time) and get the same output. No network communication needed during verification.

```
SHARED SECRET: "JBSWY3DPEHPK3PXP"  (stored in DB + in authenticator app)

Every 30 seconds:

Server calculates:                    Phone calculates:
  secret + time_window                  secret + time_window
       │                                       │
       ▼                                       ▼
  HMAC-SHA1 hash                         HMAC-SHA1 hash
       │                                       │
       ▼                                       ▼
  Dynamic truncation                    Dynamic truncation
       │                                       │
       ▼                                       ▼
     "482 391"          ===                 "482 391"   ✓
```

### HMAC-SHA1 and Dynamic Truncation (Simplified)

```
Input: secret_bytes + 8_byte_counter (current 30-second window number)
       └── counter = floor(current_unix_timestamp / 30)

Step 1: HMAC-SHA1(secret_bytes, counter_bytes)
        → 20-byte hash: [a1 f3 7c 2b 9d 0e 4a 8f 1c 3d 7e 5b 9a 2f 6c 0d 4e 8b 3f 7a]

Step 2: Dynamic truncation
        → Look at last byte's lower 4 bits → offset = 7
        → Take 4 bytes starting at offset 7
        → Convert to 31-bit integer

Step 3: Take modulo 1,000,000
        → 6-digit code: "482391"
```

Our `Totp.php` implements all of this in pure PHP without any libraries.

### The QR Code

The QR code encodes a URL in the `otpauth://` scheme:

```
otpauth://totp/Chatty:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=Chatty
```

When scanned, the authenticator app:
1. Extracts the secret
2. Stores it permanently
3. Uses it to generate TOTP codes forever

We use a third-party QR code rendering service (just for display — the secret never changes):
```
https://api.qrserver.com/v1/create-qr-code/?data=otpauth://...
```

### The Full 2FA Setup Flow

```
SETUP (one-time):
──────────────────
Settings page loads → GET /api/2fa/status
        │
        ▼
TwoFactorController@status
  → {enabled: false, secret: null}
  → Show "Enable 2FA" button

User clicks "Enable 2FA":
        │
        ▼
GET /api/2fa/setup
        │
        ▼
TwoFactorService::setup($userId, $userEmail)
  ├── secret = Totp::generateSecret()
  │       → base32_encode(random_bytes(20))
  │       → "JBSWY3DPEHPK3PXP"
  ├── UserRepository::setTwoFactorSecret($userId, $secret)
  │       → UPDATE users SET two_factor_secret=? WHERE id=?
  │       (NOT yet enabled — just stored)
  └── return {secret, qrUrl}
        │
        ▼
settings.js shows QR code image
User scans with authenticator app

User enters 6-digit code, clicks "Verify & Enable":
        │
        ▼
POST /api/2fa/enable  {"code": "482391"}
        │
        ▼
TwoFactorService::enable($userId, $code)
  ├── UserRepository::getTwoFactorInfo($userId)
  │       → {secret: "JBSWY...", enabled: false}
  ├── Totp::verify($secret, $code)
  │       → checks T-1, T, T+1 windows (clock drift tolerance)
  │       → if wrong: throw InvalidArgumentException
  └── UserRepository::enableTwoFactor($userId)
          → UPDATE users SET two_factor_enabled=1 WHERE id=?
        │
        ▼
json_response({success: true})
Settings page shows "2FA is ON" state
```

### The Login Flow With 2FA

```
LOGIN WITH 2FA ENABLED:
────────────────────────
POST /auth/login {email, password}
        │
        ▼
AuthService::login() → validates credentials → returns User ✓
        │
        ▼
AuthController@login checks 2FA:
  $twoFa = UserRepository::getTwoFactorInfo($user->id)
  if ($twoFa['enabled']) {
      $_SESSION['pending_2fa_user_id'] = $user->id;
      // NOT setting $_SESSION['user_id'] yet!
      // User is NOT logged in — just "pending"
      redirect to two-factor.html
  }
        │
        ▼
User sees 2FA code entry page
        │
        ▼
Enters 6-digit code → POST /api/auth/2fa-verify {"code": "482391"}
        │
        ▼
TwoFactorService::verifyLogin($code)
  ├── $userId = $_SESSION['pending_2fa_user_id']  ← the "pending" state
  ├── If not set: throw (didn't go through login first)
  ├── Get user's 2FA secret from DB
  ├── Totp::verify($secret, $code) ← check the code
  ├── On success:
  │     $_SESSION['user_id']    = $userId   ← NOW fully logged in
  │     $_SESSION['user_name']  = $user->name
  │     $_SESSION['user_email'] = $user->email
  │     unset($_SESSION['pending_2fa_user_id'])  ← clean up pending state
  └── Return User
        │
        ▼
json_response({success: true})
two-factor.js redirects to chat.html
```

### The ±1 Window Clock Drift

TOTP codes are valid for 30 seconds. If your phone's clock is 15 seconds slow, the code it shows might be for the *previous* window. Our `verify()` function checks three windows:

```php
foreach ([-1, 0, 1] as $drift) {
    $counter = floor(time() / 30) + $drift;
    if ($this->generateCode($secret, $counter) === $code) {
        return true;  // valid
    }
}
return false;
```

`T-1` (previous window), `T` (current), `T+1` (next) — this allows up to ~30 seconds of clock drift.

### base32 Encoding

The 2FA secret is stored and displayed in **base32** (not base64 or hex). Base32 uses only uppercase letters A-Z and digits 2-7. This is because authenticator apps use base32, and the standard (RFC 4648) requires it. It's also easier for humans to type manually if needed (no confusing 0/O or 1/l/I characters).

---

## 16. Security Fundamentals

### The OWASP Top 10 — Problems We Address

OWASP (Open Web Application Security Project) publishes the most common web security vulnerabilities. Here's which ones we handle:

#### SQL Injection
**What it is:** Attacker injects SQL code through user input to manipulate queries.
**Our defense:** Prepared statements everywhere in repositories. User input never touches SQL strings.

#### Broken Authentication
**What it is:** Weak passwords, session fixation, credential stuffing.
**Our defenses:**
- bcrypt hashing (slow, salted)
- Session ID regenerated on login (`session_regenerate_id()`)
- 2FA as optional second factor

#### Sensitive Data Exposure
**What it is:** Exposing passwords, tokens, private data in responses.
**Our defenses:**
- DTOs never include `password_hash`
- Reset tokens cleared after use
- Tokens logged only to server-side `app.log`, never sent to browser in response

#### Broken Access Control
**What it is:** Users accessing/modifying other users' data.
**Our defenses:**
- Message edit/delete checks `user_id` matches session user
- All protected routes require auth middleware
- Settings endpoint only modifies the session user's own record

#### Security Misconfiguration
**What it is:** `display_errors=On` leaking stack traces to users.
**Our defense:** Output buffering captures PHP warnings before they reach the client; stray output logged server-side.

### Input Validation

Every piece of user input is validated before use:

```
Controller receives raw input
        │
        ▼
Trim whitespace: trim($input)
        │
        ▼
Check not empty: if ($value === '') { error }
        │
        ▼
Type-check: filter_var($email, FILTER_VALIDATE_EMAIL)
        │
        ▼
Length check: strlen($password) >= 6
        │
        ▼
Business rule: is email already taken by another user?
        │
        ▼
Only then: use the value
```

**Never trust user input.** Validate at every entry point (form fields, URL parameters, JSON body).

---

## 17. Complete Application Architecture

### High-Level Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        BROWSER (Client)                                 │
│                                                                         │
│  HTML Pages           CSS Styles         JavaScript Modules             │
│  ┌────────────┐       ┌──────────┐       ┌──────────────────────────┐  │
│  │ login.html │       │ auth.css │       │ api-client.js            │  │
│  │ chat.html  │       │ chat.css │       │  └─ API URLs + fetchJson  │  │
│  │ settings.. │       │settings. │       │ chat.js, settings.js,    │  │
│  │ forgot-pw  │       │  css     │       │ forgot-password.js,      │  │
│  │ reset-pw   │       └──────────┘       │ reset-password.js,       │  │
│  │ two-factor │                          │ two-factor.js            │  │
│  └────────────┘                          └──────────────────────────┘  │
└─────────────────────────────────────────────────┬───────────────────────┘
                                                  │ HTTP Requests
                                                  │ (fetch API, forms)
┌─────────────────────────────────────────────────▼───────────────────────┐
│                         SERVER (XAMPP)                                  │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                      Apache + .htaccess                          │   │
│  │    All /api/* requests → back-end/index.php                     │   │
│  └──────────────────────────────┬──────────────────────────────────┘   │
│                                 │                                       │
│  ┌──────────────────────────────▼──────────────────────────────────┐   │
│  │                    index.php (Router)                            │   │
│  │  1. Parse URL path                                               │   │
│  │  2. Parse request body (JSON or form)                            │   │
│  │  3. Match route table                                            │   │
│  │  4. Run middleware pipeline                                      │   │
│  │  5. Dispatch to controller                                       │   │
│  └──────────┬──────────────┬──────────────┬──────────────┬─────────┘   │
│             │              │              │              │              │
│      ┌──────▼──────┐ ┌────▼─────┐ ┌─────▼────┐ ┌──────▼──────┐      │
│      │AuthController│ │MeCtrl    │ │Settings  │ │Conversation │      │
│      │ login        │ │          │ │Controller│ │Controller   │      │
│      │ register     │ └────┬─────┘ └─────┬────┘ └──────┬──────┘      │
│      │ forgotPw     │      │             │             │              │
│      │ resetPw      │      │             │             │              │
│      └──────┬───────┘      │             │             │              │
│             │              │             │             │              │
│  ┌──────────▼──────────────▼─────────────▼─────────────▼──────────┐   │
│  │                        Services Layer                            │   │
│  │  AuthService  PasswordResetService  SettingsService             │   │
│  │  ConversationService  MessageService  TwoFactorService          │   │
│  └──────────────────────────────┬───────────────────────────────┘   │
│                                 │                                       │
│  ┌──────────────────────────────▼──────────────────────────────────┐   │
│  │                     Repositories Layer                           │   │
│  │  UserRepository  ConversationRepository  MessageRepository      │   │
│  │  BotRepository                                                   │   │
│  └──────────────────────────────┬───────────────────────────────┘   │
│                                 │                                       │
│  ┌──────────────────────────────▼──────────────────────────────────┐   │
│  │                     MySQL Database                               │   │
│  │  users  conversations  conversation_members  messages  bots     │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Core Utilities: Logger · Mailer · Totp                         │   │
│  │  Cross-cutting: bootstrap.php · auth.php · config.php           │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

### Database Schema

```
┌──────────────────────────────────────────────────────────────────────┐
│                         DATABASE SCHEMA                              │
│                                                                      │
│  users                              bots                            │
│  ┌─────────────────────────────┐    ┌──────────────────┐           │
│  │ id              PK INT AI   │    │ id    PK INT AI   │           │
│  │ name            VARCHAR(100)│    │ name  VARCHAR(100)│           │
│  │ email           VARCHAR(191)│    └──────────────────┘           │
│  │ password_hash   VARCHAR(255)│         │                          │
│  │ reset_token     VARCHAR(64) │         │                          │
│  │ reset_token_exp DATETIME    │         │                          │
│  │ two_factor_sec  VARCHAR(32) │         │                          │
│  │ two_factor_en   TINYINT(1)  │         │                          │
│  └──────────────┬──────────────┘         │                          │
│                 │                        │                          │
│                 │     conversations       │                          │
│                 │     ┌──────────────────▼──┐                       │
│                 │     │ id     PK INT AI     │                       │
│                 │     │ bot_id FK → bots.id  │  (nullable)           │
│                 │     └────────────┬─────────┘                       │
│                 │                  │                                  │
│  conversation_members              │                                  │
│  ┌─────────────────────────────┐   │                                  │
│  │ conversation_id  FK ────────┼───┘                                  │
│  │ user_id          FK ────────┼──────────────────────────────────┐  │
│  └─────────────────────────────┘                                   │  │
│                                                                    │  │
│  messages                          (user_id FK links here) ────────┘  │
│  ┌──────────────────────────────┐                                      │
│  │ id              PK INT AI    │                                      │
│  │ conversation_id FK → convs   │                                      │
│  │ user_id         FK → users   │                                      │
│  │ content         TEXT         │                                      │
│  │ created_at      DATETIME     │                                      │
│  └──────────────────────────────┘                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### Request Lifecycle (Complete)

```
Browser: PATCH /simple-chat/api/settings
         Header: Content-Type: application/json
         Cookie: PHPSESSID=abc123
         Body: {"name":"New Name","email":"new@email.com"}

         │
         ▼
1. Apache receives request
   .htaccess: RewriteRule → back-end/index.php

         │
         ▼
2. ob_start()  ← output buffering begins (captures any PHP warnings)
   require bootstrap.php  ← DB connection, logger, json_response() helper
   Parse URL: "api/settings"
   Parse body: Content-Type is JSON → file_get_contents('php://input') → decode

         │
         ▼
3. matchRoute('PATCH', 'api/settings', $routes)
   Found: target = 'SettingsController@update', middleware = ['json','auth']

         │
         ▼
4. Pipeline::run()
   ├── JsonMiddleware: header('Content-Type: application/json') → next()
   ├── AuthMiddleware: $_SESSION['user_id'] exists? Yes → next()
   └── Destination: SettingsController@update($request)

         │
         ▼
5. SettingsController@update
   $name  = $request->body['name']
   $email = $request->body['email']
   $userId = $_SESSION['user_id']
   → SettingsService::update($userId, $name, $email)

         │
         ▼
6. SettingsService::update
   ├── trim, validate not empty
   ├── filter_var($email, FILTER_VALIDATE_EMAIL)
   ├── UserRepository::emailTakenByOther($email, $userId)
   │   → SELECT id FROM users WHERE email=? AND id != ?
   │   → No rows → OK
   ├── UserRepository::updateProfile($userId, $name, $email)
   │   → UPDATE users SET name=?, email=? WHERE id=?
   ├── $_SESSION['user_name']  = $name   ← refresh session
   └── Return SettingsResponse {id, name, email}

         │
         ▼
7. SettingsController calls json_response($settingsResponse, 200)
   ├── ob_end_clean()  ← discard any PHP warnings
   ├── http_response_code(200)
   ├── header('Content-Type: application/json')
   └── echo json_encode({id:3, name:"New Name", email:"new@email.com"})

         │
         ▼
8. Browser receives 200 OK
   settings.js: parse JSON, show "Saved!" message, update sidebar name
```

---

## 18. Glossary of Terms

| Term | Definition |
|------|------------|
| **API** | Application Programming Interface — a defined way for two programs to communicate |
| **Atomicity** | A database property: a transaction either fully completes or fully rolls back — no partial state |
| **Authentication** | Verifying who you are (login) |
| **Authorization** | Verifying what you're allowed to do (can you edit this message?) |
| **base32** | An encoding using A-Z and 2-7 (32 characters). Used in TOTP secrets |
| **bcrypt** | A slow, salted password hashing algorithm. Intentionally slow to resist brute-force |
| **Body (HTTP)** | The data payload of an HTTP request or response |
| **Client** | The program making the request (usually a browser) |
| **Content-Type** | HTTP header declaring the format of a request/response body |
| **Controller** | The HTTP layer in MVC: reads the request, calls services, sends the response |
| **Cookie** | A small piece of data the server sets in the browser, sent back automatically on every request |
| **CORS** | Cross-Origin Resource Sharing — browser security policy controlling which domains can call your API |
| **Dependency Injection** | Passing dependencies into a class from outside rather than creating them internally |
| **DTO** | Data Transfer Object — a shaped object for carrying data between layers, often for API responses |
| **Entity** | An object that maps to a database table row |
| **Entry Point** | The single PHP file that receives all requests (our `index.php`) |
| **Foreign Key** | A column that references the primary key of another table, creating a relationship |
| **Hash** | A one-way transformation of data. Cannot be reversed to get the original. Used for passwords |
| **HMAC** | Hash-based Message Authentication Code — a keyed hash used in TOTP |
| **HTTP** | HyperText Transfer Protocol — the communication rules of the web |
| **JSON** | JavaScript Object Notation — the standard text format for exchanging data between front-end and back-end |
| **Middleware** | Code that runs before a controller, used for authentication checks, setting headers, logging |
| **Migration** | A versioned SQL file describing a change to the database schema |
| **MVC** | Model-View-Controller — an architecture pattern separating data, presentation, and logic |
| **Nullable** | A database column that allows NULL (absent value) |
| **ob_start()** | PHP output buffering — captures all output into memory instead of sending to client |
| **ORM** | Object-Relational Mapper — a library that writes SQL for you (we did it manually) |
| **PHPMailer** | A PHP library that handles the SMTP dialogue for you, so sending email is just a few method calls |
| **Pipeline** | A sequence of middleware that a request passes through in order |
| **Prepared Statement** | A parameterized SQL query that separates structure from data, preventing SQL injection |
| **Primary Key** | A unique identifier for each row in a database table, usually `id` |
| **Repository** | A class that encapsulates all database access for one entity type |
| **REST** | Representational State Transfer — a style for designing APIs using HTTP verbs and resource URLs |
| **Router** | Code that maps incoming URL + method to the right controller function |
| **Salt** | Random data added to a password before hashing so identical passwords produce different hashes |
| **Server** | The program that listens for and responds to requests |
| **SMTP** | Simple Mail Transfer Protocol — the standard text-based protocol email servers use to deliver messages to each other |
| **SMTPS** | SMTP over SSL — encrypted from the first byte, used on port 465 |
| **STARTTLS** | An upgrade mechanism that starts a plain connection then switches it to encrypted, used on port 587 |
| **Service** | The business logic layer in MVC — validates rules, orchestrates repositories |
| **Session** | Server-side storage associated with a browser via a cookie, used to remember logged-in users |
| **SQL** | Structured Query Language — the language for querying relational databases |
| **SQL Injection** | A vulnerability where user input is interpreted as SQL code |
| **Stateless** | Each HTTP request is independent — the server doesn't remember previous requests |
| **Status Code** | A 3-digit number in an HTTP response indicating success, error, redirect, etc. |
| **Token** | A long random string used to prove identity or authorization without a password |
| **TOTP** | Time-based One-Time Password — the algorithm behind authenticator apps (RFC 6238) |
| **Transaction** | A group of database operations that are committed together or rolled back together |
| **2FA** | Two-Factor Authentication — requiring two different proofs of identity to log in |
| **Validation** | Checking that user input meets required rules before processing it |
| **XAMPP** | A local server package: Apache + MySQL + PHP, used for development on your own machine |

---

*Document generated from the simple-chat project. Last updated: April 2026.*
*Covers: MVC architecture, REST API design, PHP sessions, SQL, password security, token-based flows, SMTP email delivery (PHPMailer + Zoho), TOTP 2FA, middleware, repository pattern, dependency injection.*
