<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../includes/supabase_client.php';
require_once __DIR__ . '/../../includes/bodega.php';
require_once __DIR__ . '/../../Components/notification_view.php';
require_once __DIR__ . '/../../includes/role_notifications.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$items     = bodegaItems();
$itemMap   = bodegaItemMap($items);
$txns      = bodegaTransactions($itemMap, 200);
$pageError = bodegaSetError() ?? '';

/* Built from current data: nothing is stored, so nothing needs marking as read. */
$alerts = [];
foreach ($txns as $t) {
    $item = $itemMap[(string) $t['item_id']] ?? [];
    if (($t['status'] ?? '') === 'pending') {
        $alerts[] = ['tone' => 'pending', 'title' => 'Request to approve', 'text' => (int) $t['qty'] . ' × ' . ($item['name'] ?? 'item') . ' requested by a maintenance worker.', 'href' => rolePageUrl('treasurer', 'Borrow.php'), 'related_request_id' => (string) ($t['related_request_id'] ?? ''), 'when' => (string) ($t['created_at'] ?? '')];
    }
}
foreach ($items as $item) {
    if (bodegaIsLow($item)) {
        $alerts[] = ['tone' => 'low', 'title' => bodegaAvailableQty($item) === 0 ? 'Out of Stock' : 'Low Stock', 'text' => $item['name'] . ' has ' . bodegaAvailableQty($item) . ' available; reorder level is ' . (int) $item['reorder_level'] . '.', 'href' => rolePageUrl('treasurer', 'Inventory.php'), 'related_request_id' => '', 'when' => ''];
    }
}
usort($alerts, fn($a, $b) => strcmp($b['when'], $a['when']));

$dynamicItems = [];
foreach ($alerts as $alert) {
    if ($alert['tone'] === 'pending') continue;
    $dynamicItems[] = [
        'id' => notificationItemId($alert['tone'], $alert['title'], $alert['text'], $alert['when']),
        'tone' => $alert['tone'] === 'low' ? 'orange' : 'blue',
        'title' => $alert['title'],
        'text' => $alert['text'],
        'time' => $alert['when'] !== '' ? bodegaDate($alert['when']) : 'Current Storage Unit alert',
        'unread' => true,
        'category' => $alert['tone'] === 'low' ? 'inventory' : ($alert['tone'] === 'pending' ? 'request' : 'borrow'),
        'related_request_id' => (string) $alert['related_request_id'],
    ];
}
renderRoleNotificationPage('treasurer', 'Requests, overdue items and low stock', [
    ['key' => 'request', 'label' => 'Requests'],
    ['key' => 'borrow', 'label' => 'Borrow Updates'],
    ['key' => 'inventory', 'label' => 'Inventory'],
], $dynamicItems, $pageError);
exit;

/* Each alert gets a stable id. Opening this page counts as seeing the alerts (script at the
   bottom), so the red dot comes back only when a NEW alert appears. */
$alertItems = [];
foreach ($alerts as $a) {
    $alertItems[] = ['id' => notificationItemId($a['tone'], $a['title'], $a['text'], $a['when']), 'unread' => true];
}

/* Background check used by the red dot in the menu: just the ids, no page */
if (isset($_GET['feed'])) {
    notificationFeedRespond($alertItems);
}

renderHead('Notifications', 'Notification.css', 'treasurer');
?>
<div class="page">
    <div class="one-card">

    <?php renderPageHeader('Notifications', 'Requests, overdue items and low stock', 'treasurer'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="tr-alert error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <?php if (!$alerts): ?>
        <p class="tr-empty">You're all caught up.</p>
    <?php else: ?>
    <ul class="tr-alerts">
        <?php foreach ($alerts as $a): ?>
            <li class="tr-alert-card <?= e($a['tone']) ?> notification-detail-trigger"
                tabindex="0" role="group"
                data-title="<?= e($a['title']) ?>"
                data-message="<?= e($a['text']) ?>"
                data-time="<?= e($a['when'] !== '' ? $a['when'] : 'Current inventory alert') ?>"
                data-id="<?= e(notificationItemId($a['tone'], $a['title'], $a['text'], $a['when'])) ?>"
                data-request-url="<?= !empty($a['related_request_id']) ? e(rolePageUrl('treasurer', 'Borrow.php') . '?request_id=' . rawurlencode((string) $a['related_request_id'])) : '' ?>">
                <div>
                    <strong><?= e($a['title']) ?></strong>
                    <p><?= e($a['text']) ?></p>
                </div>
                <a class="tr-btn tr-btn-ghost" href="<?= e($a['href']) ?>" data-alert-navigation>Open</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    </div>
</div>
<dialog class="notification-details-modal" id="notificationDetailsModal" aria-labelledby="notificationDetailsTitle">
    <div class="notification-details-content">
        <button class="notification-details-close" type="button" aria-label="Close notification details">&times;</button>
        <h2 id="notificationDetailsTitle"></h2>
        <p class="notification-details-message" id="notificationDetailsMessage"></p>
        <p class="notification-details-time" id="notificationDetailsTime"></p>
        <a class="notification-details-link" id="notificationDetailsLink" href="" hidden>Go to request</a>
        <a class="notification-details-link" id="notificationOpenLink" href="">Open</a>
    </div>
</dialog>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const alerts = document.querySelectorAll('.notification-detail-trigger');
        const detailsModal = document.getElementById('notificationDetailsModal');
        const detailsTitle = document.getElementById('notificationDetailsTitle');
        const detailsMessage = document.getElementById('notificationDetailsMessage');
        const detailsTime = document.getElementById('notificationDetailsTime');
        const detailsLink = document.getElementById('notificationDetailsLink');
        const openLink = document.getElementById('notificationOpenLink');

        function showDetails(alert) {
            detailsTitle.textContent = alert.dataset.title || '';
            detailsMessage.textContent = alert.dataset.message || '';
            detailsTime.textContent = alert.dataset.time || '';
            openLink.href = alert.querySelector('[data-alert-navigation]').href;
            if (alert.dataset.requestUrl) {
                detailsLink.href = alert.dataset.requestUrl;
                detailsLink.hidden = false;
            } else {
                detailsLink.removeAttribute('href');
                detailsLink.hidden = true;
            }
            if (window.MTNotifSound && alert.dataset.id) MTNotifSound.markRead([alert.dataset.id]);
            detailsModal.showModal();
        }

        alerts.forEach(function (alert) {
            alert.addEventListener('click', function (event) {
                if (event.target.closest('[data-alert-navigation]')) return;
                showDetails(alert);
            });
            alert.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    showDetails(alert);
                }
            });
        });

        detailsModal.querySelector('.notification-details-close').addEventListener('click', function () {
            detailsModal.close();
        });
        detailsModal.addEventListener('click', function (event) {
            if (event.target === detailsModal) detailsModal.close();
        });

        /* Opening the page also clears the current alerts, matching the existing behavior. */
        if (window.MTNotifSound) {
            MTNotifSound.markRead(<?= json_encode(array_column($alertItems, 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        }
    });
</script>
<?php renderFoot(); ?>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>