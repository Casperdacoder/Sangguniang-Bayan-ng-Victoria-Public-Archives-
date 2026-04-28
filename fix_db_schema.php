<?php
/**
 * Database Schema Repair Script
 * This script adds the missing 'pdf_path' column to the documents table.
 */
include 'config.php';

echo "<h2>Database Repair Tool</h2>";

// 1. Add file_data column for binary storage if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM documents LIKE 'file_data'");

if ($check->num_rows == 0) {
    $sql = "ALTER TABLE documents ADD COLUMN file_data LONGBLOB AFTER pdf_path";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>✅ Success: Column 'file_data' (LONGBLOB) has been added to the 'documents' table.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Notice: Column 'file_data' already exists. No changes needed.</p>";
}

// 2. Add year column if it doesn't exist
$check_year = $conn->query("SHOW COLUMNS FROM documents LIKE 'year'");
if ($check_year->num_rows == 0) {
    $sql = "ALTER TABLE documents ADD COLUMN year VARCHAR(4) AFTER doc_number";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>✅ Success: Column 'year' has been added to the 'documents' table.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Notice: Column 'year' already exists. No changes needed.</p>";
}

echo "<br><a href='admin_dashboard.php'>Return to Dashboard</a>";
?>