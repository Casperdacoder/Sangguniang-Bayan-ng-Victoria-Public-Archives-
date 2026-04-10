<?php
/**
 * SB Archive - Staff & Admin Login (Plain Text Version)
 */
include 'config.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: staff_dashboard.php");
    }
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_input = trim($_POST['username']);
    $pass_input = trim($_POST['password']);

    if (!empty($user_input) && !empty($pass_input)) {
        // Query the database
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $user_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $db_pass = trim($row['password']);

            // PLAIN TEXT COMPARISON (Since you don't want password_hash)
            if ($pass_input === $db_pass) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                
                if ($row['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: staff_dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Account not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SB Archive</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="login-body">
    <div class="login-card">
        <h2 style="text-align: center; color: #0f172a;">Secure Login</h2>
        
        <?php if($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" required class="input-field">
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required class="input-field">
            </div>
            <button type="submit" class="btn-submit">Sign In</button>
            <a href="index.php" class="back-link">← Back to Home</a>
        </form>
    </div>
</body>
</html>