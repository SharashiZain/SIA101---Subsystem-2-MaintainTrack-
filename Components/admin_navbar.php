<?php
/* ------------------------------------------------------------
   ADMIN NAVBAR
   ------------------------------------------------------------ */
require_once __DIR__ . '/layout.php';
[$userName, $userHandle, $userInitials] = roleUser('admin');
$menuItems = roleNavigationItems('admin');
?>

<?php
$adminLayout = $_COOKIE['admin_layout'] ?? 'sidebar';
if ($adminLayout !== 'navbar') {
    include __DIR__ . '/admin_sidebar.php';
    return;
}
?>

<link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/Navbar.css')) ?>">

<header class="topnav" data-layout-role="admin">

    <div class="topnav-inner">

        <button
            class="brand"
            type="button"
            onclick="window.location.href='<?= e(rolePageUrl('admin', 'Dashboard.php')) ?>'"
        >
            <div class="brand-logo">
                <img src="<?= e(appUrl('assets/images/Logo.png')) ?>" alt="MaintainTrack Logo">
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
                        <?php if (($item['indicator'] ?? '') === 'dot'): ?><span class="notification-dot" data-notif-dot hidden></span><?php endif; ?>
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

<script src="<?= e(appAssetUrl('assets/js/RoleNavigation.js')) ?>"></script>