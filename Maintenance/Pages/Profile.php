<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="../Components/css/Profile.css">
</head>
<body>
    <div class="page">
        <?php include '../Components/NavBar.php'; ?>
  <header class="page-header">
    <h1 class="page-title">Profile</h1>
    <span class="page-subtitle">Maintenance Personnel Portal · Barangay Gulod</span>
  </header>

  <div class="profile-layout">
    <section class="profile-card">
      <div class="avatar-lg"></div>
      <h2>Jose Mar</h2>
      <span class="profile-role">Maintenance Personnel</span>
      <div class="profile-btns">
        <button class="btn-light">Edit Profile</button>
        <button class="btn-light">Change Password</button>
      </div>
    </section>

    <section class="panel">
      <h3 class="section-label">Account Details</h3>
      <div class="form-grid">
        <div class="field"><label>Full Name</label><input type="text" placeholder="Jose Mar"></div>
        <div class="field"><label>Username</label><input type="text" placeholder="josemar"></div>
        <div class="field"><label>Email Address</label><input type="email" placeholder="josemar@email.com"></div>
        <div class="field"><label>Contact Number</label><input type="text" placeholder="09XX XXX XXXX"></div>
      </div>
      <div class="form-actions">
        <button class="btn-primary">Save</button>
        <button class="btn-outline">Cancel</button>
      </div>
    </section>

    <section class="info-card">
      <h3 class="section-label">Account Information</h3>
      <div class="info-row"><span>User ID</span><strong>MT-2026-001</strong></div>
      <div class="info-row"><span>Role</span><strong>Maintenance Personnel</strong></div>
      <div class="info-row"><span>Status</span><strong>Active</strong></div>
    </section>

    <section class="info-card">
      <h3 class="section-label">Recent Activity</h3>
      <div class="activity-row">
        <div>
          <strong>Maintenance Request Updated</strong>
          <span>Updated request MR-2026-001</span>
        </div>
        <span class="activity-time">Today</span>
      </div>
      <div class="activity-row">
        <div>
          <strong>Maintenance Request Updated</strong>
          <span>Updated request MR-2026-001</span>
        </div>
        <span class="activity-time">Today</span>
      </div>
    </section>
  </div>
</div>
</body>
</html>