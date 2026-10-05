<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$notifications = [
    [
        'title'     => 'Maintenance Request Updated',
        'prefix'    => 'Your maintenance request MR-2026-001',
        'suffix'    => '– Leaking Faucet is now In Progress.',
        'time'      => 'Today, 9:00 AM',
        'iconClass' => 'icon-update',
        'iconSvg'   => '<path d="M20 12a8 8 0 1 1-2.3-5.7"></path><path d="M20 4v4h-4"></path>',
    ],
    [
        'title'     => 'Maintenance Request Assigned',
        'prefix'    => 'Your request has been assigned to',
        'suffix'    => 'Mang Juan, our maintenance staff.',
        'time'      => 'Today, 9:00 AM',
        'iconClass' => 'icon-assigned',
        'iconSvg'   => '<circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c0-3.6 3-6 7-6s7 2.4 7 6"></path>',
    ],
    [
        'title'     => 'Maintenance Request Submitted',
        'prefix'    => 'Your maintenance request MR-2026-001',
        'suffix'    => 'has been successfully submitted.',
        'time'      => 'Today, 9:00 AM',
        'iconClass' => 'icon-submitted',
        'iconSvg'   => '<path d="M20 6L9 17l-5-5"></path>',
    ],
];

renderHead('Notifications', 'Notification.css', 'regular');
?>
<div class="page">

    <?php renderPageHeader('Notifications', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <section class="notification-container">

        <div class="notification-toolbar">
            <div class="notification-filters">
                <button class="filter-button active" type="button">ALL</button>
                <button class="filter-button" type="button">Maintenance Updates</button>
            </div>
            <button class="read-button" type="button">Mark all as Read</button>
        </div>

        <div class="notification-list">
            <?php foreach ($notifications as $item): ?>
                <div class="notification-card">
                    <div class="notification-icon <?= e($item['iconClass']) ?>">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                            <?= $item['iconSvg'] ?>
                        </svg>
                    </div>

                    <div class="notification-content">
                        <h2><?= e($item['title']) ?></h2>
                        <p><?= e($item['prefix']) ?> <span><?= e($item['suffix']) ?></span></p>
                    </div>

                    <div class="notification-time">
                        <?= e($item['time']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

</div>
<?php renderFoot(); ?>
</html>