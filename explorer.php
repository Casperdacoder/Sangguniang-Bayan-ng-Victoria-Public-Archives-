<?php 
include 'config.php'; 

// Check if you want this page locked. 
// If ONLY staff should search, keep the next line. 
// If the PUBLIC should search, add // to the start of the next line.
protect_page(); 

// 1. Get Search Input
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// 2. Build Query for the 'documents' table
$sql = "SELECT id, title, doc_number, category, date_enacted FROM documents WHERE is_deleted = 0";

$params = [];
$types = "";

if (!empty($q)) {
    $sql .= " AND (title LIKE ? OR doc_number LIKE ? OR category LIKE ?)";
    $search_param = "%$q%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types = "sss";
}

$sql .= " ORDER BY date_enacted DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <title>Archive Explorer | Sangguniang Bayan</title>
</head>
<body>
    <nav>
        <div class="logo-section">
            <img src="logo.png" alt="Victoria Logo">
            <div>
                <div style="font-weight: 900; font-size: 1.2rem; color: var(--primary);">VICTORIA</div>
                <div style="font-size: 0.7rem; letter-spacing: 1px;">MUNICIPAL GOVERNMENT</div>
            </div>
        </div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="archive.php" class="nav-cta">View Archives</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="<?= $_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'staff_dashboard.php' ?>">Dashboard</a>
            <?php else: ?>
                <a href="login.php">Staff Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <div style="margin: 40px 0;">
            <h1>Legislative Explorer</h1>
            <p style="color: #64748b;">Search through official records, ordinances, and meeting minutes.</p>
        </div>

        <form method="GET" action="explorer.php" style="display: flex; gap: 10px; margin-bottom: 40px; flex-wrap: wrap;">
            <input type="text" name="q" 
                   placeholder="Type keywords, year, or document title..." 
                   value="<?= htmlspecialchars($q) ?>" 
                   style="flex: 1; margin-bottom: 0; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
            <button type="submit" class="btn">Search Archive</button>
        </form>

        <div style="margin-bottom: 20px;">
            <?php if (!empty($q)): ?>
                <p>Showing results for: <strong><?= htmlspecialchars($q) ?></strong></p>
            <?php else: ?>
                <p style="color: #64748b;">Displaying all recent records</p>
            <?php endif; ?>

            

            <?php if ($res && $res->num_rows > 0): ?>
                <?php while($row = $res->fetch_assoc()): ?>
                    <div class="doc-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span style="font-size: 10px; background: #eff6ff; color: #2563eb; padding: 3px 10px; border-radius: 50px; font-weight: bold; text-transform: uppercase; border: 1px solid #dbeafe;">
                                <?= htmlspecialchars($row['category']) ?>
                            </span>
                            
                            <h3 style="margin: 10px 0 5px 0; color: #0f172a;"><?= htmlspecialchars($row['title']) ?></h3>
                            <small style="color: #64748b;">
                                <?= !empty($row['doc_number']) ? 'Doc #: ' . htmlspecialchars($row['doc_number']) . ' | ' : '' ?>
                                Date: <strong><?= date("M d, Y", strtotime($row['date_enacted'])) ?></strong>
                            </small>
                        </div>
                        <div>
                            <a href="view_file.php?id=<?= $row['id'] ?>" target="_blank" class="btn" style="white-space: nowrap;">View Document</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 12px; border: 2px dashed #cbd5e1;">
                    <p style="color: #64748b; font-size: 1.1rem;">No documents found matching your criteria.</p>
                    <a href="explorer.php" style="color: var(--blue); font-weight: 600;">View All Records</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="text-align: center; margin-top: 50px; padding: 20px; color: #94a3b8; font-size: 0.8rem;">
        &copy; 2026 Sangguniang Bayan Archive System | Port 3307
    </footer>

    <!-- Loading Overlay -->
    <div class="loader-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Fetching Records...</div>
    </div>

    <script>
        const searchForm = document.querySelector('form');
        const loader = document.querySelector('.loader-overlay');

        if (searchForm) {
            searchForm.addEventListener('submit', function() {
                loader.classList.add('active');
            });
        }

        // Hide loader when navigating back (bfcache)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                loader.classList.remove('active');
            }
        });
    </script>
</body>
</html>