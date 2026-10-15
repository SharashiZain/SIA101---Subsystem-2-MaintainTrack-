<?php
/* ------------------------------------------------------------
   SHARED NOTIFICATION PAGE (Regular, Maintenance, Secretary)
   Same markup + look as Admin/Pages/Notification.php so every role's
   notification page is uniform. Pair it with the stylesheet
   'Components/css/NotificationShared.css' in renderHead().

   $items   : [ ['tone' => blue|green|purple|orange|red,
                 'title' => '', 'text' => '', 'time' => '',
                 'unread' => bool, 'category' => 'slug'], ... ]
   $filters : extra filter buttons after All / Unread:
              [ ['key' => 'slug', 'label' => 'Label'], ... ]
   ------------------------------------------------------------ */
if (!function_exists('renderNotificationPage')) {
    function renderNotificationPage(string $role, ?string $subtitle, array $items, array $filters = []): void
    {
        ?>
<div class="container">

    <div class="one-card">
        <?php renderPageHeader('Notifications', $subtitle, $role); ?>
        <div class="actions-top">
            <div class="notif-filters">
                <button class="filter-btn active" data-filter="all" type="button">All</button>
                <button class="filter-btn" data-filter="unread" type="button">Unread</button>
                <?php foreach ($filters as $filter): ?>
                    <button class="filter-btn" data-filter="<?= e($filter['key']) ?>" type="button"><?= e($filter['label']) ?></button>
                <?php endforeach; ?>
            </div>

            <button class="btn-mark-read" id="markAllReadBtn" type="button">Mark all as read</button>
        </div>

        <main class="notifications-card" id="notificationsCard">
            <?php foreach ($items as $index => $n): ?>
                <div class="notification-item<?= !empty($n['unread']) ? ' unread' : '' ?>"
                     data-category="<?= e($n['category'] ?? '') ?>"
                     data-unread="<?= !empty($n['unread']) ? 'true' : 'false' ?>"
                     data-index="<?= (int) $index ?>">
                    <div class="icon-indicator bg-<?= e($n['tone'] ?? 'blue') ?>">!</div>
                    <div class="notif-content">
                        <h4><?= e($n['title']) ?></h4>
                        <p><?= e($n['text']) ?></p>
                    </div>
                    <div class="notif-time"><?= e($n['time']) ?></div>
                </div>
            <?php endforeach; ?>

            <div id="notifNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
                <p style="font-size: 15px; font-weight: 600;">No notifications found for this filter.</p>
            </div>
        </main>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filterBtns = document.querySelectorAll('.filter-btn');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const notificationItems = document.querySelectorAll('.notification-item');
        const noResults = document.getElementById('notifNoResults');

        let currentFilter = 'all';

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

                item.style.display = visible ? 'flex' : 'none';
                if (visible) visibleCount++;
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function markRead(item) {
            item.classList.remove('unread');
            item.classList.add('read');
            item.setAttribute('data-unread', 'false');
        }

        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterBtns.forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilter();
            });
        });

        markAllReadBtn.addEventListener('click', function () {
            notificationItems.forEach(markRead);
            applyFilter();
        });

        notificationItems.forEach(function (item) {
            item.addEventListener('click', function () {
                if (this.getAttribute('data-unread') === 'true') {
                    markRead(this);
                    applyFilter();
                }
            });
        });
    });
</script>
<?php
    }
}
