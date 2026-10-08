<?php
require_once __DIR__ . '/../../Components/layout.php';
$menuItems = roleNavigationItems('regular');
[$userName, $userRoleLabel, $userInitials] = roleUser('regular');
$regularLayout = $_COOKIE['regular_layout'] ?? 'sidebar';
if ($regularLayout === 'sidebar') {
    include __DIR__ . '/Sidebar.php';
    return;
}
?>

<link rel="stylesheet" href="<?= e(appAssetUrl('Regular/Components/css/Navbar.css')) ?>">

<header class="topnav" data-layout-role="regular">

    <div class="topnav-inner">

        <!-- BRAND -->
        <button
            class="brand"
            type="button"
            onclick="window.location.href='<?= e(rolePageUrl('regular', 'Dashboard.php')) ?>'"
        >

            <div class="brand-logo">
                <img src="<?= e(appUrl('Components/img/Logo.png')) ?>" alt="MaintainTrack Logo">
            </div>

            <div class="brand-name">
                MaintainTrack
            </div>

        </button>

        <nav class="topnav-menu">
            <?php foreach ($menuItems as $item): ?>
                <button class="menu-item" data-page="<?= e($item['href']) ?>" data-href="<?= e(roleNavigationUrl('regular', $item['href'])) ?>" aria-label="<?= e($item['label']) ?>" type="button">
                    <span class="menu-icon">
                        <svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg>
                        <?php if (($item['indicator'] ?? '') === 'dot'): ?><span class="notification-dot"></span><?php endif; ?>
                    </span>
                    <span><?= e($item['label']) ?></span>
                </button>
            <?php endforeach; ?>
        </nav>

        <!-- PROFILE AREA -->
        <div class="topnav-profile-wrapper">
            <?php renderRoleProfileMenu('regular'); ?>

            <button
                class="topnav-profile"
                type="button"
                id="profileTrigger"
                aria-expanded="false"
                aria-controls="profileMenu"
            >
                <div class="profile-avatar"><?= e($userInitials) ?></div>

                <div class="profile-info">
                    <strong><?= e($userName) ?></strong>
                    <span><?= e($userRoleLabel) ?></span>
                </div>

                <span class="profile-chevron">▾</span>
            </button>

        </div>

    </div>

</header>


<script src="<?= e(appAssetUrl('Components/js/RoleNavigation.js')) ?>"></script>