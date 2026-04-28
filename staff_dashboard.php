<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$message = "";

if (isset($_GET['upload']) && $_GET['upload'] == 'success') {
    $message = "<div style='color:green; margin-bottom:15px; font-weight:bold;'>✅ Document submitted successfully for review!</div>";
}

// UPLOAD LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    $title = trim($_POST['title']);
    $doc_num = trim($_POST['doc_number']); // Changed to $doc_num for consistency with admin_dashboard
    $year = trim($_POST['year']);
    $category = $_POST['category']; // Order of Business, Journal, etc.
    $date_enacted = $_POST['date_enacted'];
    $uploaded_by = $_SESSION['username'];
    $status = 'hidden'; // Staff uploads are 'hidden' for approval

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        // 2. Move File First (Avoids MySQL "Gone Away" during transfer)
        // File Upload Handling
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

        $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['pdf_file']['name'])); // Sanitize filename
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_file)) {
            // 3. FRESH CONNECTION (Ensures server is 'awake' for the INSERT)
            global $db_host, $db_user, $db_pass, $db_name; // Ensure globals are accessible
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            $conn->set_charset("utf8mb4");
            
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            // PREPARE: 7 placeholders for 7 columns
            $stmt = $conn->prepare("INSERT INTO documents (title, doc_number, year, category, date_enacted, pdf_path, status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            // BIND: 7 variables matching 'sssssss'
            $stmt->bind_param("ssssssss", $title, $doc_num, $year, $category, $date_enacted, $file_name, $status, $uploaded_by);

            if ($stmt->execute()) {
                log_activity($conn, "Upload", "Staff uploaded: $title");
                header("Location: staff_dashboard.php?upload=success");
                exit();
            } else {
                echo "Database Error: " . $stmt->error;
            }
        } else {
            echo "Error: Check folder permissions for 'uploads/'.";
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
    <?php echo $message; ?>
    
    <div class="card">
        <h3 style="margin-top:0;">Upload New Document</h3>
        <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: flex-end;">
            <input type="text" name="title" placeholder="Document Title" required>
            <input type="text" name="doc_number" placeholder="Res/Ord No.">
            <input type="text" name="year" placeholder="Year (YYYY)" pattern="\d{4}" maxlength="4" required title="Please enter a 4-digit year">
            <select name="category" class="input-field" required>
                <option value="">-- Select Category --</option>
                <option value="Order of Business">Order of Business</option>
                <option value="Journal">Journal</option>
                <option value="Appropriation Ordinance">Appropriation Ordinance</option>
                <option value="General Ordinance">General Ordinance</option>
                <option value="Resolution">Resolution</option>
                <option value="Minutes of Meeting">Minutes of Meeting</option>
            </select>
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
                        <th style="text-align:center;">Actions</th>
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
                            <span style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['title']) ?></span>
                            <?php if(!empty($row['doc_number'])): ?>
                                <span style="font-size: 0.8rem; color: #64748b; margin-left: 5px;"><?= htmlspecialchars($row['doc_number']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge" style="background:<?= ($st=='hidden'?'#fef3c7':($st=='rejected'?'#fee2e2':'#dcfce7')) ?>; color:<?= ($st=='hidden'?'#92400e':($st=='rejected'?'#ef4444':'#166534')) ?>;">
                                <?= strtoupper($st == 'hidden' ? 'PENDING REVIEW' : $st) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
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