<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details</title>
    <link rel="stylesheet" href="../Components/css/Request.css">
</head>
<body>
    <div class="page">
        <?php include '../Components/NavBar.php'; ?>
  <header class="page-header">
    <h1 class="page-title">Request Details</h1>
    <span class="page-subtitle">Maintenance Personnel Portal · Barangay Gulod</span>
  </header>

  <div class="rd-layout">
    <div class="rd-main">
      <section class="panel">
        <div class="rd-title-row">
          <div>
            <h2 class="rd-title">Broken Aircon</h2>
            <span class="rd-sub">Jose Mar · Barangay Gulod</span>
          </div>
          <div class="rd-actions">
            <span class="tag tag-mid">In Progress</span>
            <button class="btn-primary">Update Maintenance</button>
          </div>
        </div>

        <h3 class="section-label">Request Information</h3>
        <div class="form-grid">
          <div class="field"><label>Request ID</label><div class="field-value">MT-2026-001</div></div>
          <div class="field"><label>Date Reported</label><div class="field-value">Sept 10, 2026</div></div>
          <div class="field"><label>Facility</label><div class="field-value">Barangay Hall</div></div>
          <div class="field"><label>Specific Area</label><div class="field-value">2nd Floor</div></div>
          <div class="field"><label>Affected Equipment</label><div class="field-value">Aircon Unit 02</div></div>
          <div class="field"><label>Category</label><div class="field-value">Electrical</div></div>
          <div class="field"><label>Reported By</label><div class="field-value">Pedro Santos</div></div>
        </div>
        <div class="field field-full">
          <label>Problem Description</label>
          <div class="field-value field-textarea">Aircon unit is not cooling properly and making unusual noise. Needs inspection and possible repair.</div>
        </div>
      </section>

      <section class="panel">
        <h3 class="section-label">Maintenance Update</h3>
        <div class="update-post">
          <div class="update-post-head">
            <span class="update-author">Pedro Santos</span>
            <span class="update-date">Sept 10, 2026 · 3:45PM</span>
          </div>
          <p class="update-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit. In quam purus, tempor eget semper vitae, euismod a ipsum. Sed ac consectetur nibh, vel malesuada purus.</p>
          <div class="update-photo">
            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="10" r="1.6" stroke="currentColor" stroke-width="1.4"/><path d="M4 17l5-5 3 3 4-4 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
        </div>
      </section>
    </div>

    <aside class="rd-side">
      <section class="panel">
        <h3 class="section-label">Maintenance Timeline</h3>
        <ul class="timeline">
          <li class="timeline-item done">
            <span class="dot"></span>
            <div><strong>Request Submitted</strong><span>Sept 10, 2026 · 8:15 AM</span></div>
          </li>
          <li class="timeline-item done">
            <span class="dot"></span>
            <div><strong>Assigned to Maintenance Personnel</strong><span>Jose Mar · Sept 10, 2026 · 9:00 AM</span></div>
          </li>
          <li class="timeline-item done">
            <span class="dot"></span>
            <div><strong>In Progress</strong><span>Sept 10, 2026 · 11:30 AM</span></div>
          </li>
          <li class="timeline-item">
            <span class="dot dot-empty"></span>
            <div><strong class="muted">Completed</strong></div>
          </li>
        </ul>
      </section>

      <section class="panel">
        <h3 class="section-label">Activity Log</h3>
        <ul class="activity-log">
          <li>
            <strong>Task Assigned</strong>
            <span>Pedro Santos accepted the task · Sept 10, 10:00 AM</span>
          </li>
          <li>
            <strong>Status Updated to In Progress</strong>
            <span>Initial inspection started · Sept 10, 11:30 AM</span>
          </li>
        </ul>
      </section>
    </aside>
  </div>
</div>
</body>
</html>