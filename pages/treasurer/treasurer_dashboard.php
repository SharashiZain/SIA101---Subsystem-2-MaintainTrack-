<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/bodega.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$items    = bodegaItems();
$itemMap  = bodegaItemMap($items);
$txns     = bodegaTransactions($itemMap, 200);
$procs    = bodegaProcurements($itemMap, 300);
$pageError = bodegaSetError() ?? '';

$lowStock = array_values(array_filter($items, 'bodegaIsLow'));

$overdue = [];
$pendingCount = 0;
$userIds = [];
foreach ($txns as $t) {
    $item = $itemMap[(string) $t['item_id']] ?? [];
    if (($t['status'] ?? '') === 'pending') $pendingCount++;
    if (bodegaIsOverdue($t, $item)) $overdue[] = $t;
    if (!empty($t['borrower_user_id'])) $userIds[] = $t['borrower_user_id'];
}
$names = bodegaUserNames($userIds);

$monthStart = date('Y-m-01');
$monthSpend = 0.0;
foreach ($procs as $p) {
    if ((string) ($p['purchase_date'] ?? '') >= $monthStart) $monthSpend += (float) $p['total'];
}

renderHead('Treasurer Dashboard', 'Dashboard.css', 'treasurer');
?>
<div class="page">
    <div class="one-card">

    <?php renderPageHeader('Treasurer Dashboard', 'Bodega stock, borrows and spending', 'treasurer'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="tr-alert error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <div class="tr-kpis">
        <a class="tr-kpi" href="<?= e(rolePageUrl('treasurer', 'Inventory.php')) ?>"><span>Low stock</span><strong><?= count($lowStock) ?></strong></a>
        <a class="tr-kpi" href="<?= e(rolePageUrl('treasurer', 'Borrow.php')) ?>"><span>Overdue borrows</span><strong><?= count($overdue) ?></strong></a>
        <a class="tr-kpi" href="<?= e(rolePageUrl('treasurer', 'Borrow.php')) ?>"><span>Pending requests</span><strong><?= $pendingCount ?></strong></a>
        <a class="tr-kpi" href="<?= e(rolePageUrl('treasurer', 'Procurement.php')) ?>"><span>Spent this month</span><strong><?= e(bodegaPeso($monthSpend)) ?></strong></a>
    </div>

    <div class="tr-grid-2">
        <section class="tr-panel">
            <h2 class="tr-section-title">Low stock</h2>
            <?php if (!$lowStock): ?>
                <p class="tr-empty">Everything is above its reorder level.</p>
            <?php else: ?>
                <ul class="tr-list">
                <?php foreach (array_slice($lowStock, 0, 8) as $item): ?>
                    <li>
                        <span class="tr-strong"><?= e($item['name']) ?></span>
                        <span class="tr-muted"><?= (int) $item['current_qty'] ?> left · reorder at <?= (int) $item['reorder_level'] ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="tr-panel">
            <h2 class="tr-section-title">Overdue borrows</h2>
            <?php if (!$overdue): ?>
                <p class="tr-empty">No overdue items.</p>
            <?php else: ?>
                <ul class="tr-list">
                <?php foreach (array_slice($overdue, 0, 8) as $t):
                    $item = $itemMap[(string) $t['item_id']] ?? [];
                    $who = ($t['borrower_type'] ?? '') === 'outsider'
                        ? ($t['outsider_name'] ?? 'Outsider')
                        : ($names[(string) ($t['borrower_user_id'] ?? '')] ?? 'Staff');
                ?>
                    <li>
                        <span class="tr-strong"><?= e($item['name'] ?? '') ?></span>
                        <span class="tr-muted"><?= e($who) ?> · due <?= e(bodegaDate($t['due_date'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="tr-panel">
        <h2 class="tr-section-title">Recent movements</h2>
        <?php if (!$txns): ?>
            <p class="tr-empty">No movements yet.</p>
        <?php else: ?>
        <div class="tr-table-wrap">
            <table class="tr-table">
                <thead><tr><th>When</th><th>Item</th><th>Movement</th><th>Qty</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($txns, 0, 8) as $t): $item = $itemMap[(string) $t['item_id']] ?? []; ?>
                    <tr>
                        <td><?= e(bodegaDate($t['created_at'] ?? '')) ?></td>
                        <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                        <td><?= e(ucfirst((string) ($t['txn_type'] ?? ''))) ?></td>
                        <td><?= (int) $t['qty'] ?></td>
                        <td><span class="tr-badge <?= e($t['status'] ?? '') ?>"><?= e(ucfirst((string) ($t['status'] ?? ''))) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    </div>
</div>
<?php renderFoot(); ?>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>
