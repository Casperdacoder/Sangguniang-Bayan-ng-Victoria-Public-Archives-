<?php
session_start();

/**
 * Simple .env loader to populate $_ENV from the local .env file.
 */
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
        }
    }
}

/**
 * SB Archive - Database Configuration
 * Apache Port: 80
 * MySQL Port: 3306 (Default)
 */

// Parse the DATABASE_URL environment variable
$db_url = $_ENV['DATABASE_URL'] ?? "mysql://root:@127.0.0.1:3306/sb_victoria";
$db_parts = parse_url($db_url);

$db_host = $db_parts['host'] . (isset($db_parts['port']) ? ':' . $db_parts['port'] : '');
$db_user = $db_parts['user'] ?? 'root';
$db_pass = $db_parts['pass'] ?? '';
$db_name = ltrim($db_parts['path'] ?? '', '/');

// Create Connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check Connection
if ($conn->connect_error) {
    echo "<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red;'>";
    echo "<strong>Database Connection Failed!</strong><br>";
    echo "Error: " . $conn->connect_error . "<br><br>";
    echo "<em>Note: Ensure MySQL is started in XAMPP and running on port 3307.</em>";
    echo "</div>";
    exit();
}

// 5. Global Security Gatekeeper
/**
 * Prevents unauthorized access to sensitive pages.
 * Redirects to login.php if a session is not active.
 */
if (!function_exists('protect_page')) {
    function protect_page() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit();
        }
    }
}

// 7. Activity Logging Helper
if (!function_exists('log_activity')) {
    function log_activity($conn, $action, $details) {
        $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'System';
        $stmt = $conn->prepare("INSERT INTO activity_log (username, action, details) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user, $action, $details);
        $stmt->execute();
    }
}