<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/bodega.php';
require_once __DIR__ . '/../../Components/notification_view.php';
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
        $alerts[] = ['tone' => 'pending', 'title' => 'Request to approve', 'text' => (int) $t['qty'] . ' × ' . ($item['name'] ?? 'item') . ' requested by a maintenance worker.', 'href' => rolePageUrl('treasurer', 'Borrow.php'), 'when' => (string) ($t['created_at'] ?? '')];
    }
    if (bodegaIsOverdue($t, $item)) {
        $alerts[] = ['tone' => 'overdue', 'title' => 'Overdue borrow', 'text' => (int) $t['qty'] . ' × ' . ($item['name'] ?? 'item') . ' was due ' . bodegaDate($t['due_date'] ?? '') . '.', 'href' => rolePageUrl('treasurer', 'Borrow.php'), 'when' => (string) ($t['due_date'] ?? '')];
    }
}
foreach ($items as $item) {
    if (bodegaIsLow($item)) {
        $alerts[] = ['tone' => 'low', 'title' => 'Low stock', 'text' => $item['name'] . ': ' . (int) $item['current_qty'] . ' left, reorder at ' . (int) $item['reorder_level'] . '.', 'href' => rolePageUrl('treasurer', 'Inventory.php'), 'when' => ''];
    }
}
usort($alerts, fn($a, $b) => strcmp($b['when'], $a['when']));

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
            <li class="tr-alert-card <?= e($a['tone']) ?>">
                <div>
                    <strong><?= e($a['title']) ?></strong>
                    <p><?= e($a['text']) ?></p>
                </div>
                <a class="tr-btn tr-btn-ghost" href="<?= e($a['href']) ?>">Open</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    </div>
</div>
<script>
    /* Opening the page = the current alerts have been seen, so the red dot clears. */
    document.addEventListener('DOMContentLoaded', function () {
        if (window.MTNotifSound) {
            MTNotifSound.markRead(<?= json_encode(array_column($alertItems, 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        }
    });
</script>
<?php renderFoot(); ?>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>