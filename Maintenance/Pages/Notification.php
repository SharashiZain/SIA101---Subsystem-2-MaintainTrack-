<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$notifications = [
    [
        'title'   => 'Maintenance Request Updated',
        'time'    => 'Today, 9:00 AM',
        'message' => 'Your maintenance request MR-2026-001 – Leaking Faucet is now In Progress.',
        'unread'  => true,
    ],
    [
        'title'   => 'Maintenance Request Assigned',
        'time'    => 'Today, 9:00 AM',
        'message' => 'Your request has been assigned to Mong Juan, our maintenance staff.',
        'unread'  => true,
    ],
    [
        'title'   => 'Maintenance Request Submitted',
        'time'    => 'Today, 9:00 AM',
        'message' => 'Your maintenance request MR-2026-001 has been successfully submitted.',
        'unread'  => false,
    ],
];

renderHead('Notifications', 'Notification.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('Notifications', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <section class="notif-toolbar">
        <div class="notif-tabs">
            <button class="tab active" type="button">All</button>
            <button class="tab" type="button">Maintenance Updates</button>
        </div>
        <button class="mark-read-btn" type="button">Mark all as Read</button>
    </section>

    <section class="notif-list">
        <?php foreach ($notifications as $item): ?>
            <article class="notif-card <?= $item['unread'] ? 'unread' : '' ?>">
                <span class="notif-dot <?= $item['unread'] ? '' : 'read' ?>"></span>
                <div class="notif-body">
                    <div class="notif-top">
                        <h3><?= e($item['title']) ?></h3>
                        <span class="notif-time"><?= e($item['time']) ?></span>
                    </div>
                    <p><?= e($item['message']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

</div>
<?php renderFoot(); ?>
</html>