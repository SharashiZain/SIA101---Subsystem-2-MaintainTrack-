<?php
require_once '../../Components/layout.php';

$notifications = [
    [
        'title'     => 'Maintenance Request Updated',
        'prefix'    => 'Your maintenance request MR-2026-001',
        'suffix'    => '– Leaking Faucet is now In Progress.',
        'time'      => 'Today, 9:00 AM',
        'iconClass' => 'icon-update',
        'iconSvg'   => '<path d="M20 12a8 8 0 1 1-2.3-5.7"></path><path d="M20 4v4h-4"></path>',
        'type'      => 'maintenance',
        'unread'    => true,
    ],
    [
        'title'     => 'Maintenance Request Assigned',
        'prefix'    => 'Your request has been assigned to',
        'suffix'    => 'Mang Juan, our maintenance staff.',
        'time'      => 'Today, 9:00 AM',
        'iconClass' => 'icon-assigned',
        'iconSvg'   => '<circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c0-3.6 3-6 7-6s7 2.4 7 6"></path>',
        'type'      => 'maintenance',
        'unread'    => true,
    ],
    [
        'title'     => 'Maintenance Request Submitted',
        'prefix'    => 'Your maintenance request MR-2026-001',
        'suffix'    => 'has been successfully submitted.',
        'time'      => 'Today, 9:00 AM',
        'iconClass' => 'icon-submitted',
        'iconSvg'   => '<path d="M20 6L9 17l-5-5"></path>',
        'type'      => 'maintenance',
        'unread'    => false,
    ],
];

renderHead('Notifications', 'Notification.css', 'regular');
?>
<div class="page">

    <?php renderPageHeader('Notifications', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <section class="notification-container">

        <div class="notification-toolbar">
            <div class="notification-filters">
                <button class="filter-button active" type="button" data-filter="all">ALL</button>
                <button class="filter-button" type="button" data-filter="maintenance">Maintenance Updates</button>
            </div>
            <button class="read-button" type="button" id="markAllRead">Mark all as Read</button>
        </div>

        <div class="notification-list" id="notificationList">
            <?php foreach ($notifications as $item): ?>
                <div class="notification-card <?= $item['unread'] ? 'unread' : '' ?>" 
                     data-type="<?= e($item['type']) ?>"
                     data-unread="<?= $item['unread'] ? 'true' : 'false' ?>">
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

        <div id="notifNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
            <p style="font-size: 15px; font-weight: 600;">No notifications found for this filter.</p>
        </div>

    </section>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filterBtns = document.querySelectorAll('.filter-button');
        const markAllRead = document.getElementById('markAllRead');
        const cards = document.querySelectorAll('.notification-card');
        const noResults = document.getElementById('notifNoResults');

        let currentFilter = 'all';

        function applyFilter() {
            let visible = 0;
            cards.forEach(function (card) {
                const type = card.dataset.type || '';
                const isUnread = card.dataset.unread === 'true';
                let show = true;

                if (currentFilter === 'maintenance') {
                    show = type === 'maintenance';
                }

                card.style.display = show ? 'flex' : 'none';
                if (show) visible++;
            });
            noResults.style.display = visible === 0 ? 'block' : 'none';
        }

        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilter();
            });
        });

        markAllRead.addEventListener('click', function () {
            cards.forEach(function (card) {
                card.classList.remove('unread');
                card.dataset.unread = 'false';
            });
            alert('All notifications marked as read.');
        });

        cards.forEach(function (card) {
            card.addEventListener('click', function () {
                if (this.dataset.unread === 'true') {
                    this.classList.remove('unread');
                    this.dataset.unread = 'false';
                }
            });
        });

        applyFilter();
    });
</script>
<?php renderFoot(); ?>
</html>