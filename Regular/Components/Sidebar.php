<?php
$menuItems = roleNavigationItems('regular');
[$userName, $userRoleLabel, $userInitials] = roleUser('regular');
?>
<link rel="stylesheet" href="<?= e(appAssetUrl('Components/css/Sidebar.css')) ?>">

<aside class="role-sidebar" id="role-sidebar" data-layout-role="regular" aria-label="Regular user navigation">
    <a class="sidebar-brand" href="<?= e(rolePageUrl('regular', 'Dashboard.php')) ?>">
        <span class="sidebar-brand-mark"><img src="<?= e(appUrl('Components/img/Logo.png')) ?>" alt=""></span>
        <span>MaintainTrack</span>
    </a>

    <nav class="sidebar-menu" aria-label="Main navigation">
        <?php foreach ($menuItems as $item): ?>
            <a href="<?= e(roleNavigationUrl('regular', $item['href'])) ?>" class="menu-item" data-page="<?= e($item['href']) ?>">
                <span class="menu-icon">
                    <svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg>
                    <?php if (($item['indicator'] ?? '') === 'dot'): ?><span class="notification-dot"></span><?php endif; ?>
                </span>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php renderSidebarRetractToggle(); ?>

    <div class="sidebar-account">
        <button class="sidebar-user" type="button" aria-expanded="false" aria-controls="sidebarUserMenu">
            <span class="profile-avatar"><?= e($userInitials) ?></span>
            <span class="profile-summary"><strong><?= e($userName) ?></strong><small><?= e($userRoleLabel) ?></small></span>
            <span class="sidebar-user-chevron" aria-hidden="true">▾</span>
        </button>
        <?php renderRoleProfileMenu('regular', 'sidebar'); ?>
    </div>
</aside>

<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>
