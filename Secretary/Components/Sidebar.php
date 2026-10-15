<?php
require_once __DIR__ . '/../../Components/layout.php';
$menuItems = roleNavigationItems('secretary');
[$userName, $userRoleLabel, $userInitials] = roleUser('secretary');
?>
<!-- Sidebar stylesheet linked directly (same appUrl() helper the logo uses) -->
<link rel="stylesheet" href="<?= e(appUrl('Components/css/Sidebar.css')) ?>">

<aside class="role-sidebar" id="role-sidebar" data-layout-role="secretary" aria-label="Secretary navigation">
    <a class="sidebar-brand" href="<?= e(rolePageUrl('secretary', 'Dashboard.php')) ?>">
        <span class="sidebar-brand-mark" aria-hidden="true">
            <img src="<?= e(appUrl('Components/img/Logo.png')) ?>" alt="MaintainTrack">
        </span>
        <span>MaintainTrack</span>
    </a>

    <nav class="sidebar-menu" aria-label="Main navigation">
        <?php foreach ($menuItems as $item): ?>
            <a class="menu-item" href="<?= e(roleNavigationUrl('secretary', $item['href'])) ?>" data-page="<?= e($item['href']) ?>">
                <span class="menu-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg>
                    <?php if (($item['indicator'] ?? '') === 'dot'): ?>
                        <span class="notification-dot"></span>
                    <?php endif; ?>
                </span>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-account">
        <button class="sidebar-user" type="button" id="sidebarUserTrigger" aria-expanded="false" aria-controls="sidebarUserMenu">
            <span class="profile-avatar"><?= e($userInitials) ?></span>
            <span class="profile-summary">
                <strong><?= e($userName) ?></strong>
                <small><?= e($userRoleLabel) ?></small>
            </span>
            <span class="sidebar-user-chevron" aria-hidden="true">▾</span>
        </button>
        <?php renderRoleProfileMenu('secretary', 'sidebar'); ?>
    </div>
</aside>

<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>