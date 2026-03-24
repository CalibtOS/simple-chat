<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Only POST allowed'], 405);
}

if (!isLoggedIn()) {
    json_response(['error' => 'You must be logged in'], 401);
}

$user = getCurrentUser();
$userId = (int) $user['id'];

$messageId = (int) ($_POST['message_id'] ?? 0);
$newText = trim($_POST['message'] ?? '');

if ($messageId <= 0) {
    json_response(['error' => 'message_id is required'], 400);
}

if ($newText === '') {
    json_response(['error' => 'Message text is required'], 400);
}

$sqlFetch = "SELECT id, conversation_id, user_id, message
             FROM messages
             WHERE id = $messageId
             LIMIT 1";
$resFetch = mysqli_query($conn, $sqlFetch);
$row = mysqli_fetch_assoc($resFetch);

if (!$row) {
    json_response(['error' => 'Message not found'], 404);
}

$ownerId = $row['user_id'];
if ($ownerId === null || (int) $ownerId !== $userId) {
    json_response(['error' => 'You can only edit your own messages'], 403);
}

$conversationId = (int) $row['conversation_id'];

$sqlAuth = "SELECT 1 FROM conversation_members
            WHERE conversation_id = $conversationId AND user_id = $userId
            LIMIT 1";
$resAuth = mysqli_query($conn, $sqlAuth);
if (!mysqli_fetch_assoc($resAuth)) {
    json_response(['error' => 'Forbidden'], 403);
}

$newEsc = mysqli_real_escape_string($conn, $newText);
$sqlUpdate = "UPDATE messages
              SET message = '$newEsc'
              WHERE id = $messageId AND user_id = $userId";

if (!mysqli_query($conn, $sqlUpdate)) {
    json_response(['error' => 'Update failed'], 500);
}

json_response(['success' => true, 'id' => $messageId]);
