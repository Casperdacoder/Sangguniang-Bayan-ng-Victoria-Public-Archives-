<?php
include 'config.php';

// Add event_location column if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM events LIKE 'event_location'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE events ADD COLUMN event_location VARCHAR(255) AFTER event_date";
    if ($conn->query($sql) === TRUE) {
        echo "<h3>Success!</h3> Added 'event_location' column to events table.<br>";
    } else {
        echo "Error updating table: " . $conn->error;
    }
} else {
    echo "Column 'event_location' already exists.<br>";
}
echo "<a href='admin_events.php'>Go to Manage Events</a>";
?>