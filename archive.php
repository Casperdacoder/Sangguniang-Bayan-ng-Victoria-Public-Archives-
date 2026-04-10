<?php
/**
 * SB Archive - Public Search
 * Displays only 'public' status documents
 */
include 'config.php';

// Handle Search and Filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'All';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build the SQL Query dynamically - CRITICAL: Filter for 'public' status only
$sql = "SELECT * FROM documents WHERE status = 'public'";
$params = [];
$types = "";

// Text Search Filter
if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR doc_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Category Filter
if ($category !== 'All') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Date Range Filter
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND date_enacted BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
} elseif (!empty($start_date)) {
    $sql .= " AND date_enacted >= ?";
    $params[] = $start_date;
    $types .= "s";
} elseif (!empty($end_date)) {
    $sql .= " AND date_enacted <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$sql .= " ORDER BY date_enacted DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Archive | Municipality of Victoria</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

<nav>
    <div class="logo-section">
        <img src="Pics/logo.jpg" alt="Victoria Logo">
        <div>
            <div style="font-weight: 900; font-size: 2.2rem; color: var(--primary);">SANGGUNIANG BAYAN NG VICTORIA</div>
            <div style="font-size: 0.9rem; letter-spacing: 1px;">ORIENTAL MINDORO</div>
        </div>
    </div>
    <div class="nav-links">
        <a href="index.php" class="nav-cta">Home</a>
        <a href="archive.php" class="nav-cta active-page">View Archives</a>
        <a href="events.php" class="nav-cta">Events</a>
        <a href="login.php" class="nav-cta">Staff Login</a>
    </div>
</nav>

<header>
    <h1 style="margin:0;">Sangguniang Bayan Public Archive</h1>
    <p style="opacity: 0.8; margin: 10px 0 0 0;">Search official ordinances, resolutions, and meeting minutes.</p>
</header>

<div class="container">
    <form class="search-box" method="GET">
        <div class="field-group" style="grid-column: span 2;">
            <label>Search Keyword</label>
            <input type="text" name="search" placeholder="Enter title or document number..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="field-group">
            <label>Category</label>
            <select name="category">
                <option value="All">All Types</option>
                <option value="Ordinance" <?php if($category == 'Ordinance') echo 'selected'; ?>>Ordinance</option>
                <option value="Resolution" <?php if($category == 'Resolution') echo 'selected'; ?>>Resolution</option>
                <option value="Minutes" <?php if($category == 'Minutes') echo 'selected'; ?>>Minutes</option>
            </select>
        </div>

        <div class="field-group">
            <label>From Date</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
        </div>

        <div class="field-group">
            <label>To Date</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
        </div>

        <button type="submit" class="btn-search">Search Archive</button>
    </form>

    <div class="results-card" style="overflow: visible;">
        <div class="result-count">
             Found <b><?php echo $result->num_rows; ?></b> verified public documents.
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Document Details</th>
                        <th>Type</th>
                        <th>Enacted Date</th>
                        <th>File</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; font-size: 1rem; color: #0f172a;"><?php echo $row['title']; ?></div>
                                    <div style="color: #64748b; font-size: 0.8rem; margin-top: 3px;"><?php echo $row['doc_number']; ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $row['category']; ?>">
                                        <?php echo $row['category']; ?>
                                    </span>
                                </td>
                                <td style="font-weight: 500; font-size: 0.9rem;"><?php echo date("M d, Y", strtotime($row['date_enacted'])); ?></td>
                                <td>
                                    <a href="view_file.php?id=<?php echo $row['id']; ?>" class="view-link" target="_blank">
                                        VIEW PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 60px; color: #94a3b8;">
                                <img src="https://cdn-icons-png.flaticon.com/512/6134/6134065.png" style="width: 50px; opacity: 0.3; margin-bottom: 10px;"><br>
                                No public records found matching your search.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p style="text-align: center; color: #94a3b8; font-size: 0.8rem; margin-top: 40px;">
        Official Archive of Victoria &copy; 2026. All documents are verified by the Office of the Sangguniang Bayan.
    </p>
</div>

<!-- Loading Overlay -->
<div class="loader-overlay">
    <div class="spinner"></div>
    <div class="loading-text">Searching Archive...</div>
</div>

<script>
    const searchForm = document.querySelector('.search-box');
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