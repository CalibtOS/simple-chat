<?php
header("Content-Type: application/json");

require 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Only POST allowed"]);
    exit;
}

$name = trim($_POST["name"] ?? "");
$message = trim($_POST["message"] ?? "");

if ($name === "" || $message === "") {
    http_response_code(400);
    echo json_encode(["error" => "Name and message are required"]);
    exit;
}

$nameEsc = mysqli_real_escape_string($conn, $name);
$messageEsc = mysqli_real_escape_string($conn, $message);

$sql = "INSERT INTO messages (name, message, created_at)
        VALUES ('$nameEsc', '$messageEsc', NOW())";

if (!mysqli_query($conn, $sql)) {
    http_response_code(500);
    echo json_encode(["error" => "Insert failed"]);
    exit;
}

// Ahmed auto-reply: insert a message from Ahmed asking to talk more
$ahmedReply = "Hey! Thanks for your message. Talk to me more!";
$ahmedReplyEsc = mysqli_real_escape_string($conn, $ahmedReply);
$sqlReply = "INSERT INTO messages (name, message, created_at)
             VALUES ('Ahmed', '$ahmedReplyEsc', NOW())";
mysqli_query($conn, $sqlReply);

echo json_encode(["success" => true]);