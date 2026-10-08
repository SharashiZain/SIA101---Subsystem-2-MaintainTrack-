<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$notifications = [
    [
        'title'   => 'Maintenance Request Updated',
        'time'    => 'Today, 9:00 AM',
        'message' => 'Your maintenance request MR-2026-001 – Leaking Faucet is now In Progress.',
        'unread'  => true,
        'type'    => 'maintenance',
    ],
    [
        'title'   => 'Maintenance Request Assigned',
        'time'    => 'Today, 9:00 AM',
        'message' => 'Your request has been assigned to Jose Mar, our maintenance staff.',
        'unread'  => true,
        'type'    => 'maintenance',
    ],
    [
        'title'   => 'Maintenance Request Submitted',
        'time'    => 'Today, 9:00 AM',
        'message' => 'Your maintenance request MR-2026-001 has been successfully submitted.',
        'unread'  => false,
        'type'    => 'maintenance',
    ],
];

renderHead('Notifications', 'Notification.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('Notifications', 'Maintenance Personnel Portal • Barangay Gulod', 'maintenance'); ?>

    <section class="notif-toolbar">
        <div class="notif-tabs">
            <button class="tab active" type="button" data-filter="all">All</button>
            <button class="tab" type="button" data-filter="maintenance">Maintenance Updates</button>
        </div>
        <button class="mark-read-btn" type="button" id="markAllRead">Mark all as Read</button>
    </section>

    <section class="notif-list" id="notifList">
        <?php foreach ($notifications as $item): ?>
            <article class="notif-card <?= $item['unread'] ? 'unread' : '' ?>" 
                     data-type="<?= e($item['type']) ?>"
                     data-unread="<?= $item['unread'] ? 'true' : 'false' ?>">
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

    <div id="notifNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
        <p style="font-size: 15px; font-weight: 600;">No notifications found for this filter.</p>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('.tab');
        const cards = document.querySelectorAll('.notif-card');
        const markAllBtn = document.getElementById('markAllRead');
        const noResults = document.getElementById('notifNoResults');

        let activeFilter = 'all';

        function applyFilter() {
            let visible = 0;
            cards.forEach(function (card) {
                const type = card.dataset.type;
                const show = activeFilter === 'all' || type === activeFilter;
                card.style.display = show ? 'flex' : 'none';
                if (show) visible++;
            });
            noResults.style.display = visible === 0 ? 'block' : 'none';
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                this.classList.add('active');
                activeFilter = this.dataset.filter;
                applyFilter();
            });
        });

        markAllBtn.addEventListener('click', function () {
            cards.forEach(function (card) {
                card.classList.remove('unread');
                card.dataset.unread = 'false';
                const dot = card.querySelector('.notif-dot');
                if (dot) dot.classList.add('read');
            });
            alert('All notifications marked as read.');
        });

        cards.forEach(function (card) {
            card.addEventListener('click', function () {
                if (this.dataset.unread === 'true') {
                    this.classList.remove('unread');
                    this.dataset.unread = 'false';
                    const dot = this.querySelector('.notif-dot');
                    if (dot) dot.classList.add('read');
                }
            });
        });

        applyFilter();
    });
</script>
<?php renderFoot(); ?>
</html>