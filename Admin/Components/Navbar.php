<?php
/* ------------------------------------------------------------
   ADMIN NAVBAR
   ------------------------------------------------------------ */
require_once __DIR__ . '/../../Components/layout.php';
[$userName, $userHandle, $userInitials] = roleUser('admin');
$menuItems = roleNavigationItems('admin');
?>

<?php
$adminLayout = $_COOKIE['admin_layout'] ?? 'sidebar';
if ($adminLayout !== 'navbar') {
    include __DIR__ . '/Sidebar.php';
    return;
}
?>

<link rel="stylesheet" href="<?= e(appAssetUrl('Admin/Components/css/Navbar.css')) ?>">

<header class="topnav" data-layout-role="admin">

    <div class="topnav-inner">

        <button
            class="brand"
            type="button"
            onclick="window.location.href='<?= e(rolePageUrl('admin', 'Dashboard.php')) ?>'"
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
                    data-href="<?= e(roleNavigationUrl('admin', $item['href'])) ?>"
                    aria-label="<?= e($item['label']) ?>"
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
            <?php renderRoleProfileMenu('admin'); ?>

            <button class="topnav-profile" type="button" id="profileTrigger" aria-expanded="false" aria-controls="profileMenu">
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

<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>