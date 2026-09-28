<link rel="stylesheet" href="../Components/css/NavBar.css">

<header class="topnav">
    <div class="topnav-inner">

        <button class="brand" type="button" onclick="window.location.href='Dashboard.php'">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L3 7v6c0 5 4 8.5 9 9 5-.5 9-4 9-9V7l-9-5z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="brand-text">MaintainTrack</span>
        </button>

        <nav class="topnav-menu">
            <a href="Dashboard.php" class="menu-item" data-page="Dashboard.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M3 11l9-7 9 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span>Dashboard</span>
            </a>

            <a href="Facility.php" class="menu-item" data-page="Facility.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="4" width="14" height="17" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 3h6v3H9z" stroke="currentColor" stroke-width="1.6"/><path d="M8 11h8M8 15h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </span>
                <span>Facility</span>
            </a>

            <a href="Task.php" class="menu-item" data-page="Task.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span>Task</span>
            </a>

            <a href="Request.php" class="menu-item" data-page="Request.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M6 4v16M6 4l4 2-4 2M18 20V4M18 20l-4-2 4-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span>Request</span>
            </a>

            <a href="Notification.php" class="menu-item" data-page="Notification.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M6 8a6 6 0 0112 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 20a2 2 0 004 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </span>
                <span>Notification</span>
                <span class="menu-badge">3</span>
            </a>
        </nav>

        <div class="profile-wrap">
            <button class="profile-trigger" type="button" id="profileTrigger">
                <span class="profile-avatar">JM</span>
                <span class="profile-summary">
                    <strong>Jose Mar</strong>
                    <small>Maintenance</small>
                </span>
                <span class="profile-chevron">▾</span>
            </button>

            <div class="profile-menu" id="profileMenu">
                <a href="Profile.php">Edit Profile</a>
                <a href="../../index.php" class="logout-link">Log out</a>
            </div>
        </div>
    </div>
</header>

<script src="../Components/js/NavBar.js"></script>