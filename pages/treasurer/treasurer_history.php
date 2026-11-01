<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/bodega.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$items     = bodegaItems();
$itemMap   = bodegaItemMap($items);
$pageError = bodegaSetError() ?? '';

$filterType = (string) ($_GET['type'] ?? '');
$filterFrom = (string) ($_GET['from'] ?? '');
$filterTo   = (string) ($_GET['to'] ?? '');
$validTypes = ['use', 'borrow', 'return', 'stock_in', 'damaged', 'disposed', 'adjustment'];

$txns  = bodegaTransactions($itemMap, 1000);
$procs = bodegaProcurements($itemMap, 1000);

$txns = array_values(array_filter($txns, function ($t) use ($filterType, $filterFrom, $filterTo) {
    if ($filterType !== '' && ($t['txn_type'] ?? '') !== $filterType) return false;
    $day = substr((string) ($t['created_at'] ?? ''), 0, 10);
    if ($filterFrom !== '' && $day < $filterFrom) return false;
    if ($filterTo !== '' && $day > $filterTo) return false;
    return true;
}));
$procs = array_values(array_filter($procs, function ($p) use ($filterFrom, $filterTo) {
    $day = (string) ($p['purchase_date'] ?? '');
    if ($filterFrom !== '' && $day < $filterFrom) return false;
    if ($filterTo !== '' && $day > $filterTo) return false;
    return true;
}));

$userIds = [];
foreach ($txns as $t) {
    if (!empty($t['borrower_user_id'])) $userIds[] = $t['borrower_user_id'];
}
$names = bodegaUserNames($userIds);

renderHead('History', 'History.css', 'treasurer');
?>
<div class="page">
    <div class="one-card">

    <?php renderPageHeader('History', 'Stock movements and procurement logs', 'treasurer'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="tr-alert error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <form method="get" class="tr-toolbar tr-inline tr-wrap">
        <select class="tr-select" name="type">
            <option value="" disabled hidden <?= $filterType === '' ? 'selected' : '' ?>>All movements</option>
            <?php foreach ($validTypes as $type): ?>
                <option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $type))) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="tr-inline-label">From <input class="tr-input" type="date" name="from" value="<?= e($filterFrom) ?>"></label>
        <label class="tr-inline-label">To <input class="tr-input" type="date" name="to" value="<?= e($filterTo) ?>"></label>
        <button class="tr-btn" type="submit">Apply</button>
        <a class="tr-btn tr-btn-ghost" href="<?= e(rolePageUrl('treasurer', 'History.php')) ?>">Reset</a>
    </form>

    <section class="tr-panel">
        <h2 class="tr-section-title">Stock movements <span class="tr-count"><?= count($txns) ?></span></h2>
        <?php if (!$txns): ?>
            <p class="tr-empty">No movements match these filters.</p>
        <?php else: ?>
        <div class="tr-table-wrap">
            <table class="tr-table">
                <thead><tr><th>When</th><th>Item</th><th>Movement</th><th>Qty</th><th>Who</th><th>Status</th><th>Note</th></tr></thead>
                <tbody>
                <?php foreach ($txns as $t):
                    $item = $itemMap[(string) $t['item_id']] ?? [];
                    $who = ($t['borrower_type'] ?? '') === 'outsider'
                        ? ($t['outsider_name'] ?? 'Outsider') . ' (outsider)'
                        : ($names[(string) ($t['borrower_user_id'] ?? '')] ?? '—');
                ?>
                    <tr>
                        <td><?= e(bodegaDate($t['created_at'] ?? '')) ?></td>
                        <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', (string) ($t['txn_type'] ?? '')))) ?></td>
                        <td><?= (int) $t['qty'] ?></td>
                        <td><?= e($who) ?></td>
                        <td><span class="tr-badge <?= e($t['status'] ?? '') ?>"><?= e(ucfirst((string) ($t['status'] ?? ''))) ?></span></td>
                        <td class="tr-muted"><?= e($t['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="tr-panel">
        <h2 class="tr-section-title">Procurement log <span class="tr-count"><?= count($procs) ?></span></h2>
        <?php if (!$procs): ?>
            <p class="tr-empty">No purchases match these filters.</p>
        <?php else: ?>
        <div class="tr-table-wrap">
            <table class="tr-table">
                <thead><tr><th>Purchase date</th><th>Item</th><th>Qty</th><th>Unit price</th><th>Total</th><th>Supplier</th></tr></thead>
                <tbody>
                <?php foreach ($procs as $p): $item = $itemMap[(string) $p['item_id']] ?? []; ?>
                    <tr>
                        <td><?= e(bodegaDate($p['purchase_date'] ?? '')) ?></td>
                        <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                        <td><?= (int) $p['qty'] ?></td>
                        <td><?= e(bodegaPeso($p['unit_price'] ?? 0)) ?></td>
                        <td><?= e(bodegaPeso($p['total'] ?? 0)) ?></td>
                        <td><?= e($p['supplier'] ?? '—') ?></td>
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