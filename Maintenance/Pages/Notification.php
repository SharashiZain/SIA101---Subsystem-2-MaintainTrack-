<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification</title>
    <link rel="stylesheet" href="../Components/css/Notification.css">
</head>
<body>  
    <div class="page">
        <?php include '../Components/NavBar.php'; ?>
  <header class="page-header">
    <h1 class="page-title">Notification</h1>
    <span class="page-subtitle">Maintenance Personnel Portal · Barangay Gulod</span>
  </header>

  <section class="notif-toolbar">
    <div class="notif-tabs">
      <button class="tab active">All</button>
      <button class="tab">Maintenance Updates</button>
    </div>
    <button class="mark-read-btn">Mark all as Read</button>
  </section>

  <section class="notif-list">
    <article class="notif-card unread">
      <span class="notif-dot"></span>
      <div class="notif-body">
        <div class="notif-top">
          <h3>Maintenance Request Updated</h3>
          <span class="notif-time">Today, 9:00 AM</span>
        </div>
        <p>Your maintenance request MR-2026-001 – Leaking Faucet is now In Progress.</p>
      </div>
    </article>

    <article class="notif-card unread">
      <span class="notif-dot"></span>
      <div class="notif-body">
        <div class="notif-top">
          <h3>Maintenance Request Assigned</h3>
          <span class="notif-time">Today, 9:00 AM</span>
        </div>
        <p>Your request has been assigned to Mong Juan, our maintenance staff.</p>
      </div>
    </article>

    <article class="notif-card">
      <span class="notif-dot read"></span>
      <div class="notif-body">
        <div class="notif-top">
          <h3>Maintenance Request Submitted</h3>
          <span class="notif-time">Today, 9:00 AM</span>
        </div>
        <p>Your maintenance request MR-2026-001 has been successfully submitted.</p>
      </div>
    </article>
  </section>
</div>
</body>
</html>