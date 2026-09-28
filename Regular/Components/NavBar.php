<link rel="stylesheet" href="../Components/css/Navbar.css">

<header class="topnav">

    <div class="topnav-inner">

        <!-- BRAND -->
        <button
            class="brand"
            type="button"
            onclick="window.location.href='reg_user_dashboard.php'"
        >

            <div class="brand-logo">
                <img src="../../Components/img/logo.png" alt="MaintainTrack Logo">
            </div>

            <div class="brand-name">
                MaintainTrack
            </div>

        </button>

        <!-- MENU -->
        <nav class="topnav-menu">

            <!-- Dashboard -->
            <button class="menu-item" data-page="reg_user_dashboard.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 10.5 12 3l9 7.5"></path>
                        <path d="M5 9.5V21h14V9.5"></path>
                        <path d="M9 21v-7h6v7"></path>
                    </svg>
                </span>
                <span>Dashboard</span>
            </button>

            <!-- Reports -->
            <button class="menu-item" data-page="Report.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 5h16v14H4z"></path>
                        <path d="M8 9h8"></path>
                        <path d="M8 13h5"></path>
                    </svg>
                </span>
                <span>Reports</span>
            </button>

            <!-- History -->
            <button class="menu-item" data-page="History.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 12a9 9 0 1 0 9-9"></path>
                        <path d="M3 4v8h8"></path>
                    </svg>
                </span>
                <span>History</span>
            </button>

            <!-- Request -->
            <button class="menu-item" data-page="Request.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 5h16v14H4z"></path>
                        <path d="M8 12h8"></path>
                        <path d="M12 8v8"></path>
                    </svg>
                </span>
                <span>Request</span>
            </button>

            <!-- Notification -->
            <button class="menu-item" data-page="Notification.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                        <path d="M10 21h4"></path>
                    </svg>
                    <span class="notification-dot"></span>
                </span>
                <span>Notification</span>
            </button>

            <!-- Maintenance -->
            <button class="menu-item" data-page="Maintenance.php">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 3v18"></path>
                        <path d="M5 8h14"></path>
                        <path d="M5 16h14"></path>
                        <path d="M7 3h10"></path>
                    </svg>
                </span>
                <span>Maintenance</span>
            </button>

        </nav>

        <!-- PROFILE AREA -->
        <div class="topnav-profile-wrapper">

            <div class="profile-menu" id="profileMenu">

                <a href="Profile.php">
                    Edit Profile
                </a>

                <a href="../../index.php" class="profile-logout">
                    Log out
                </a>

            </div>

            <button
                class="topnav-profile"
                type="button"
                id="profileTrigger"
            >
                <div class="profile-avatar">
                    JD
                </div>

                <div class="profile-info">
                    <strong>Juan Dela Cruz</strong>
                    <span>Regular User</span>
                </div>

                <span class="profile-chevron">▾</span>
            </button>

        </div>

    </div>

</header>


<script src="../Components/js/Navbar.js"></script>