<?php
include 'config.php';

$user = $_ENV['ADMIN_USER'] ?? 'admin';
$pass = $_ENV['ADMIN_PASS'] ?? 'sbvictoria'; 

// Clean the table and insert the fresh user
$conn->query("TRUNCATE TABLE users");
$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
$stmt->bind_param("ss", $user, $pass);

if ($stmt->execute()) {
    echo "<h1>Success!</h1>";
    echo "<p>Admin account created with password: <b>$pass</b></p>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
} else {
    echo "Error: " . $conn->error;
}

// IMPORTANT: Delete this file after running it!
?>