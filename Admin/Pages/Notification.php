<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$notifications = [
    [
        'color'     => 'blue',
        'text'      => 'MT-00132 was assigned to Maria Santos.',
        'time'      => '8:49 AM',
        'unread'    => true,
        'category'  => 'assignment',
    ],
    [
        'color'     => 'green',
        'text'      => 'MT-00134 was marked completed.',
        'time'      => '8:02 AM',
        'unread'    => true,
        'category'  => 'status',
    ],
    [
        'color'     => 'purple',
        'text'      => 'MT-00131 was updated to In Progress.',
        'time'      => '7:47 AM',
        'unread'    => false,
        'category'  => 'status',
    ],
    [
        'color'     => 'orange',
        'text'      => 'New request MT-00135 received.',
        'time'      => '2:35 PM',
        'unread'    => false,
        'category'  => 'request',
    ],
];

renderHead('Notifications', 'Notification.css');
?>
<div class="container">

    <?php renderPageHeader('Notifications'); ?>

    <div class="actions-top">
        <div class="notif-filters">
            <button class="filter-btn active" data-filter="all" type="button">All</button>
            <button class="filter-btn" data-filter="unread" type="button">Unread</button>
            <button class="filter-btn" data-filter="assignment" type="button">Assignments</button>
            <button class="filter-btn" data-filter="status" type="button">Status Updates</button>
            <button class="filter-btn" data-filter="request" type="button">New Requests</button>
        </div>

        <button class="btn-mark-read" id="markAllReadBtn" type="button">Mark all as read</button>
    </div>

    <main class="notifications-card" id="notificationsCard">
        <?php foreach ($notifications as $index => $n): ?>
            <div class="notification-item<?= $n['unread'] ? ' unread' : '' ?>" 
                 data-category="<?= e($n['category']) ?>" 
                 data-unread="<?= $n['unread'] ? 'true' : 'false' ?>"
                 data-index="<?= $index ?>">
                <div class="icon-indicator bg-<?= e($n['color']) ?>">!</div>
                <div class="notif-content">
                    <h4><?= e($n['text']) ?></h4>
                    <p>Maintenance Reporting Portal</p>
                </div>
                <div class="notif-time"><?= e($n['time']) ?></div>
            </div>
        <?php endforeach; ?>

        <!-- NO RESULTS MESSAGE -->
        <div id="notifNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
            <p style="font-size: 15px; font-weight: 600;">No notifications found for this filter.</p>
        </div>
    </main>

    <!-- NOTIFICATION COUNT -->
    <div class="notif-count" id="notifCount">
        <?= count($notifications) ?> notification<?= count($notifications) !== 1 ? 's' : '' ?> shown
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const filterBtns = document.querySelectorAll('.filter-btn');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const notificationItems = document.querySelectorAll('.notification-item');
        const noResults = document.getElementById('notifNoResults');
        const notifCount = document.getElementById('notifCount');

        let currentFilter = 'all';

        /* ==========================================
           FILTER FUNCTION
           ========================================== */
        function applyFilter() {
            let visibleCount = 0;

            notificationItems.forEach(function (item) {
                const category = item.dataset.category || '';
                const isUnread = item.dataset.unread === 'true';

                let visible = true;

                if (currentFilter === 'unread') {
                    visible = isUnread;
                } else if (currentFilter !== 'all') {
                    visible = category === currentFilter;
                }

                if (visible) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            notifCount.textContent = visibleCount + ' notification' + (visibleCount !== 1 ? 's' : '') + ' shown';
        }

        /* ==========================================
           FILTER BUTTONS
           ========================================== */
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterBtns.forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilter();
            });
        });

        /* ==========================================
           MARK ALL AS READ
           ========================================== */
        markAllReadBtn.addEventListener('click', function () {
            notificationItems.forEach(function (item) {
                item.classList.remove('unread');
                item.dataset.unread = 'false';
            });
            applyFilter();
            alert('All notifications marked as read.');
        });

        /* ==========================================
           CLICK INDIVIDUAL NOTIFICATION
           ========================================== */
        notificationItems.forEach(function (item) {
            item.style.cursor = 'pointer';
            item.addEventListener('click', function () {
                if (this.dataset.unread === 'true') {
                    this.classList.remove('unread');
                    this.dataset.unread = 'false';
                    applyFilter();
                }
            });
        });

        /* ==========================================
           INITIAL STATE
           ========================================== */
        applyFilter();
    });
</script>
<?php renderFoot(); ?>