<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Fetch binary data, type, and status
    $stmt = $conn->prepare("SELECT title, file_type, file_data, status FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($title, $file_type, $file_data, $status);

    if ($stmt->fetch()) {
        // Security Check: If not public, ensure user is logged in
        if ($status !== 'public' && !isset($_SESSION['user_id'])) {
            die("Access Denied: This document is restricted.");
        }

        if (!empty($file_data)) {
            // Clean output buffer to prevent file corruption
            if (ob_get_length()) ob_clean();

            // Set headers to display PDF
            header("Content-Type: " . $file_type);
            header("Content-Disposition: inline; filename=\"" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $title) . ".pdf\"");
            header("Content-Length: " . strlen($file_data));

            echo $file_data;
            exit();
        } else {
            echo "Error: File data is empty.";
        }
    } else {
        echo "Document not found.";
    }
    $stmt->close();
} else {
    echo "Invalid Request.";
}
?>