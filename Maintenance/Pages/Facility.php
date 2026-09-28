<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities & Equipment</title>
    <link rel="stylesheet" href="../Components/css/Facility.css">
</head>
<body>
    <div class="page">
        <?php include '../Components/NavBar.php'; ?>
  <header class="page-header">
    <h1 class="page-title">Facilities &amp; Equipment</h1>
    <span class="page-subtitle">Maintenance Personnel Portal · Barangay Gulod</span>
  </header>

  <section class="filter-bar">
    <div class="search-input">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      <input type="text" placeholder="Search equipment...">
    </div>
    <button class="reset-btn">Reset</button>
    <button class="filter-btn">All Facilities
      <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <button class="filter-btn">All Category
      <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
  </section>

  <section class="equip-grid">
    <article class="equip-card">
      <h3>Aircon Unit 02</h3>
      <span class="equip-loc">A2T-001 · Barangay Hall · 2nd Floor</span>
      <div class="equip-tags">
        <span class="meta-chip">Equipment</span>
        <span class="meta-chip">2 yrs old</span>
        <span class="meta-chip">8 Records</span>
      </div>
      <div class="equip-status">
        <span>Current Task:</span> MT-C0134 · Broken Aircon
      </div>
      <button class="btn-primary">View Task</button>
    </article>

    <article class="equip-card">
      <h3>Aircon Unit 03</h3>
      <span class="equip-loc">A2T-001 · Barangay Hall · 2nd Floor</span>
      <div class="equip-tags">
        <span class="meta-chip">Equipment</span>
        <span class="meta-chip">6 yrs old</span>
        <span class="meta-chip">8 Records</span>
      </div>
      <div class="equip-status">
        <span>Last Serviced:</span> Sept 8, 2026
      </div>
      <button class="btn-primary">View History</button>
    </article>
  </section>
</div>
</body>
</html>