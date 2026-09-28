<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification</title>
    <link rel="stylesheet" href="../Components/css/Notification.css">
</head>

<body>
<?php include '../Components/NavBar.php'; ?>
    <main class="notification-page">

        <header class="top-header">
            <div>
                <h1>Notification</h1>
                <p>Maintenance Reporting Portal • Barangay Gulod</p>
            </div>
        </header>


        <section class="notification-container">

            <div class="notification-toolbar">

                <div class="notification-filters">
                    <button class="filter-button active">
                        ALL
                    </button>

                    <button class="filter-button">
                        Maintenance Updates
                    </button>
                </div>

                <button class="read-button">
                    Mark all as Read
                </button>

            </div>


            <div class="notification-list">

                <div class="notification-card">

                    <div class="notification-icon icon-update">
                        <svg viewBox="0 0 24 24" width="22" height="22">
                            <path d="M20 12a8 8 0 1 1-2.3-5.7"></path>
                            <path d="M20 4v4h-4"></path>
                        </svg>
                    </div>

                    <div class="notification-content">

                        <h2>
                            Maintenance Request Updated
                        </h2>

                        <p>
                            Your maintenance request MR-2026-001
                        </p>

                        <span>
                            &nbsp;– Leaking Faucet is now In Progress.
                        </span>

                    </div>

                    <div class="notification-time">
                        Today, 9:00 AM
                    </div>

                </div>


                <div class="notification-card">

                    <div class="notification-icon icon-assigned">
                        <svg viewBox="0 0 24 24" width="22" height="22">
                            <circle cx="12" cy="8" r="3.5"></circle>
                            <path d="M5 20c0-3.6 3-6 7-6s7 2.4 7 6"></path>
                        </svg>
                    </div>

                    <div class="notification-content">

                        <h2>
                            Maintenance Request Assigned
                        </h2>

                        <p>
                            Your request has been assigned to
                        </p>

                        <span>
                            Mang Juan, our maintenance staff.
                        </span>

                    </div>

                    <div class="notification-time">
                        Today, 9:00 AM
                    </div>

                </div>


                <div class="notification-card">

                    <div class="notification-icon icon-submitted">
                        <svg viewBox="0 0 24 24" width="22" height="22">
                            <path d="M8 4h8a2 2 0 0 1 2 2v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V6a2 2 0 0 1 2-2z"></path>
                            <path d="M9 13l2 2 4-4"></path>
                        </svg>
                    </div>

                    <div class="notification-content">

                        <h2>
                            Maintenance Request Submitted
                        </h2>

                        <p>
                            Your maintenance request MR-2026-001
                        </p>

                        <span>
                            &nbsp; has been successfully submitted.
                        </span>

                    </div>

                    <div class="notification-time">
                        Today, 9:00 AM
                    </div>

                </div>

            </div>

        </section>

    </main>

</body>
</html>