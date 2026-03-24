<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    json_response(
        ['error' => 'You must be logged in to view messages'],
        401
    );
}

$user = getCurrentUser();
$userId = (int) $user['id'];

// If conversation_id is not provided, fall back to the user's oldest conversation.
$conversationId = (int) ($_GET['conversation_id'] ?? 0);

if ($conversationId <= 0) {
    $sqlConv = "SELECT c.id
                FROM conversations c
                JOIN conversation_members cm ON cm.conversation_id = c.id
                WHERE cm.user_id = $userId
                ORDER BY c.created_at ASC
                LIMIT 1";
    $resConv = mysqli_query($conn, $sqlConv);
    $rowConv = mysqli_fetch_assoc($resConv);
    $conversationId = (int) ($rowConv['id'] ?? 0);
}

// If the user has no conversations yet, create a default one (Ahmed bot).
if ($conversationId <= 0) {
    $resBot = mysqli_query($conn, "SELECT id FROM bots WHERE slug = 'ahmed' LIMIT 1");
    $botRow = mysqli_fetch_assoc($resBot);
    $botId = (int) ($botRow['id'] ?? 0);
    if ($botId <= 0) {
        json_response(['error' => 'Default bot not found. Run migrations.'], 500);
    }

    $sqlCreate = "INSERT INTO conversations (title, created_by_user_id, bot_id, created_at)
                  VALUES ('Chat with Ahmed', $userId, $botId, NOW())";
    mysqli_query($conn, $sqlCreate);
    $conversationId = (int) mysqli_insert_id($conn);

    mysqli_query($conn, "INSERT IGNORE INTO conversation_members (conversation_id, user_id, created_at)
                         VALUES ($conversationId, $userId, NOW())");
}

// Authorization: user must be a member of this conversation.
$sqlAuth = "SELECT 1 FROM conversation_members
            WHERE conversation_id = $conversationId AND user_id = $userId
            LIMIT 1";
$resAuth = mysqli_query($conn, $sqlAuth);
if (!mysqli_fetch_assoc($resAuth)) {
    json_response(['error' => 'Forbidden'], 403);
}

$sql = "SELECT id, user_id, name, message, created_at
        FROM messages
        WHERE conversation_id = $conversationId
        ORDER BY created_at ASC";

$result = mysqli_query($conn, $sql);

$messages = [];

while ($row = mysqli_fetch_assoc($result)) {
    $uid = $row['user_id'];
    $messages[] = [
        'id'         => (int) $row['id'],
        'user_id'    => $uid === null ? null : (int) $uid,
        'name'       => $row['name'],
        'message'    => $row['message'],
        'created_at' => $row['created_at'],
    ];
}

json_response($messages);
