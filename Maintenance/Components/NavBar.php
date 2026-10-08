<?php
require_once __DIR__ . '/../../Components/layout.php';
$menuItems = roleNavigationItems('maintenance');
[$userName, $userRoleLabel, $userInitials] = roleUser('maintenance');
$maintenanceLayout = $_COOKIE['maintenance_layout'] ?? 'sidebar';
if ($maintenanceLayout === 'sidebar') {
    include __DIR__ . '/Sidebar.php';
    return;
}
?>

<link rel="stylesheet" href="<?= e(appAssetUrl('Maintenance/Components/css/NavBar.css')) ?>">

<header class="topnav" data-layout-role="maintenance">
    <div class="topnav-inner">

        <button class="brand" type="button" onclick="window.location.href='<?= e(rolePageUrl('maintenance', 'Dashboard.php')) ?>'">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L3 7v6c0 5 4 8.5 9 9 5-.5 9-4 9-9V7l-9-5z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="brand-text">MaintainTrack</span>
        </button>

        <nav class="topnav-menu">
            <?php foreach ($menuItems as $item): ?>
                <a href="<?= e(roleNavigationUrl('maintenance', $item['href'])) ?>" class="menu-item" data-page="<?= e($item['href']) ?>" aria-label="<?= e($item['label']) ?>">
                    <span class="menu-icon"><svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg></span>
                    <span><?= e($item['label']) ?></span>
                    <?php if (isset($item['indicator'])): ?><span class="menu-badge"><?= e($item['indicator']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="profile-wrap">
            <?php renderRoleProfileMenu('maintenance'); ?>
            <button class="profile-trigger" type="button" id="profileTrigger" aria-expanded="false" aria-controls="profileMenu">
                <span class="profile-avatar"><?= e($userInitials) ?></span>
                <span class="profile-summary">
                    <strong><?= e($userName) ?></strong>
                    <small><?= e($userRoleLabel) ?></small>
                </span>
                <span class="profile-chevron">▾</span>
            </button>
        </div>
    </div>
</header>

<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>