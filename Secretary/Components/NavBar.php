<?php
require_once __DIR__ . '/../../Components/layout.php';
$menuItems = roleNavigationItems('secretary');
[$userName, $userRoleLabel, $userInitials] = roleUser('secretary');
$secretaryLayout = $_COOKIE['secretary_layout'] ?? 'sidebar';
if ($secretaryLayout === 'sidebar') {
    include __DIR__ . '/Sidebar.php';
    return;
}
?>

<link rel="stylesheet" href="<?= e(appAssetUrl('Secretary/Components/css/navbar.css')) ?>">

<header class="topnav" data-layout-role="secretary">
    <div class="topnav-inner">
        <button class="brand" type="button" onclick="window.location.href='<?= e(rolePageUrl('secretary', 'Dashboard.php')) ?>'">
            <span class="brand-logo">
                <img src="<?= e(appUrl('Components/img/Logo.png')) ?>" alt="MaintainTrack">
            </span>
            <span class="brand-name">MaintainTrack</span>
        </button>

        <nav class="topnav-menu">
            <?php foreach ($menuItems as $item): ?>
                <a href="<?= e(roleNavigationUrl('secretary', $item['href'])) ?>"
                   class="menu-item"
                   data-page="<?= e($item['href']) ?>">
                    <span class="menu-icon">
                        <svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg>
                        <?php if (($item['indicator'] ?? '') === 'dot'): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="topnav-profile-wrapper">
            <?php renderRoleProfileMenu('secretary'); ?>
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

<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>