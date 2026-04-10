<?php
/**
 * SB Archive - Management Dashboard
 * Workflow: Review -> Reject/Approve
 * Notification: Sidebar count for pending staff uploads
 */
include 'config.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_role = $_SESSION['role'];
$username = $_SESSION['username'];
$message = ""; // Initialize message variable

// 2. HANDLE WORKFLOW ACTIONS
// Use POST for all state-changing actions to prevent CSRF
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $action = $_POST['action'];

    if ($id > 0) {
        if ($current_role == 'admin') {
            if ($action == 'approve') {
                $stmt = $conn->prepare("UPDATE documents SET status = 'public' WHERE id = ?");
                $stmt->bind_param("i", $id); $stmt->execute();
                log_activity($conn, "Approve", "Approved Document ID: $id");
            } elseif ($action == 'reject') {
                $stmt = $conn->prepare("UPDATE documents SET status = 'rejected' WHERE id = ?");
                $stmt->bind_param("i", $id); $stmt->execute();
                log_activity($conn, "Reject", "Rejected Document ID: $id");
            } elseif ($action == 'toggle_private') {
                $stmt = $conn->prepare("UPDATE documents SET status = 'private' WHERE id = ?");
                $stmt->bind_param("i", $id); $stmt->execute();
                log_activity($conn, "Hide", "Set Document ID: $id to Private");
            } elseif ($action == 'toggle_public') {
                $stmt = $conn->prepare("UPDATE documents SET status = 'public' WHERE id = ?");
                $stmt->bind_param("i", $id); $stmt->execute();
                log_activity($conn, "Publish", "Set Document ID: $id to Public");
            } elseif ($action == 'restore') {
                $stmt = $conn->prepare("UPDATE documents SET is_deleted = 0 WHERE id = ?");
                $stmt->bind_param("i", $id); $stmt->execute();
                log_activity($conn, "Restore", "Restored Document ID: $id from Bin");
            } elseif ($action == 'perm_delete') {
                $del_stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
                $del_stmt->bind_param("i", $id);
                $del_stmt->execute();
                log_activity($conn, "Delete", "Permanently Deleted Document ID: $id");
            }
        }

        if ($action == 'soft_delete') {
            if ($current_role == 'admin') {
                $stmt = $conn->prepare("UPDATE documents SET is_deleted = 1 WHERE id = ?");
                $stmt->bind_param("i", $id);
            } else { // Staff can only delete their own
                $stmt = $conn->prepare("UPDATE documents SET is_deleted = 1 WHERE id = ? AND uploaded_by = ?");
                $stmt->bind_param("is", $id, $username);
            }
            $stmt->execute();
            log_activity($conn, "Recycle", "Moved Document ID: $id to Bin");
        }
    }
    // Maintain view state after action by preserving GET params
    $redirect_qs = http_build_query(array_intersect_key($_GET, array_flip(['view', 'search', 'page'])));
    header("Location: admin_dashboard.php?" . $redirect_qs);
    exit();
}

// HANDLE BULK ACTIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    if ($current_role == 'admin') {
        $action = $_POST['bulk_action'];
        $doc_ids = isset($_POST['doc_ids']) ? $_POST['doc_ids'] : [];

        if (!empty($action) && !empty($doc_ids) && is_array($doc_ids)) {
            // Sanitize IDs to prevent SQL injection
            $sanitized_ids = array_map('intval', $doc_ids);
            $id_list = implode(',', $sanitized_ids);

            if (!empty($id_list)) {
                $log_action = "Bulk ";
                $log_details = "Applied to IDs: $id_list";
                $sql = "";

                switch ($action) {
                    case 'approve':
                        $sql = "UPDATE documents SET status = 'public' WHERE id IN ($id_list) AND status = 'hidden'";
                        $log_action .= "Approve";
                        break;
                    case 'reject':
                        $sql = "UPDATE documents SET status = 'rejected' WHERE id IN ($id_list) AND status = 'hidden'";
                        $log_action .= "Reject";
                        break;
                    case 'soft_delete':
                        $sql = "UPDATE documents SET is_deleted = 1 WHERE id IN ($id_list)";
                        $log_action .= "Recycle";
                        break;
                    case 'restore':
                        $sql = "UPDATE documents SET is_deleted = 0 WHERE id IN ($id_list) AND is_deleted = 1";
                        $log_action .= "Restore";
                        break;
                }

                if (!empty($sql)) { $conn->query($sql); log_activity($conn, $log_action, $log_details); }
            }
        }
    }
    // Redirect to prevent re-submission and maintain view
    $redirect_qs = http_build_query(array_intersect_key($_GET, array_flip(['view', 'search', 'page'])));
    header("Location: admin_dashboard.php?" . $redirect_qs);
    exit();
}

// 3. EVENT HANDLER (New)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['save_event']) || isset($_POST['delete_event'])) && $current_role == 'admin') {
    // Deletion
    if (isset($_POST['delete_event'])) {
        $id = (int)$_POST['event_id'];
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "<div style='color:green; margin-bottom:15px;'>Event deleted successfully.</div>";
        }
    }
    // Add or Update
    elseif (isset($_POST['save_event'])) {
        $id = (int)$_POST['event_id'];
        $title = trim($_POST['event_title']);
        $date = trim($_POST['event_date']);
        $location = trim($_POST['event_location']);
        $desc = trim($_POST['event_description']);

        if (!empty($title) && !empty($date)) {
            if ($id > 0) { // Update
                $stmt = $conn->prepare("UPDATE events SET event_title = ?, event_date = ?, event_location = ?, event_description = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $title, $date, $location, $desc, $id);
                $message = "<div style='color:green; margin-bottom:15px;'>Event updated successfully.</div>";
            } else { // Insert
                $stmt = $conn->prepare("INSERT INTO events (event_title, event_date, event_location, event_description) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $title, $date, $location, $desc);
                $message = "<div style='color:green; margin-bottom:15px;'>Event created successfully.</div>";
            }
        } else {
            $message = "<div style='color:red; margin-bottom:15px;'>Title and Date are required.</div>";
        }
        $stmt->execute();
    }
}
// 4. USER MANAGEMENT HANDLER (New)
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_user']) || isset($_POST['delete_user'])) && $current_role == 'admin') {
    if (isset($_POST['add_user'])) {
        $new_user = trim($_POST['new_username']);
        $new_pass = trim($_POST['new_password']);
        $new_role = $_POST['new_role'];

        if (!empty($new_user) && !empty($new_pass)) {
            // Check if username exists
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $new_user);
            $check->execute();
            if($check->get_result()->num_rows > 0) {
                 $message = "<div style='color:red; margin-bottom:15px;'>Error: Username already exists.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $new_user, $new_pass, $new_role);
                if ($stmt->execute()) {
                    $message = "<div style='color:green; margin-bottom:15px;'>User '$new_user' created successfully!</div>";
                } else {
                     $message = "<div style='color:red; margin-bottom:15px;'>Error creating user.</div>";
                }
            }
        } else {
            $message = "<div style='color:red; margin-bottom:15px;'>Username and password cannot be empty.</div>";
        }
    } elseif (isset($_POST['delete_user'])) {
         $id = (int)$_POST['user_id'];
         if ($id != $_SESSION['user_id']) { // Prevent self-delete
             $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
             $stmt->bind_param("i", $id);
             if($stmt->execute()) $message = "<div style='color:green; margin-bottom:15px;'>User deleted successfully.</div>";
         } else {
             $message = "<div style='color:red; margin-bottom:15px;'>You cannot delete your own account.</div>";
         }
    }
}
// 4.5 ANNOUNCEMENT HANDLER (New)
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['save_announcement']) || isset($_POST['delete_announcement'])) && $current_role == 'admin') {
    if (isset($_POST['delete_announcement'])) {
        $id = (int)$_POST['ann_id'];
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "<div style='color:green; margin-bottom:15px;'>Announcement deleted successfully.</div>";
        }
    } elseif (isset($_POST['save_announcement'])) {
        $id = (int)$_POST['ann_id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);

        // Fetch existing images if editing to manage the gallery
        $image_list = [];
        if ($id > 0) {
            $existing_ann = $conn->query("SELECT image FROM announcements WHERE id = $id")->fetch_assoc();
            if ($existing_ann && $existing_ann['image']) {
                $raw_images = json_decode($existing_ann['image'], true) ?: [$existing_ann['image']];
                foreach ($raw_images as $img) {
                    // Compatibility: Convert old string format to new array format
                    if (is_array($img)) {
                        $image_list[] = $img;
                    } else {
                        $image_list[] = ['file' => $img, 'caption' => ''];
                    }
                }
            }
        }

        // Update captions for existing images
        if (isset($_POST['image_captions']) && is_array($_POST['image_captions'])) {
            foreach ($image_list as &$item) {
                if (isset($_POST['image_captions'][$item['file']])) {
                    $item['caption'] = trim($_POST['image_captions'][$item['file']]);
                }
            }
        }

        // Handle removal of specific images from the gallery
        if (isset($_POST['remove_images']) && is_array($_POST['remove_images'])) {
            $image_list = array_filter($image_list, function($item) {
                return !in_array($item['file'], $_POST['remove_images']);
            });
        }

        // Handle multi-upload processing
        if (!empty($_FILES['images']['name'][0])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            foreach ($_FILES['images']['name'] as $k => $name) {
                if ($_FILES['images']['error'][$k] == 0) {
                    $new_file = time() . "_" . $k . "_" . basename($name);
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$k], $target_dir . $new_file)) {
                        $image_list[] = ['file' => $new_file, 'caption' => ''];
                    }
                }
            }
        }
        
        $final_image_data = !empty($image_list) ? json_encode(array_values($image_list)) : NULL;

        if (!empty($title) && !empty($content)) {
            if ($id > 0) { // Update
                $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, image = ? WHERE id = ?");
                $stmt->bind_param("sssi", $title, $content, $final_image_data, $id);
                $action_word = "Updated";
            } else { // Insert
                $stmt = $conn->prepare("INSERT INTO announcements (title, content, image, status) VALUES (?, ?, ?, 'public')");
                $stmt->bind_param("sss", $title, $content, $final_image_data);
                $action_word = "Published";
            }
            
            if ($stmt->execute()) {
                $message = "<div style='color:green; margin-bottom:15px;'>Announcement $action_word successfully!</div>";
                log_activity($conn, "Announcement", "$action_word: $title");
            } else {
                $message = "<div style='color:red; margin-bottom:15px;'>Error publishing announcement.</div>";
            }
        } else {
            $message = "<div style='color:red; margin-bottom:15px;'>Title and details are required.</div>";
        }
    }
}
// 5. UPLOAD & UPDATE HANDLER (Documents)
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) { // Ensure action handler doesn't re-trigger
    if (isset($_POST['update_id'])) {
        $id = (int)$_POST['update_id'];
        $title = trim($_POST['title']);
        $doc_num = trim($_POST['doc_number']);
        $category = $_POST['category'];
        $date = $_POST['date_enacted'];
        
        $stmt = $conn->prepare("UPDATE documents SET title=?, doc_number=?, category=?, date_enacted=? WHERE id=?");
        $stmt->bind_param("ssssi", $title, $doc_num, $category, $date, $id);
        $stmt->execute();
        log_activity($conn, "Edit", "Updated details for: $title");
        header("Location: admin_dashboard.php?msg=updated");
        exit();
    } elseif (isset($_FILES['pdf_file'])) {
        $title = trim($_POST['title']);
        $doc_num = trim($_POST['doc_number']);
        $category = $_POST['category'];
        $date = $_POST['date_enacted'];

        // Admin uploads are public; Staff uploads are hidden
        $status = ($current_role == 'admin') ? 'public' : 'hidden'; 

        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
            $fileTmpName = $_FILES['pdf_file']['tmp_name'];
            $fileType = $_FILES['pdf_file']['type'];
            
            // Read file content into binary
            $fileData = file_get_contents($fileTmpName);

            $stmt = $conn->prepare("INSERT INTO documents (title, doc_number, category, date_enacted, file_data, file_type, status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $null = NULL; // Placeholder for blob
            $stmt->bind_param("ssssbsss", $title, $doc_num, $category, $date, $null, $fileType, $status, $username);
            $stmt->send_long_data(4, $fileData); // Send binary data to the 5th parameter (index 4)

            if ($stmt->execute()) {
            log_activity($conn, "Upload", "Uploaded new document: $title");
            header("Location: admin_dashboard.php?upload=success");
            exit();
            }
        }
    }
}

// 6. DATA FETCHING & COUNTERS
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$search_q = isset($_GET['search']) ? trim($_GET['search']) : '';
$is_dashboard_search = ($view == 'dashboard' && !empty($search_q));

$pending_count = $conn->query("SELECT COUNT(*) as total FROM documents WHERE status = 'hidden' AND is_deleted = 0")->fetch_assoc()['total'];
$bin_count = $conn->query("SELECT COUNT(*) as total FROM documents WHERE is_deleted = 1")->fetch_assoc()['total'];
$total_docs_count = $conn->query("SELECT COUNT(*) as total FROM documents WHERE is_deleted = 0")->fetch_assoc()['total'];
$total_users_count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

// Fetch event for editing if needed
$edit_event = null;
if ($view == 'events' && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_event = $conn->query("SELECT * FROM events WHERE id = $id")->fetch_assoc();
}

// Fetch announcement for editing if needed
$edit_ann = null;
if ($view == 'announcements' && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_ann = $conn->query("SELECT * FROM announcements WHERE id = $id")->fetch_assoc();
}

if ($is_dashboard_search) {
    $active_view = 'dashboard';
} else {
    $active_view = $view;
    if ($view == 'edit') {
        $active_view = 'active';
    }
    if ($view == 'events') $active_view = 'events';
}

// PAGINATION SETTINGS
$limit = 10; // Items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Console | SB Victoria</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="admin-body">

<div class="sidebar">
    <h2>SB VICTORIA</h2>
    <div style="margin: 20px 0;">
        <p style="font-size: 0.75rem; color: #94a3b8; margin: 0;">User: <b><?= htmlspecialchars($username) ?></b></p>
        <span style="font-size: 0.7rem; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 4px;"><?= strtoupper($current_role) ?></span>
    </div>

    <nav>
        <a href="admin_dashboard.php?view=dashboard" class="nav-link <?= $active_view == 'dashboard' ? 'active' : '' ?>">
            <span>📊 Dashboard Home</span>
        </a>
        <a href="admin_dashboard.php?view=active" class="nav-link <?= $active_view == 'active' ? 'active' : '' ?>">
            <span>📂 Active Archives</span>
        </a>

        <?php if($current_role == 'admin'): ?>
            <a href="admin_dashboard.php?view=pending" class="nav-link <?= $active_view == 'pending' ? 'active' : '' ?>">
                <span>🔔 Staff Uploads</span>
                <?php if($pending_count > 0): ?>
                    <span class="notif-badge"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_dashboard.php?view=bin" class="nav-link <?= $active_view == 'bin' ? 'active' : '' ?>">
                <span>🗑️ Recycle Bin</span>
                <?php if($bin_count > 0): ?>
                    <span style="font-size: 0.7rem; color: #64748b;">(<?= $bin_count ?>)</span>
                <?php endif; ?>
            </a>
            <a href="admin_dashboard.php?view=users" class="nav-link <?= $active_view == 'users' ? 'active' : '' ?>"><span>👥 User Accounts</span></a>
            <a href="admin_dashboard.php?view=events" class="nav-link <?= $active_view == 'events' ? 'active' : '' ?>"><span>📅 Manage Events</span></a>
            <a href="admin_dashboard.php?view=announcements" class="nav-link <?= $active_view == 'announcements' ? 'active' : '' ?>"><span>📢 Announcements</span></a>
        <?php endif; ?>

        <a href="logout.php" class="nav-link" style="color: #f87171; margin-top: 40px; padding-top: 20px;">Logout</a>
    </nav>
</div>

<div class="main">
    <h1 style="margin-top:0;">
        <?php 
            if ($is_dashboard_search) echo "Document Search Results";
            elseif($view == 'dashboard') echo "System Overview";
            elseif($view == 'pending') echo "Staff Uploads (Approval Needed)";
            elseif($view == 'bin') echo "Recycle Bin";
            elseif($view == 'edit') echo "Edit Document";
            elseif($view == 'events') echo "Event Management";
            elseif($view == 'users') echo "User Management";
            elseif($view == 'announcements') echo "Announcement Management";
            else echo "Archive Management";
        ?>
    </h1>
    <?php echo $message; ?>
    
    <?php if($view == 'edit' && isset($_GET['id'])): 
        $id = (int)$_GET['id'];
        $row = $conn->query("SELECT * FROM documents WHERE id=$id")->fetch_assoc();
    ?>
    <div class="card">
        <h3>Edit Details</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="update_id" value="<?= $row['id'] ?>">
            <input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>" required placeholder="Document Title">
            <input type="text" name="doc_number" value="<?= htmlspecialchars($row['doc_number']) ?>" placeholder="Document No.">
            <select name="category">
                <option <?= $row['category'] == 'Ordinance' ? 'selected' : '' ?>>Ordinance</option>
                <option <?= $row['category'] == 'Resolution' ? 'selected' : '' ?>>Resolution</option>
            </select>
            <input type="date" name="date_enacted" value="<?= $row['date_enacted'] ?>" required>
            <button type="submit" class="btn">Save Changes</button>
            <a href="admin_dashboard.php" class="btn" style="background: transparent !important; color: #64748b !important; border: 1px solid #cbd5e1 !important;">Cancel</a>
        </form>
    </div>
    <?php endif; ?>
    
    <?php if($view == 'dashboard'): ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="card" style="padding: 1.5rem !important;">
            <h3 style="margin:0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Documents</h3>
            <p style="font-size: 2.5rem; font-weight: 800; margin: 5px 0; color: var(--primary-dark);"><?= $total_docs_count ?></p>
            <a href="?view=active" style="font-size: 0.8rem; font-weight: 600; text-decoration: none;">View All Archives →</a>
        </div>
        <div class="card" style="padding: 1.5rem !important;">
             <h3 style="margin:0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Pending Review</h3>
            <p style="font-size: 2.5rem; font-weight: 800; margin: 5px 0; color: var(--gold);"><?= $pending_count ?></p>
            <a href="?view=pending" style="font-size: 0.8rem; font-weight: 600; text-decoration: none;">Review Submissions →</a>
        </div>
        <?php if ($current_role == 'admin'): ?>
        <div class="card" style="padding: 1.5rem !important;">
             <h3 style="margin:0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">System Users</h3>
            <p style="font-size: 2.5rem; font-weight: 800; margin: 5px 0; color: var(--primary-dark);"><?= $total_users_count ?></p>
            <a href="?view=users" style="font-size: 0.8rem; font-weight: 600; text-decoration: none;">Manage Users →</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3><span style="font-size: 1.5rem; vertical-align: middle;">🔎</span> Search Documents</h3>
        <p style="color: var(--text-muted); margin-top: -1rem; margin-bottom: 1.5rem;">Quickly find any document in the active archives.</p>
        <form method="GET" action="admin_dashboard.php">
            <input type="hidden" name="view" value="dashboard">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="Enter title, document number, etc..." value="<?= htmlspecialchars($search_q) ?>" style="margin-bottom: 0; flex-grow: 1; background: white;">
                <button type="submit" class="btn" style="width: auto; white-space: nowrap;">Search</button>
                <?php if($is_dashboard_search): ?>
                    <a href="admin_dashboard.php?view=dashboard" class="btn" style="width: auto; background: #64748b !important;">Clear Search</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (!$is_dashboard_search): ?>
        <div class="card">
            <h3 style="border-bottom:1px solid #e2e8f0; padding-bottom:15px; margin-bottom: 20px;">Recent Activity Log</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Time</th>
                    </tr>
                </thead>
                    <tbody>
                        <?php
                    // Get total for pagination
                    $total_logs = $conn->query("SELECT COUNT(*) as count FROM activity_log")->fetch_assoc()['count'];
                    $total_pages = ceil($total_logs / $limit);

                    $logs = $conn->query("SELECT * FROM activity_log ORDER BY id DESC LIMIT $offset, $limit");
                    while($log = $logs->fetch_assoc()):
                    ?>
                        <tr>
                            <td style="font-weight:bold; color:#1e293b;"><?= htmlspecialchars($log['username']) ?></td>
                            <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td style="color:#64748b;"><?= htmlspecialchars($log['details']) ?></td>
                            <td style="font-size:0.8rem; color:#94a3b8;"><?= date("M d, h:i A", strtotime($log['timestamp'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                <a href="?view=dashboard&page=<?= $page - 1 ?>">← Prev</a>
            <?php endif; ?>
            
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?view=dashboard&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                <a href="?view=dashboard&page=<?= $page + 1 ?>">Next →</a>
            <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if($view == 'events' && $current_role == 'admin'): ?>
    <div class="card">
        <h3><?= $edit_event ? 'Edit Event' : 'Create New Event' ?></h3>
        <form method="POST" class="form-grid" style="grid-template-columns: 1fr;">
            <input type="hidden" name="event_id" value="<?= $edit_event['id'] ?? 0 ?>">
            <input type="text" name="event_title" placeholder="Event Title" value="<?= htmlspecialchars($edit_event['event_title'] ?? '') ?>" required>
            <input type="date" name="event_date" value="<?= htmlspecialchars($edit_event['event_date'] ?? '') ?>" required>
            <input type="text" name="event_location" placeholder="Event Location / Venue" value="<?= htmlspecialchars($edit_event['event_location'] ?? '') ?>">
            <textarea name="event_description" placeholder="Event Description (optional)"><?= htmlspecialchars($edit_event['event_description'] ?? '') ?></textarea>
            <button type="submit" name="save_event" class="btn-add" style="width: auto; justify-self: start;"><?= $edit_event ? 'Update Event' : 'Create Event' ?></button>
            <?php if ($edit_event): ?>
                <a href="admin_dashboard.php?view=events" style="display:inline-block; margin-left:10px; color:#64748b;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card">
        <h3>Existing Events</h3>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Title</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php 
                    $total_events = $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];
                    $total_pages = ceil($total_events / $limit);

                    $ev_res = $conn->query("SELECT * FROM events ORDER BY event_date DESC LIMIT $offset, $limit"); 
                    while($er = $ev_res->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <b><?= htmlspecialchars($er['event_title']) ?></b>
                            <?php if(!empty($er['event_location'])): ?>
                                <br><small style="color:#2563eb;">📍 <?= htmlspecialchars($er['event_location']) ?></small>
                            <?php endif; ?>
                            <br><small style="color:#64748b;"><?= htmlspecialchars($er['event_description']) ?></small>
                        </td>
                        <td><?= date("M d, Y", strtotime($er['event_date'])) ?></td>
                        <td>
                            <a href="admin_dashboard.php?view=events&edit=<?= $er['id'] ?>" class="btn-edit">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event?');"><input type="hidden" name="event_id" value="<?= $er['id'] ?>"><button type="submit" name="delete_event" class="btn-del" style="border:none; background:transparent; cursor:pointer;">Remove</button></form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?view=events&page=<?= $page - 1 ?>">← Prev</a>
            <?php endif; ?>
            
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?view=events&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?view=events&page=<?= $page + 1 ?>">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($view == 'users' && $current_role == 'admin'): ?>
    <div class="card">
        <h3>Add New Staff/Admin</h3>
        <form method="POST" class="form-grid">
            <input type="text" name="new_username" placeholder="Username" required>
            <input type="text" name="new_password" placeholder="Password" required>
            <select name="new_role">
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit" name="add_user" class="btn-add">Create User</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing Accounts</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Username</th><th>Role</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php $u_res = $conn->query("SELECT * FROM users"); while($u_row = $u_res->fetch_assoc()): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($u_row['username']) ?></b></td>
                        <td>
                            <span class="badge <?= ($u_row['role'] == 'admin') ? 'badge-admin' : 'badge-staff' ?>">
                                <?= htmlspecialchars($u_row['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if($u_row['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this user? This cannot be undone.');">
                                    <input type="hidden" name="user_id" value="<?= $u_row['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn-del" style="border:none; background:transparent; cursor:pointer;">Remove</button>
                                </form>
                            <?php else: ?><small style="color: #94a3b8;">(You)</small><?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($view == 'announcements' && $current_role == 'admin'): ?>
    <div class="card">
        <h3><?= $edit_ann ? 'Edit Announcement' : 'Create New Public Post' ?></h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid" style="grid-template-columns: 1fr;">
            <input type="hidden" name="ann_id" value="<?= $edit_ann['id'] ?? 0 ?>">
            <input type="text" name="title" placeholder="Announcement Title (e.g. Holiday Notice)" value="<?= htmlspecialchars($edit_ann['title'] ?? '') ?>" required>
            <textarea name="content" placeholder="Write the announcement details here..." required><?= htmlspecialchars($edit_ann['content'] ?? '') ?></textarea>
            <div class="field-group">
                <label style="font-size: 0.75rem; color: #64748b; font-weight: 700;">Attach Photos (Optional - Select multiple)</label>
                <input type="file" name="images[]" accept="image/*" multiple style="padding: 10px; background: white;">
                <?php if ($edit_ann && $edit_ann['image']): ?>
                    <div style="margin-top: 10px; display: flex; gap: 15px; flex-wrap: wrap;">
                        <?php 
                        $imgs = json_decode($edit_ann['image'], true) ?: [];
                        foreach($imgs as $img): ?>
                            <?php $f = is_array($img) ? $img['file'] : $img; ?>
                            <div style="width: 150px; background: #f1f5f9; padding: 8px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <img src="uploads/<?= htmlspecialchars($f) ?>" style="width: 100%; height: 80px; object-fit: cover; border-radius: 4px; display: block; margin-bottom: 5px;">
                                <input type="text" name="image_captions[<?= htmlspecialchars($f) ?>]" value="<?= htmlspecialchars($img['caption'] ?? '') ?>" placeholder="Alt text..." style="font-size: 0.7rem; padding: 4px !important; height: auto; margin-bottom: 5px;">
                                <label style="font-size: 0.7rem; color: #ef4444; font-weight: bold; cursor: pointer; display: block; text-align: center;">
                                    <input type="checkbox" name="remove_images[]" value="<?= htmlspecialchars($f) ?>"> Remove
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <button type="submit" name="save_announcement" class="btn-add" style="width: auto; justify-self: start;"><?= $edit_ann ? 'Update Announcement' : 'Publish' ?></button>
            <?php if ($edit_ann): ?>
                <a href="admin_dashboard.php?view=announcements" style="display:inline-block; margin-left:10px; color:#64748b;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>Current Public Posts</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Post Details</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $total_anns = $conn->query("SELECT COUNT(*) as count FROM announcements")->fetch_assoc()['count'];
                    $total_pages = ceil($total_anns / $limit);

                    $ann_res = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT $offset, $limit"); 
                    while($ar = $ann_res->fetch_assoc()): ?>
                    <tr style="border-bottom: 2px solid #f1f5f9;">
                        <td style="padding: 20px 15px;">
                            <strong><?= htmlspecialchars($ar['title']) ?></strong>
                            <?php if($ar['image']): ?> <span class="badge" style="background:#dbeafe; color:#1e40af;">📷 Image</span> <?php endif; ?>
                            <div class="announcement-body" style="color:#64748b; line-height: 1.4; margin-top: 5px;">
                                <?php 
                                $full_text = $ar['content'];
                                $limit = 300; 
                                if (strlen($full_text) > $limit): 
                                    $preview = substr($full_text, 0, $limit) . "...";
                                ?>
                                    <span class="text-preview"><?= nl2br(htmlspecialchars($preview)) ?></span>
                                    <span class="text-full" style="display:none;"><?= nl2br(htmlspecialchars($full_text)) ?></span>
                                    <button onclick="toggleReadMore(this)" class="read-more-btn">Read More</button>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($full_text)) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= date('M d, Y', strtotime($ar['created_at'])) ?></td>
                        <td>
                            <a href="admin_dashboard.php?view=announcements&edit=<?= $ar['id'] ?>" class="btn-edit">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this post?');">
                                <input type="hidden" name="ann_id" value="<?= $ar['id'] ?>">
                                <button type="submit" name="delete_announcement" class="btn-del" style="border:none; background:transparent; cursor:pointer;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?view=announcements&page=<?= $page - 1 ?>">← Prev</a>
            <?php endif; ?>
            
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?view=announcements&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?view=announcements&page=<?= $page + 1 ?>">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if(in_array($view, ['active', 'pending', 'bin']) || $is_dashboard_search): ?>
    
    <?php if($view == 'active'): ?>
    <div class="card">
        <h3>Quick Upload</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="text" name="title" placeholder="Document Title" required>
            <input type="text" name="doc_number" placeholder="Doc No.">
            <select name="category"><option>Ordinance</option><option>Resolution</option></select>
            <input type="date" name="date_enacted" required>
            <input type="file" name="pdf_file" accept=".pdf" required style="padding: 10px; background: white;">
            <button type="submit" class="btn" style="background: var(--primary-dark) !important;">Upload Document</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!$is_dashboard_search): ?>
    <div style="margin-bottom: 20px;">
        <form method="GET" action="admin_dashboard.php" style="display: flex; gap: 10px;">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <input type="text" name="search" placeholder="Search by title or document number..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" style="margin-bottom: 0; background: white;">
            <button type="submit" class="btn" style="width: auto; white-space: nowrap;">Search</button>
            <?php if(isset($_GET['search']) && !empty($_GET['search'])): ?>
                <a href="admin_dashboard.php?view=<?= htmlspecialchars($view) ?>" class="btn" style="width: auto; background: #64748b !important;">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <?php
                    // Filter logic based on the sidebar view
                    $params = [];
                    $types = "";
                    $where_clause = "";

                    if ($view == 'pending') {
                        $where_clause = "WHERE status = 'hidden' AND is_deleted = 0";
                    } elseif ($view == 'bin') {
                        $where_clause = "WHERE is_deleted = 1";
                    } else { // This is now only for the 'active' view
                        if ($current_role == 'admin') {
                            $where_clause = "WHERE is_deleted = 0 AND status != 'hidden'";
                        } else { // Staff view
                            $where_clause = "WHERE is_deleted = 0 AND status != 'hidden' AND uploaded_by = ?";
                            $params[] = $username;
                            $types .= "s";
                        }
                    }

                    // Search Logic
                    if (!empty($search_q)) {
                        $where_clause .= " AND (title LIKE ? OR doc_number LIKE ?)";
                        $params[] = "%$search_q%";
                        $params[] = "%$search_q%";
                        $types .= "ss";
                    }
                    
                    // 1. Get Total Count
                    $count_sql = "SELECT COUNT(*) as total FROM documents " . $where_clause;
                    $count_stmt = $conn->prepare($count_sql);
                    if(!empty($params)) $count_stmt->bind_param($types, ...$params);
                    $count_stmt->execute();
                    $total_docs = $count_stmt->get_result()->fetch_assoc()['total'];
                    $total_pages = ceil($total_docs / $limit);
    ?>
    <form method="POST" action="admin_dashboard.php?<?= http_build_query($_GET) ?>">
    <?php if($total_docs > 0): ?>
    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <select name="bulk_action" style="width: auto; margin-bottom: 0; background: white;">
            <option value="">Bulk Actions...</option>
            <?php if($view == 'pending' && $current_role == 'admin'): ?>
                <option value="approve">Approve Selected</option>
                <option value="reject">Reject Selected</option>
            <?php endif; ?>
            <?php if($view == 'bin' && $current_role == 'admin'): ?>
                <option value="restore">Restore Selected</option>
            <?php else: ?>
                <option value="soft_delete">Move to Recycle Bin</option>
            <?php endif; ?>
        </select>
        <button type="submit" class="btn" style="width: auto; white-space: nowrap;">Apply</button>
    </div>
    <?php endif; ?>

    <div class="card" style="padding:0;">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:1%;"><input type="checkbox" id="select-all"></th>
                        <th>Document Details</th>
                        <th>By</th>
                        <th>Category</th>
                        <th style="text-align:center;">Status</th>
                        <?php if($current_role == 'admin' && $view != 'pending'): ?>
                            <th style="text-align:center;">Public</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // For colspan calculation
                    $num_cols = 6; // Increased for the new Category column
                    if($current_role == 'admin' && $view != 'pending') { $num_cols++; } // For the Public toggle

                    // Fetch Data with Limit
                    $sql = "SELECT * FROM documents " . $where_clause . " ORDER BY id DESC LIMIT ?, ?";
                    $params[] = $offset;
                    $params[] = $limit;
                    $types .= "ii";

                    $stmt = $conn->prepare($sql);
                    if(!empty($params)) $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    if($total_docs == 0) echo "<tr><td colspan='$num_cols' style='text-align:center; padding:40px; color:#94a3b8;'>No documents found here.</td></tr>";

                    while($res && $row = $res->fetch_assoc()):
                        $st = $row['status'];
                    ?>
                    <tr>
                        <td><input type="checkbox" name="doc_ids[]" value="<?= $row['id'] ?>" class="doc-checkbox"></td>
                        <td>
                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['title']) ?></div>
                            <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($row['doc_number']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($row['uploaded_by']) ?></td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($row['category']) ?>">
                                <?= htmlspecialchars($row['category']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php if($st == 'hidden'): ?>
                                <span class="badge" style="background:#fef3c7; color:#92400e;">PENDING</span>
                            <?php elseif($st == 'rejected'): ?>
                                <span class="badge" style="background:#fee2e2; color:#ef4444;">REJECTED</span>
                            <?php else: ?>
                                <span class="badge" style="background:#dcfce7; color:#166534;">ACCEPTED</span>
                            <?php endif; ?>
                        </td>
                        
                        <?php if($current_role == 'admin' && $view != 'pending'): ?>
                        <td style="text-align:center;">
                            <?php if($view == 'active'): ?>
                            <label class="switch">
                                <input type="checkbox" <?= ($st == 'public') ? 'checked' : '' ?> <?= ($st == 'rejected') ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <span class="slider"></span>
                            </label>
                            <form method="POST" style="display:none;">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="action" value="<?= ($st == 'public') ? 'toggle_private' : 'toggle_public' ?>">
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>

                        <td>
                            <div style="display:flex; gap:5px; align-items:center;">
                                <a href="view_file.php?id=<?= $row['id'] ?>" target="_blank" class="btn-sm btn-review">Review</a>
                                <a href="admin_dashboard.php?view=edit&id=<?= $row['id'] ?>" class="btn-sm btn-review" style="background:#3b82f6;">Edit</a>
                                
                                <?php if($current_role == 'admin' && $view == 'pending'): ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $row['id'] ?>"><button type="submit" name="action" value="reject" class="btn-sm btn-reject">Reject</button></form>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $row['id'] ?>"><button type="submit" name="action" value="approve" class="btn-sm btn-approve">Approve</button></form>
                                <?php endif; ?>

                                <?php if($view == 'bin' && $current_role == 'admin'): ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $row['id'] ?>"><button type="submit" name="action" value="restore" class="btn-sm btn-approve">Restore</button></form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('This will permanently delete the file and record. Are you sure?');"><input type="hidden" name="id" value="<?= $row['id'] ?>"><button type="submit" name="action" value="perm_delete" class="btn-sm btn-reject">Destroy</button></form>
                                <?php endif; ?>

                                <?php if($view != 'bin'): ?>
                                    <form method="POST" style="display:inline; margin-left:auto;" onsubmit="return confirm('Move this item to the Recycle Bin?');">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="action" value="soft_delete" style="background:none; border:none; cursor:pointer; padding:0; font-size:1.2rem;" title="Move to Recycle Bin">
                                            🗑️
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php 
            $qs = "&view=" . urlencode($view);
            if(!empty($search_q)) $qs .= "&search=" . urlencode($search_q);
        ?>
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $qs ?>">← Prev</a>
            <?php endif; ?>
            
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?page=<?= $i ?><?= $qs ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $qs ?>">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    </form>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('select-all');
        const docCheckboxes = document.querySelectorAll('.doc-checkbox');

        if (selectAllCheckbox && docCheckboxes.length > 0) {
            // Handle "Select All" checkbox
            selectAllCheckbox.addEventListener('change', function() {
                docCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });

            // Update "Select All" checkbox based on individual checkboxes
            docCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    } else {
                        // Check if all other checkboxes are checked
                        const allChecked = Array.from(docCheckboxes).every(c => c.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
        }

        // Read More Toggle Function for Announcements
        window.toggleReadMore = function(btn) {
            const container = btn.parentElement;
            const preview = container.querySelector('.text-preview');
            const full = container.querySelector('.text-full');
            
            if (full.style.display === "none") {
                full.style.display = "inline";
                preview.style.display = "none";
                btn.textContent = "Read Less";
            } else {
                full.style.display = "none";
                preview.style.display = "inline";
                btn.textContent = "Read More";
            }
        };
    });
</script>

</body>
</html>