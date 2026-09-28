<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks</title>
    <link rel="stylesheet" href="../Components/css/Task.css">
</head>
<body>
    <div class="page">
        <?php include '../Components/NavBar.php'; ?>
  <header class="page-header">
    <h1 class="page-title">My Task</h1>
    <span class="page-subtitle">Maintenance Personnel Portal · Barangay Gulod</span>
  </header>

  <section class="stat-grid">
    <div class="stat-card">
      <span class="stat-label">My Task</span>
      <span class="stat-value">12</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Pending</span>
      <span class="stat-value">4</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">In Progress</span>
      <span class="stat-value">3</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Completed</span>
      <span class="stat-value">5</span>
    </div>
  </section>

  <section class="filter-bar">
    <div class="search-input">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      <input type="text" placeholder="Search task...">
    </div>
    <button class="filter-btn icon-only">
      <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="17" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 9h18M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </button>
    <button class="filter-btn">All Status
      <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <button class="filter-btn">All Facilities
      <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <button class="reset-btn">Reset</button>
  </section>

  <section class="task-list">
    <article class="task-card">
      <div class="task-card-top">
        <h3>Broken Aircon</h3>
        <span class="tag tag-high">High</span>
      </div>
      <span class="task-ref">MT-10101 · Barangay Hall · 3rd Floor</span>
      <p class="task-desc">Lorem ipsum dolor sit amet, consectetur adipiscing elit. In quam purus, tempor eget semper vitae, euismod a ipsum. Sed ac consectetur nibh, vel malesuada purus.</p>
      <div class="task-card-bottom">
        <div class="task-meta">
          <span class="meta-chip">Aircon</span>
          <span class="meta-date">Sept 11, 2026</span>
        </div>
        <button class="btn-primary">View Details</button>
      </div>
    </article>

    <article class="task-card">
      <div class="task-card-top">
        <h3>Broken Aircon</h3>
        <span class="tag tag-mid">Medium</span>
      </div>
      <span class="task-ref">MT-10101 · Barangay Hall · 3rd Floor</span>
      <p class="task-desc">Lorem ipsum dolor sit amet, consectetur adipiscing elit. In quam purus, tempor eget semper vitae, euismod a ipsum. Sed ac consectetur nibh, vel malesuada purus.</p>
      <div class="task-card-bottom">
        <div class="task-meta">
          <span class="meta-chip">Aircon</span>
          <span class="meta-date">Sept 11, 2026</span>
        </div>
        <button class="btn-primary">View Details</button>
      </div>
    </article>

    <article class="task-card">
      <div class="task-card-top">
        <h3>Broken Aircon</h3>
        <span class="tag tag-low">Low</span>
      </div>
      <span class="task-ref">MT-10101 · Barangay Hall · 3rd Floor</span>
      <p class="task-desc">Lorem ipsum dolor sit amet, consectetur adipiscing elit. In quam purus, tempor eget semper vitae, euismod a ipsum. Sed ac consectetur nibh, vel malesuada purus.</p>
      <div class="task-card-bottom">
        <div class="task-meta">
          <span class="meta-chip">Aircon</span>
          <span class="meta-date">Sept 11, 2026</span>
        </div>
        <button class="btn-primary">View Details</button>
      </div>
    </article>
  </section>

  <nav class="pagination">
    <button class="page-num active">1</button>
    <button class="page-num">2</button>
    <button class="page-num">3</button>
    <button class="page-num">4</button>
    <button class="page-num">5</button>
    <button class="page-num">6</button>
  </nav>
</div>
</body>
</html>