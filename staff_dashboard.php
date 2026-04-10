<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// UPLOAD LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    $title = trim($_POST['title']);
    $doc_num = trim($_POST['doc_number']);
    $category = $_POST['category'];
    $date = $_POST['date_enacted'];
    
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $fileTmpName = $_FILES['pdf_file']['tmp_name'];
        $fileType = $_FILES['pdf_file']['type'];
        $fileData = file_get_contents($fileTmpName);

        $stmt = $conn->prepare("INSERT INTO documents (title, doc_number, category, date_enacted, file_data, file_type, status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, 'hidden', ?)");
        
        $null = NULL;
        $stmt->bind_param("ssssbss", $title, $doc_num, $category, $date, $null, $fileType, $username);
        $stmt->send_long_data(4, $fileData);

        if ($stmt->execute()) {
            log_activity($conn, "Upload", "Staff uploaded: $title");
            header("Location: staff_dashboard.php?success=1");
            exit();
        }
    }
}

// DELETE OWN ACTION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'soft_delete') {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE documents SET is_deleted = 1 WHERE id = ? AND uploaded_by = ?");
        $stmt->bind_param("is", $id, $username);
        $stmt->execute();
        log_activity($conn, "Delete", "Staff deleted own document ID: $id");
        header("Location: staff_dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | SB Victoria</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="admin-body">
<div class="sidebar">
    <h2>SB VICTORIA</h2>
    <div style="margin: 20px 0;">
        <p style="font-size: 0.75rem; color: #94a3b8; margin:0;">Staff Account</p>
        <p style="font-weight: 700; color: #fbbf24; margin: 5px 0;"><?= htmlspecialchars($username) ?></p>
        <span style="font-size: 0.7rem; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 4px;">CONTRIBUTOR</span>
    </div>
    <nav>
        <a href="staff_dashboard.php" class="nav-link" style="color:white;">📂 My Submissions</a>
        <a href="archive.php" class="nav-link">🏠 View Website</a>
        <a href="logout.php" class="nav-link" style="color: #f87171; margin-top: 40px; padding-top: 20px;">Logout</a>
    </nav>
</div>
<div class="main">
    <h1>Staff Submission Portal</h1>
    
    <div class="card">
        <h3 style="margin-top:0;">Upload New Document</h3>
        <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: flex-end;">
            <input type="text" name="title" placeholder="Document Title" required>
            <input type="text" name="doc_number" placeholder="Res/Ord No.">
            <select name="category"><option>Ordinance</option><option>Resolution</option></select>
            <input type="date" name="date_enacted" required>
            <input type="file" name="pdf_file" accept=".pdf" required>
            <button type="submit" style="background:#1e293b; color:white; border:none; padding:10px; border-radius:4px; cursor:pointer; font-weight:bold;">Upload to Admin</button>
        </form>
    </div>

    <div class="card" style="padding:0;">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>My Documents</th>
                        <th style="text-align:center;">Review Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM documents WHERE uploaded_by = ? AND is_deleted = 0 ORDER BY id DESC");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while($row = $res->fetch_assoc()):
                        $st = $row['status'];
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['title']) ?></div>
                            <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($row['doc_number']) ?></div>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge" style="background:<?= ($st=='hidden'?'#fef3c7':($st=='rejected'?'#fee2e2':'#dcfce7')) ?>; color:<?= ($st=='hidden'?'#92400e':($st=='rejected'?'#ef4444':'#166534')) ?>;">
                                <?= strtoupper($st == 'hidden' ? 'PENDING REVIEW' : $st) ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_file.php?id=<?= $row['id'] ?>" target="_blank" class="btn-sm btn-review">View PDF</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this submission?');">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="action" value="soft_delete">
                                <button type="submit" class="btn-sm btn-reject">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>