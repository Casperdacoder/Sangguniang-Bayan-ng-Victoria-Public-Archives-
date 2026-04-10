<?php
include 'config.php';
$events_result = $conn->query("SELECT event_date, event_title, event_location, event_description FROM events ORDER BY event_date ASC");
$events_data = [];
while($row = $events_result->fetch_assoc()) {
    $date = $row['event_date'];
    if (!isset($events_data[$date])) {
        $events_data[$date] = [];
    }
    $events_data[$date][] = [
        'title' => $row['event_title'],
        'location' => $row['event_location'],
        'description' => $row['event_description']
    ];
}

// Fetch Announcements
$ann_res = $conn->query("SELECT * FROM announcements WHERE status = 'public' ORDER BY created_at DESC");
?>
<!doctype html>
<html>
  <head>
    <title>Events</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* Announcements Feed Styles */
        .news-feed { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .post-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 60px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-top: 5px solid var(--primary); }
        .post-content { padding: 25px; }
        .post-img { width: 100%; height: auto; display: block; object-fit: cover; max-height: 500px; }
        .news-feed h2 { margin-top: 0; color: #1e293b; font-size: 1.4rem; }
        .date { color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .post-divider { border: 0; border-top: 1px solid #e2e8f0; margin: 40px 0; }
        
        /* Read More Toggle */
        .read-more-btn {
            background: none;
            border: none;
            color: var(--accent);
            padding: 0;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            margin-top: 10px;
        }
    </style>
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
        <a href="archive.php" class="nav-cta">View Archives</a>
        <a href="events.php" class="nav-cta active-page">Events</a>
        <a href="login.php" class="nav-cta">Staff Login</a>
      </div>
    </nav>
    
    <div class="main-content">
      <!-- Calendar Card -->
      <div class="calendar-card">
        <div class="calendar-header">
            <button id="prev" class="calendar-nav-btn">&lt;</button>
            <h2 id="monthYear"></h2>
            <button id="next" class="calendar-nav-btn">&gt;</button>
        </div>
        <div class="calendar-body">
            <div class="days-grid">
                <div class="day-name">Sun</div><div class="day-name">Mon</div><div class="day-name">Tue</div><div class="day-name">Wed</div><div class="day-name">Thu</div><div class="day-name">Fri</div><div class="day-name">Sat</div>
            </div>
            <div class="dates-grid" id="dates"></div>
        </div>
      </div>

      <!-- Events Panel -->
      <div class="events-panel">
        <h3>Upcoming Events</h3>
        <div id="eventList">
            <div class="no-events">Select a highlighted date to view details.</div>
        </div>
      </div>
    </div>

    <!-- Announcements Feed -->
    <div class="news-feed">
        <h1 style="text-align: center; margin-bottom: 10px; color: var(--primary-dark);">Latest Announcements</h1>
        <hr style="border: 0; border-top: 1px solid #cbd5e1; margin-bottom: 40px; width: 100px;">

        <?php if ($ann_res && $ann_res->num_rows > 0): ?>
            <?php while($row = $ann_res->fetch_assoc()): ?>
                <div class="post-card">
                    <?php if($row['image']): ?>
                        <?php 
                            $imgs = json_decode($row['image'], true) ?: [['file' => $row['image'], 'caption' => '']];
                            $total = count($imgs);
                            $grid_class = ($total > 3) ? 'grid-more' : 'grid-'.$total; 
                        ?>
                        <div class="image-grid <?= $grid_class ?>">
                            <?php foreach(array_slice($imgs, 0, 3) as $index => $img): ?>
                                <?php $f = is_array($img) ? $img['file'] : $img; $cap = is_array($img) ? $img['caption'] : ''; ?>
                                <div class="grid-item">
                                    <img src="uploads/<?= htmlspecialchars($f) ?>" alt="<?= htmlspecialchars($cap) ?>" title="<?= htmlspecialchars($cap) ?>">
                                    <?php if($index === 2 && $total > 3): ?>
                                        <div class="more-overlay">+<?= $total - 3 ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="post-content">
                        <span class="date"><?= date('F j, Y', strtotime($row['created_at'])) ?></span>
                        <h2><?= htmlspecialchars($row['title']) ?></h2>
                        <div class="announcement-body" style="color: #475569; line-height: 1.6;">
                            <?php 
                            $full_text = $row['content'];
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
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; color: #94a3b8;">No announcements posted yet.</p>
        <?php endif; ?>
    </div>

    <script>
      const datesEl = document.getElementById("dates");
      const monthYearEl = document.getElementById("monthYear");
      const eventListEl = document.getElementById("eventList");

      let currentDate = new Date();

      const events = <?php echo json_encode($events_data); ?>;

      function renderCalendar() {
        datesEl.innerHTML = "";
        eventListEl.innerHTML = "";

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        monthYearEl.textContent = currentDate.toLocaleString("default", {
          month: "long",
          year: "numeric",
        });

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) {
          datesEl.appendChild(document.createElement("div"));
        }

        for (let day = 1; day <= daysInMonth; day++) {
          const dateDiv = document.createElement("div");
          dateDiv.textContent = day;
          dateDiv.classList.add("date-cell");

          const dateKey = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;

          if (events[dateKey]) {
            dateDiv.classList.add("has-event");
          }

          if (
            day === new Date().getDate() &&
            month === new Date().getMonth() &&
            year === new Date().getFullYear()
          ) {
            dateDiv.classList.add("today");
          }

          dateDiv.addEventListener("click", () => showEvents(dateKey));
          datesEl.appendChild(dateDiv);
        }
      }

      function showEvents(dateKey) {
        eventListEl.innerHTML = "";

        if (!events[dateKey]) {
          eventListEl.innerHTML = '<div class="no-events">No events scheduled for this date.</div>';
          return;
        }

        events[dateKey].forEach((eventObj) => {
          const div = document.createElement("div");
          div.className = "event-item";
          // Format date for display
          const dateObj = new Date(dateKey);
          const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
          
          let locHtml = eventObj.location ? `<div style="font-size:0.85rem; color:#64748b; margin-top:4px;">📍 ${eventObj.location}</div>` : '';
          let descHtml = eventObj.description ? `<div class="event-desc">${eventObj.description}</div>` : '';

          div.innerHTML = `<span class="event-date-tag">${dateStr}</span><div class="event-title">${eventObj.title}</div>${locHtml}${descHtml}`;
          eventListEl.appendChild(div);
        });
      }

      document.getElementById("prev").onclick = () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
      };

      document.getElementById("next").onclick = () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
      };

      function toggleReadMore(btn) {
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
      }

      renderCalendar();
    </script>
  </body>
  <footer>
    <p>&copy; 2026 Municipality of Victoria. All Rights Reserved.</p>
  </footer>
</html>