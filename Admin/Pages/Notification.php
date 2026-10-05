<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$notifications = [
    ['color' => 'blue',   'text' => 'MT-00132 was assigned to Maria Santos.', 'time' => '8:49 AM', 'unread' => true],
    ['color' => 'green',  'text' => 'MT-00134 was marked completed.',         'time' => '8:02 AM', 'unread' => true],
    ['color' => 'purple', 'text' => 'MT-00131 was updated to In Progress.',   'time' => '7:47 AM', 'unread' => false],
    ['color' => 'orange', 'text' => 'New request MT-00135 received.',         'time' => '2:35 PM', 'unread' => false],
];

renderHead('Notifications', 'Notification.css');
?>
    <div class="container">

        <?php renderPageHeader('Notifications'); ?>

        <div class="actions-top">
            <button class="btn-mark-read">Mark all read</button>
        </div>

        <main class="notifications-card">
            <?php foreach ($notifications as $n): ?>
                <div class="notification-item<?= $n['unread'] ? ' unread' : '' ?>">
                    <div class="icon-indicator bg-<?= e($n['color']) ?>">!</div>
                    <div class="notif-content">
                        <h4><?= e($n['text']) ?></h4>
                        <p>Maintenance Reporting Portal</p>
                    </div>
                    <div class="notif-time"><?= e($n['time']) ?></div>
                </div>
            <?php endforeach; ?>
        </main>

    </div>
<?php renderFoot(); ?>
