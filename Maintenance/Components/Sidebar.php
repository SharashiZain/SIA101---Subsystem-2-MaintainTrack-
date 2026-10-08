<?php
$menuItems = roleNavigationItems('maintenance');
[$userName, $userRoleLabel, $userInitials] = roleUser('maintenance');
?>
<link rel="stylesheet" href="<?= e(appAssetUrl('Components/css/Sidebar.css')) ?>">

<aside class="role-sidebar" id="role-sidebar" data-layout-role="maintenance" aria-label="Maintenance navigation">
    <a class="sidebar-brand" href="<?= e(rolePageUrl('maintenance', 'Dashboard.php')) ?>">
        <span class="sidebar-brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L3 7v6c0 5 4 8.5 9 9 5-.5 9-4 9-9V7l-9-5z"/><path d="M9 12l2 2 4-4"/></svg>
        </span>
        <span>MaintainTrack</span>
    </a>

    <nav class="sidebar-menu" aria-label="Main navigation">
        <?php foreach ($menuItems as $item): ?>
            <a href="<?= e(roleNavigationUrl('maintenance', $item['href'])) ?>" class="menu-item" data-page="<?= e($item['href']) ?>">
                <span class="menu-icon"><svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg></span>
                <span><?= e($item['label']) ?></span>
                <?php if (isset($item['indicator'])): ?><span class="menu-badge"><?= e($item['indicator']) ?></span><?php endif; ?>
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
        <?php renderRoleProfileMenu('maintenance', 'sidebar'); ?>
    </div>
</aside>

<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>
