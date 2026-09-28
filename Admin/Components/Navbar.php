<?php
/* ------------------------------------------------------------
   ADMIN NAVBAR
   ------------------------------------------------------------ */
$userName    = 'Admin User';
$userHandle  = 'Administrator';
$userInitials = 'AU';

$menuItems = [
    [
        'label' => 'Dashboard',
        'href'  => 'Dashboard.php',
        'icon'  => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9 21v-7h6v7"></path>',
    ],
    [
        'label' => 'Requests',
        'href'  => 'Request.php',
        'icon'  => '<path d="M4 5h16v14H4z"></path><path d="M8 12h8"></path><path d="M12 8v8"></path>',
    ],
    [
        'label' => 'Assignments',
        'href'  => 'Assignment.php',
        'icon'  => '<circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"></path><path d="M16 11l2 2 4-4"></path>',
    ],
    [
        'label' => 'Equipment',
        'href'  => 'Registry.php',
        'icon'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>',
    ],
    [
        'label' => 'Reports',
        'href'  => 'Report.php',
        'icon'  => '<path d="M4 5h16v14H4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path>',
    ],
    [
        'label' => 'Notifications',
        'href'  => 'Notification.php',
        'icon'  => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>',
    ],
    [
        'label' => 'Users',
        'href'  => 'Users.php',
        'icon'  => '<circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"></path><circle cx="17.5" cy="9" r="2.5"></circle><path d="M17 14.5c2.6 0 4.5 1.8 4.5 4.5"></path>',
    ],
];
?>

<link rel="stylesheet" href="../Components/css/Navbar.css">

<header class="topnav">

    <div class="topnav-inner">

        <button
            class="brand"
            type="button"
            onclick="window.location.href='Dashboard.php'"
        >
            <div class="brand-logo">
                <svg viewBox="0 0 24 24">
                    <path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"></path>
                    <path d="M9 14l2.2 2.2L15.5 12"></path>
                </svg>
            </div>

            <div class="brand-name">MaintainTrack</div>
        </button>

        <nav class="topnav-menu">
            <?php foreach ($menuItems as $item): ?>
                <button
                    class="menu-item"
                    data-page="<?= htmlspecialchars($item['href']) ?>"
                    type="button"
                >
                    <span class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <?= $item['icon'] ?>
                        </svg>
                    </span>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="topnav-profile-wrapper">

            <div class="profile-menu" id="profileMenu">
                <a href="Profile.php">Edit Profile</a>
                <a href="../../index.php" class="profile-logout">Log out</a>
            </div>

            <button class="topnav-profile" type="button" id="profileTrigger">
                <div class="profile-avatar">
                    <?= htmlspecialchars($userInitials) ?>
                </div>

                <div class="profile-info">
                    <strong><?= htmlspecialchars($userName) ?></strong>
                    <span><?= htmlspecialchars($userHandle) ?></span>
                </div>

                <span class="profile-chevron">▾</span>
            </button>
        </div>

    </div>

</header>

<script src="../Components/js/Navbar.js"></script>