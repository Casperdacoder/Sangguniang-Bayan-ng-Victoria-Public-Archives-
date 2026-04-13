<?php
/**
 * Database Schema Repair Script
 * This script adds the missing 'pdf_path' column to the documents table.
 */
include 'config.php';

echo "<h2>Database Repair Tool</h2>";

// 1. Check if pdf_path exists
$check = $conn->query("SHOW COLUMNS FROM documents LIKE 'pdf_path'");

if ($check->num_rows == 0) {
    $sql = "ALTER TABLE documents ADD COLUMN pdf_path VARCHAR(255) AFTER date_enacted";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>✅ Success: Column 'pdf_path' has been added to the 'documents' table.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Notice: Column 'pdf_path' already exists. No changes needed.</p>";
}

echo "<br><a href='admin_dashboard.php'>Return to Dashboard</a>";
?>