<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Fetch binary data, type, and status
    $stmt = $conn->prepare("SELECT title, file_data, file_type, status FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($title, $file_data, $file_type, $status);

    if ($stmt->fetch()) {
        // Security Check: If not public, ensure user is logged in
        if ($status !== 'public' && !isset($_SESSION['user_id'])) {
            die("Access Denied: This document is restricted.");
        }

        if (!empty($file_data)) {
            header("Content-type: " . $file_type);
            header("Content-Disposition: inline; filename=\"" . basename($title) . ".pdf\"");
            echo $file_data;
            exit();
        } else {
            echo "Error: Document data is missing in the database.";
        }
    } else {
        echo "Document not found.";
    }
    $stmt->close();
} else {
    echo "Invalid Request.";
}
?>