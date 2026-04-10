<?php
/**
 * SB Archive - Logout Handler
 * Securely destroys session and redirects with a message.
 */

include 'config.php';

// Clear session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear Remember Me cookie
setcookie('remember_me', '', time() - 3600, '/');

// Destroy server session
session_destroy();

// REDIRECT with a 'logged_out' status flag
header("Location: index.php?status=logged_out");
exit();
?>