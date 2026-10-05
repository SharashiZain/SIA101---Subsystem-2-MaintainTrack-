<?php
/* ------------------------------------------------------------
   ADMIN SIDEBAR
   ------------------------------------------------------------ */
?>
<link rel="stylesheet" href="<?= e(appAssetUrl('Components/css/Sidebar.css')) ?>">

<aside class="role-sidebar" id="role-sidebar" data-layout-role="admin" aria-label="Admin navigation">
    <a class="sidebar-brand" href="<?= e(rolePageUrl('admin', 'Dashboard.php')) ?>">
        <span class="sidebar-brand-mark" aria-hidden="true">M</span>
        <span>MaintainTrack</span>
    </a>

    <nav class="sidebar-menu" aria-label="Main navigation">
        <?php foreach ($menuItems as $item): ?>
            <a class="menu-item" href="<?= e(roleNavigationUrl('admin', $item['href'])) ?>" data-page="<?= e($item['href']) ?>">
                <span class="menu-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg>
                </span>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php renderSidebarRetractToggle(); ?>

    <div class="sidebar-account">
        <button class="sidebar-user" type="button" id="sidebarUserTrigger" aria-expanded="false" aria-controls="sidebarUserMenu">
            <span class="profile-avatar"><?= htmlspecialchars($userInitials) ?></span>
            <span class="profile-summary">
                <strong><?= htmlspecialchars($userName) ?></strong>
                <small><?= htmlspecialchars($userHandle) ?></small>
            </span>
            <span class="sidebar-user-chevron" aria-hidden="true">▾</span>
        </button>
        <?php renderRoleProfileMenu('admin', 'sidebar'); ?>
    </div>
</aside>

<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>
