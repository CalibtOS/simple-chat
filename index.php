<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "chat";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed");
}


/* -----------------------------
   Handle new message (POST)
------------------------------*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"]);
    $message = trim($_POST["message"]);

    if ($name !== "" && $message !== "") {

        $name = mysqli_real_escape_string($conn, $name);
        $message = mysqli_real_escape_string($conn, $message);

        $sql = "INSERT INTO messages (name, message, created_at)
                VALUES ('$name', '$message', NOW())";

        mysqli_query($conn, $sql);
    }

}


/* -----------------------------
   Fetch messages
------------------------------*/
$result = mysqli_query($conn, "SELECT name, message, created_at FROM messages ORDER BY created_at ASC");

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Simple Chat</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body {
            background: #0f172a;
            color: #e5e7eb;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 16px;
        }

        .chat-wrapper {
            background: #020617;
            border-radius: 16px;
            width: 100%;
            max-width: 640px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header-title {
            font-size: 18px;
            font-weight: 600;
        }

        .chat-header-subtitle {
            font-size: 12px;
            color: #9ca3af;
        }

        .chat-messages {
            padding: 16px 20px;
            height: 320px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: radial-gradient(circle at top left, rgba(59,130,246,0.15), transparent 55%),
                        radial-gradient(circle at bottom right, rgba(236,72,153,0.15), transparent 55%);
        }

        .message-row {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }

        .message-meta {
            font-size: 11px;
            color: #9ca3af;
        }

        .message-bubble {
            background: rgba(15, 23, 42, 0.85);
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 14px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            backdrop-filter: blur(6px);
        }

        .message-name {
            font-weight: 600;
            color: #e5e7eb;
        }

        .message-time {
            color: #9ca3af;
        }

        .chat-form {
            border-top: 1px solid rgba(148, 163, 184, 0.25);
            padding: 12px 16px;
            background: #020617;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .chat-name-row {
            display: flex;
            gap: 8px;
        }

        .chat-name-row input {
            flex: 1;
        }

        .chat-input,
        .chat-name-input {
            background: #020617;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.5);
            padding: 8px 12px;
            color: #e5e7eb;
            font-size: 14px;
            outline: none;
        }

        .chat-input::placeholder,
        .chat-name-input::placeholder {
            color: #6b7280;
        }

        .chat-input:focus,
        .chat-name-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.5);
        }

        .chat-submit {
            border: none;
            border-radius: 999px;
            padding: 8px 14px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.08s ease, box-shadow 0.08s ease, filter 0.08s ease;
            align-self: flex-end;
        }

        .chat-submit:hover {
            filter: brightness(1.05);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.5);
            transform: translateY(-1px);
        }

        .chat-submit:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .chat-footer-note {
            font-size: 11px;
            color: #6b7280;
            text-align: right;
        }

        @media (max-width: 480px) {
            .chat-wrapper {
                max-width: 100%;
            }

            .chat-messages {
                height: 260px;
            }
        }
    </style>
</head>

<body>
<div class="chat-wrapper">
    <div class="chat-header">
        <div>
            <div class="chat-header-title">Simple Chat</div>
            <div class="chat-header-subtitle">PHP + MySQL backend, HTML/CSS frontend</div>
        </div>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php while ($row = mysqli_fetch_assoc($result)) : ?>
            <?php
            $name = htmlspecialchars($row["name"]);
            $message = htmlspecialchars($row["message"]);
            $time = $row["created_at"];
            ?>
            <div class="message-row">
                <div class="message-meta">
                    <span class="message-name"><?php echo $name; ?></span>
                    <span class="message-time"> · <?php echo $time; ?></span>
                </div>
                <div class="message-bubble">
                    <?php echo $message; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <form class="chat-form" method="post" action="">
        <div class="chat-name-row">
            <input
                type="text"
                name="name"
                class="chat-name-input"
                placeholder="Your name"
                required
            >
        </div>

        <input
            type="text"
            name="message"
            class="chat-input"
            placeholder="Write a message and hit Send…"
            required
        >

        <button type="submit" class="chat-submit">Send</button>
        <div class="chat-footer-note">Page reloads after sending (classic PHP).</div>
    </form>
</div>

<script>
    // Auto-scroll to bottom on load so you see the latest messages
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
</script>
</body>
</html>
