<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../Components/css/Dashboard.css">   
</head>
<body>
    <div class="page">
        <?php include '../Components/NavBar.php'; ?>
  <header class="topbar">
    <div>
      <h1 class="page-title">My Dashboard</h1>
      <span class="page-subtitle">Maintenance Personnel Portal · Barangay Gulod</span>
    </div>
    <div class="topbar-user">Juan Dela Cruz</div>
  </header>

  <section class="greeting-card">
    <h2>Good Morning, Jose!</h2>
    <p>Here's your maintenance workload and the latest task updates for Barangay Gulod.</p>
    <button class="btn-primary">View My Task</button>
  </section>

  <section class="stat-grid">
    <div class="stat-card">
      <div>
        <span class="stat-label">My Task</span>
        <span class="stat-value">12</span>
      </div>
      <span class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="4" width="14" height="17" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 3h6v3H9z" stroke="currentColor" stroke-width="1.6"/><path d="M8 11h8M8 15h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </span>
    </div>
    <div class="stat-card">
      <div>
        <span class="stat-label">Pending</span>
        <span class="stat-value">4</span>
      </div>
      <span class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
    </div>
    <div class="stat-card">
      <div>
        <span class="stat-label">In Progress</span>
        <span class="stat-value">3</span>
      </div>
      <span class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none"><path d="M6 3h12M6 21h12M7 3c0 5 5 6 5 9s-5 4-5 9M17 3c0 5-5 6-5 9s5 4 5 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
    </div>
    <div class="stat-card">
      <div>
        <span class="stat-label">Completed</span>
        <span class="stat-value">5</span>
      </div>
      <span class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
    </div>
  </section>

  <section class="panel-grid">
    <div class="panel">
      <h3>Task Priorities</h3>
      <ul class="priority-list">
        <li>
          <div>
            <span class="task-id">MT-10001 Broken Aircon</span>
            <span class="task-loc">Admin Office</span>
          </div>
          <span class="tag tag-high">High</span>
        </li>
        <li>
          <div>
            <span class="task-id">MT-10001 Broken Aircon</span>
            <span class="task-loc">Admin Office</span>
          </div>
          <span class="tag tag-low">Low</span>
        </li>
        <li>
          <div>
            <span class="task-id">MT-10001 Broken Aircon</span>
            <span class="task-loc">Admin Office</span>
          </div>
          <span class="tag tag-mid">Medium</span>
        </li>
      </ul>
    </div>

    <div class="panel">
      <h3>Task Progress</h3>
      <div class="progress-row">
        <div class="progress-label"><span>In Progress</span><span>50%</span></div>
        <div class="progress-track"><div class="progress-fill fill-blue" style="width:50%"></div></div>
      </div>
      <div class="progress-row">
        <div class="progress-label"><span>Pending</span><span>50%</span></div>
        <div class="progress-track"><div class="progress-fill fill-yellow" style="width:50%"></div></div>
      </div>
      <div class="progress-row">
        <div class="progress-label"><span>Completed</span><span>50%</span></div>
        <div class="progress-track"><div class="progress-fill fill-green" style="width:50%"></div></div>
      </div>
    </div>

    <div class="panel">
      <h3>Recent Updates</h3>
      <ul class="update-list">
        <li>
          <div class="update-row">
            <span class="update-title">Photo Uploaded</span>
            <span class="update-time">Sept 11, 2026 · 11:59PM</span>
          </div>
          <span class="update-meta">MT10001</span>
        </li>
        <li>
          <div class="update-row">
            <span class="update-title">New Task Assigned</span>
            <span class="update-time">Yesterday · 12:01AM</span>
          </div>
          <span class="update-meta">MT10001</span>
        </li>
        <li>
          <div class="update-row">
            <span class="update-title">Photo Uploaded</span>
            <span class="update-time">Sept 10, 2026 · 11:59PM</span>
          </div>
          <span class="update-meta">MT10001</span>
        </li>
      </ul>
    </div>
  </section>
</div>
</body>
</html>