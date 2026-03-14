<?php
$submittedValue = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Read the value from the form
    $submittedValue = $_POST["my_text"] ?? "";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Echo Form</title>
</head>
<body>
    <h2>Simple Echo Form</h2>

    <form method="post" action="">
        <input type="text" name="my_text" placeholder="Type something">
        <button type="submit">Send</button>
    </form>

    <hr>

    <p><strong>You typed:</strong> <?php echo htmlspecialchars($submittedValue); ?></p>
</body>
</html>