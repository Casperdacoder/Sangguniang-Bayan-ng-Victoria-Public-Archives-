<?php
/**
 * One-time migration script to update 'Minutes' to 'Minutes of Meeting'
 */
include 'config.php';

// Ensure only admins can run this if accessed via browser, 
// or you can just run it once and delete it.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$old_val = 'Minutes';
$new_val = 'Minutes of Meeting';

$stmt = $conn->prepare("UPDATE documents SET category = ? WHERE category = ?");
$stmt->bind_param("ss", $new_val, $old_val);

if ($stmt->execute()) {
    echo "<h2>Migration Successful</h2>";
    echo "<p>Updated <b>" . $stmt->affected_rows . "</b> records from '$old_val' to '$new_val'.</p>";
    echo "<a href='admin_dashboard.php'>Return to Dashboard</a>";
} else {
    echo "Error updating records: " . $conn->error;
}
?>