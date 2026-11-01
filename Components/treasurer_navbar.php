<?php
require_once __DIR__ . '/layout.php';
$menuItems = roleNavigationItems('treasurer');
[$userName, $userRoleLabel, $userInitials] = roleUser('treasurer');
$treasurerLayout = $_COOKIE['treasurer_layout'] ?? 'sidebar';
if ($treasurerLayout === 'sidebar') {
    include __DIR__ . '/treasurer_sidebar.php';
    return;
}
?>

<link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/Navbar.css')) ?>">

<header class="topnav" data-layout-role="treasurer">
    <div class="topnav-inner">

        <button class="brand" type="button" onclick="window.location.href='<?= e(rolePageUrl('treasurer', 'Dashboard.php')) ?>'">
            <span class="brand-logo">
                <img src="<?= e(appUrl('assets/images/Logo.png')) ?>" alt="MaintainTrack Logo">
            </span>
            <span class="brand-name">MaintainTrack</span>
        </button>

        <nav class="topnav-menu">
            <?php foreach ($menuItems as $item): ?>
                <a href="<?= e(roleNavigationUrl('treasurer', $item['href'])) ?>" class="menu-item" data-page="<?= e($item['href']) ?>" aria-label="<?= e($item['label']) ?>">
                    <span class="menu-icon">
                        <svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg>
                        <?php if (($item['indicator'] ?? '') === 'dot'): ?><span class="notification-dot" data-notif-dot hidden></span><?php endif; ?>
                    </span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="topnav-profile-wrapper">
            <?php renderRoleProfileMenu('treasurer'); ?>
            <button class="topnav-profile" type="button" id="profileTrigger" aria-expanded="false" aria-controls="profileMenu">
                <span class="profile-avatar"><?= e($userInitials) ?></span>
                <span class="profile-info">
                    <strong><?= e($userName) ?></strong>
                    <span><?= e($userRoleLabel) ?></span>
                </span>
                <span class="profile-chevron">▾</span>
            </button>
        </div>
    </div>
</header>

<script src="<?= e(appAssetUrl('assets/js/RoleNavigation.js')) ?>"></script>