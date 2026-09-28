<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="../Components/css/Notification.css">
</head>
<body>
    <?php include '../Components/NavBar.php'; ?>
    <div class="container">
        <header>
            <div class="title-container">
                <h1>Notifications</h1>
                <p>Maintenance Reporting Portal</p>
            </div>
            <div class="user-info">
                <span>Admin User</span>
            </div>
        </header>

        <div class="actions-top">
            <button class="btn-mark-read">Mark all read</button>
        </div>

        <main class="notifications-card">
            <div class="notification-item unread">
                <div class="icon-indicator bg-blue">!</div>
                <div class="notif-content">
                    <h4>MT-00132 was assigned to Maria Santos.</h4>
                    <p>Maintenance Reporting Portal</p>
                </div>
                <div class="notif-time">8:49 AM</div>
            </div>

            <div class="notification-item unread">
                <div class="icon-indicator bg-green">!</div>
                <div class="notif-content">
                    <h4>MT-00134 was marked completed.</h4>
                    <p>Maintenance Reporting Portal</p>
                </div>
                <div class="notif-time">8:02 AM</div>
            </div>

            <div class="notification-item">
                <div class="icon-indicator bg-purple">!</div>
                <div class="notif-content">
                    <h4>MT-00131 was updated to In Progress.</h4>
                    <p>Maintenance Reporting Portal</p>
                </div>
                <div class="notif-time">7:47 AM</div>
            </div>

            <div class="notification-item">
                <div class="icon-indicator bg-orange">!</div>
                <div class="notif-content">
                    <h4>New request MT-00135 received.</h4>
                    <p>Maintenance Reporting Portal</p>
                </div>
                <div class="notif-time">2:35 PM</div>
            </div>
        </main>
    </div>
</body>
</html>