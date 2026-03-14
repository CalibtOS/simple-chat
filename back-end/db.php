<?php
require_once __DIR__ . '/config.php';
$conn = mysqli_connect($host, $user, $password, $database);
if (!$conn) {
    die("Database connection failed");
}
?>