<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 1. Get the file path and metadata using mysqli
    $stmt = $conn->prepare("SELECT title, pdf_path, status FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        // Security Check: If not public, ensure user is logged in
        if ($row['status'] !== 'public' && !isset($_SESSION['user_id'])) {
            die("Access Denied: This document is restricted.");
        }

        if (!empty($row['pdf_path'])) {
            $filePath = "uploads/" . $row['pdf_path'];

            if (file_exists($filePath)) {
                // 2. Kill any previous buffering that might cause memory leaks
                while (ob_get_level()) { ob_end_clean(); }

                // 3. Set headers for large file streaming
                header("Content-Type: application/pdf");
                header("Content-Disposition: inline; filename=\"" . basename($row['title']) . ".pdf\"");
                header("Content-Length: " . filesize($filePath));
                header("Cache-Control: private, max-age=0, must-revalidate");
                header("Pragma: public");

                // 4. Stream the file in chunks (Uses almost 0 RAM)
                $file = fopen($filePath, "rb");
                while (!feof($file)) {
                    echo fread($file, 1024 * 8); // Send 8KB at a time
                    flush(); // Push the chunk to the browser immediately
                }
                fclose($file);
                exit;
            } else {
                die("File not found on server.");
            }
        } else {
            die("No file path associated with this record.");
        }
    } else {
        die("Document not found.");
    }
} else {
    echo "Invalid Request.";
}
?>