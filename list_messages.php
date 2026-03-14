<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "chat";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed");
}

$sql = "SELECT name, message, created_at FROM messages ORDER BY created_at ASC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>List Messages</title>
</head>
<body>
    <h2>Messages from database</h2>

    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
        <?php
        $name = htmlspecialchars($row["name"]);
        $message = htmlspecialchars($row["message"]);
        $time = $row["created_at"];
        ?>
        <p>
            <strong><?php echo $name; ?></strong>
            [<?php echo $time; ?>]:
            <?php echo $message; ?>
        </p>
    <?php endwhile; ?>

</body>
</html>