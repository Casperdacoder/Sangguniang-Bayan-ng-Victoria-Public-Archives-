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
$db_url = isset($_ENV['DATABASE_URL']) ? $_ENV['DATABASE_URL'] : "mysql://root:@127.0.0.1:3306/sb_victoria";
$db_parts = parse_url($db_url);

$db_host = isset($db_parts['host']) ? $db_parts['host'] : '127.0.0.1';
if (isset($db_parts['port'])) $db_host .= ':' . $db_parts['port'];
$db_user = isset($db_parts['user']) ? $db_parts['user'] : 'root';
$db_pass = isset($db_parts['pass']) ? $db_parts['pass'] : '';
$db_name = isset($db_parts['path']) ? ltrim($db_parts['path'], '/') : 'sb_victoria';

// Initial Connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Robust connection check using try-catch to prevent Fatal Exceptions
try {
    if ($conn->connect_error || !$conn->ping()) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    }
} catch (Throwable $e) {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
}

$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
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
        try {
            if (!$conn || !$conn->ping()) { return; } 
        } catch (Throwable $e) {
            return; // Exit if connection is dead
        }
        $user = $_SESSION['username'] ?? 'System';
        $stmt = $conn->prepare("INSERT INTO activity_log (username, action, details) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user, $action, $details);
        $stmt->execute();
    }
}