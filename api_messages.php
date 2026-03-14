<?php
header("Content-Type: application/json");

require 'db.php';

$sql = "SELECT name, message, created_at FROM messages ORDER BY created_at ASC";
$result = mysqli_query($conn, $sql);

$messages = [];

while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        "name" => $row["name"],
        "message" => $row["message"],
        "created_at" => $row["created_at"],
    ];
}

echo json_encode($messages);