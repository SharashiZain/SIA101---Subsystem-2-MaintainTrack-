<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/bodega.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$actor = bodegaActorId();

/* ---- Receipt view: short-lived link to the stored receipt ---- */
if (isset($_GET['receipt'])) {
    $procId = (string) $_GET['receipt'];
    $rows   = preg_match('/^[0-9a-fA-F-]{36}$/', $procId)
        ? bodegaList('procurements?select=receipt_path,item_id&procurement_id=eq.' . rawurlencode($procId) . '&limit=1')
        : [];
    $path = (string) ($rows[0]['receipt_path'] ?? '');
    $url  = $path !== '' ? bodegaSignedUrl($path) : '';
    if ($url === '') {
        bodegaFlash('error', 'The receipt could not be opened.');
        bodegaRedirectHere();
    }
    bodegaRedirectUrl($url);
}

/* ---- Actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'record_purchase') {
        $items   = bodegaItems();
        $itemMap = bodegaItemMap($items);
        $itemId  = (string) ($_POST['item_id'] ?? '');
        $date    = trim((string) ($_POST['purchase_date'] ?? ''));
        $qty     = (int) ($_POST['qty'] ?? 0);
        $price   = (float) ($_POST['unit_price'] ?? -1);
        $supplier = trim((string) ($_POST['supplier'] ?? ''));
        $item    = $itemMap[$itemId] ?? null;

        if (!$item || $date === '' || $qty < 1 || $price < 0) {
            bodegaFlash('error', 'Pick an item, a purchase date, a quantity of at least 1 and a valid unit price.');
            bodegaRedirectHere();
        }

        $receipt = bodegaCheckFile($_FILES['receipt'] ?? ['error' => UPLOAD_ERR_NO_FILE], false);
        if (!$receipt['ok']) {
            bodegaFlash('error', $receipt['error']);
            bodegaRedirectHere();
        }
        [$receiptPath, $uploadErr] = bodegaUpload('receipts', $receipt);
        if ($uploadErr !== null) {
            bodegaFlash('error', $uploadErr);
            bodegaRedirectHere();
        }

        $total = round($qty * $price, 2);
        $err = bodegaAdjustStock((string) $item['item_id'], $qty);
        if ($err === null) {
            $err = bodegaInsert('procurements', [
                'item_id'       => $item['item_id'],
                'purchase_date' => $date,
                'qty'           => $qty,
                'unit_price'    => $price,
                'total'         => $total,
                'supplier'      => $supplier ?: null,
                'receipt_path'  => $receiptPath,
                'recorded_by'   => $actor,
            ]);
            if ($err !== null) {
                bodegaAdjustStock((string) $item['item_id'], -$qty); // undo the stock change
            }
        }
        if ($err === null) {
            // Logged in History as stock_in, so the purchase is not encoded twice.
            $logErr = bodegaInsert('inventory_transactions', [
                'item_id'     => $item['item_id'],
                'txn_type'    => 'stock_in',
                'qty'         => $qty,
                'status'      => 'released',
                'released_by' => $actor,
                'notes'       => 'Purchase on ' . $date . ($supplier !== '' ? ' from ' . $supplier : ''),
            ]);
            if ($logErr !== null) {
                bodegaFlash('error', 'Purchase saved, but the stock-in entry was not logged. ' . $logErr);
                bodegaRedirectHere();
            }
        }
        bodegaFlash($err === null ? 'success' : 'error', $err === null
            ? 'Purchase recorded: ' . $qty . ' × ' . $item['name'] . ' for ' . bodegaPeso($total) . '.'
            : 'Purchase not recorded. ' . $err);
        bodegaRedirectHere();
    }

    bodegaRedirectHere();
}

/* ---- Data ---- */
$items     = bodegaItems();
$itemMap   = bodegaItemMap($items);
$pageError = bodegaSetError() ?? '';
$flash     = bodegaTakeFlash();
$filterItem = (string) ($_GET['item_id'] ?? '');

$procurements = bodegaProcurements($itemMap);
$monthStart   = date('Y-m-01');
$monthSpend   = 0.0;
foreach ($procurements as $p) {
    if ((string) ($p['purchase_date'] ?? '') >= $monthStart) {
        $monthSpend += (float) $p['total'];
    }
}
$shown = $filterItem !== ''
    ? array_values(array_filter($procurements, fn($p) => (string) $p['item_id'] === $filterItem))
    : $procurements;

/* Price history for the selected item */
$priceStats = null;
if ($filterItem !== '' && $shown) {
    $prices = array_map(fn($p) => (float) $p['unit_price'], $shown);
    $priceStats = [
        'min' => min($prices),
        'max' => max($prices),
        'avg' => array_sum($prices) / count($prices),
        'last' => (float) $shown[0]['unit_price'],
    ];
}

renderHead('Procurement', 'Procurement.css', 'treasurer');
?>
<div class="page">
    <div class="one-card">

    <?php renderPageHeader('Procurement', 'Purchases add stock automatically', 'treasurer'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="tr-alert error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="tr-alert <?= e($flash['type']) ?>" role="status"><?= e($flash['text']) ?></div>
    <?php endif; ?>

    <div class="tr-kpis">
        <div class="tr-kpi">
            <span>Spent this month</span>
            <strong><?= e(bodegaPeso($monthSpend)) ?></strong>
        </div>
        <div class="tr-kpi">
            <span>Purchases recorded</span>
            <strong><?= count($procurements) ?></strong>
        </div>
    </div>

    <details class="tr-panel tr-add">
        <summary>+ Record a purchase</summary>
        <form method="post" enctype="multipart/form-data" class="tr-form">
            <input type="hidden" name="action" value="record_purchase">
            <label class="tr-field tr-wide">Item
                <select class="tr-select" name="item_id" required>
                    <option value="" disabled hidden selected>Select an item</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= e($item['item_id']) ?>"><?= e($item['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="tr-field">Purchase date
                <input class="tr-input" type="date" name="purchase_date" value="<?= e(date('Y-m-d')) ?>" required>
            </label>
            <label class="tr-field">Quantity
                <input class="tr-input" type="number" name="qty" min="1" value="1" required>
            </label>
            <label class="tr-field">Price per unit (₱)
                <input class="tr-input" type="number" name="unit_price" min="0" step="0.01" required>
            </label>
            <label class="tr-field">Supplier
                <input class="tr-input" type="text" name="supplier" maxlength="120">
            </label>
            <label class="tr-field tr-wide">Receipt (JPG, PNG or PDF, max 5 MB)
                <input class="tr-input" type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
            </label>
            <div class="tr-form-actions">
                <button class="tr-btn" type="submit">Record purchase</button>
            </div>
        </form>
    </details>

    <section class="tr-toolbar">
        <form method="get" class="tr-inline">
            <select class="tr-select" name="item_id" onchange="this.form.submit()">
                <option value="" disabled hidden <?= $filterItem === '' ? 'selected' : '' ?>>All items</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= e($item['item_id']) ?>" <?= $filterItem === (string) $item['item_id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <?php if ($priceStats): ?>
        <div class="tr-kpis">
            <div class="tr-kpi"><span>Last price</span><strong><?= e(bodegaPeso($priceStats['last'])) ?></strong></div>
            <div class="tr-kpi"><span>Lowest</span><strong><?= e(bodegaPeso($priceStats['min'])) ?></strong></div>
            <div class="tr-kpi"><span>Highest</span><strong><?= e(bodegaPeso($priceStats['max'])) ?></strong></div>
            <div class="tr-kpi"><span>Average</span><strong><?= e(bodegaPeso($priceStats['avg'])) ?></strong></div>
        </div>
    <?php endif; ?>

    <section class="tr-panel">
        <h2 class="tr-section-title"><?= $filterItem !== '' ? 'Price history' : 'Procurement log' ?></h2>
        <?php if (!$shown): ?>
            <p class="tr-empty">No purchases recorded yet.</p>
        <?php else: ?>
        <div class="tr-table-wrap">
            <table class="tr-table">
                <thead><tr><th>Date</th><th>Item</th><th>Qty</th><th>Unit price</th><th>Total</th><th>Supplier</th><th>Receipt</th></tr></thead>
                <tbody>
                <?php foreach ($shown as $p): $item = $itemMap[(string) $p['item_id']] ?? []; ?>
                    <tr>
                        <td><?= e(bodegaDate($p['purchase_date'] ?? '')) ?></td>
                        <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                        <td><?= (int) $p['qty'] ?></td>
                        <td><?= e(bodegaPeso($p['unit_price'] ?? 0)) ?></td>
                        <td><?= e(bodegaPeso($p['total'] ?? 0)) ?></td>
                        <td><?= e($p['supplier'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($p['receipt_path'])): ?>
                                <a class="tr-link" href="?receipt=<?= e($p['procurement_id']) ?>" target="_blank" rel="noopener">View</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
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