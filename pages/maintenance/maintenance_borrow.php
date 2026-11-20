<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../includes/bodega.php';

$pageRole = 'maintenance';
if (mtNormalizeRole($_SESSION['role'] ?? '') !== $pageRole) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}

$actor = bodegaActorId();
if ($actor === null) {
    http_response_code(403);
    exit('A valid signed-in user is required to access this page.');
}

if (empty($_SESSION['bodega_borrow_csrf'])) {
    $_SESSION['bodega_borrow_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['bodega_borrow_csrf'];

function maintenanceBorrowTransactions(string $actor, array $itemMap): array
{
    $transactions = [];
    $offset = 0;
    $pageSize = 1000;

    do {
        $result = bodegaRest(
            'GET',
            'inventory_transactions?select=*&borrower_user_id=eq.' . rawurlencode($actor)
            . '&order=created_at.desc&limit=' . $pageSize . '&offset=' . $offset
        );
        if (!$result['success'] || !is_array($result['data'])) {
            bodegaSetError('Unable to load your borrow history. ' . (string) $result['error']);
            return [];
        }

        $page = $result['data'];
        foreach ($page as $transaction) {
            if (isset($itemMap[(string) ($transaction['item_id'] ?? '')])
                && ($transaction['txn_type'] ?? '') === 'borrow') {
                $transactions[] = $transaction;
            }
        }
        $received = count($page);
        $offset += $received;
    } while ($received === $pageSize);

    return $transactions;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        bodegaFlash('error', 'Your session token expired. Refresh the page and try again.');
        bodegaRedirectHere();
    }

    $action = (string) ($_POST['action'] ?? '');
    $items = bodegaItems();
    $itemMap = bodegaItemMap($items);

    if ($action === 'request_borrow') {
        $itemId = (string) ($_POST['item_id'] ?? '');
        $qty = filter_var($_POST['qty'] ?? null, FILTER_VALIDATE_INT);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $item = $itemMap[$itemId] ?? null;
        $date = DateTime::createFromFormat('!Y-m-d', $dueDate);

        if (!$item || ($item['item_type'] ?? '') !== 'returnable') {
            bodegaFlash('error', 'Select a returnable item from the Storage Unit.');
            bodegaRedirectHere();
        }
        if ($qty === false || $qty < 1) {
            bodegaFlash('error', 'Enter a quantity of at least 1.');
            bodegaRedirectHere();
        }
        if ($qty > bodegaAvailableQty($item)) {
            bodegaFlash('error', 'There is not enough stock available for that quantity.');
            bodegaRedirectHere();
        }
        if (!$date || $date->format('Y-m-d') !== $dueDate || $dueDate < date('Y-m-d')) {
            bodegaFlash('error', 'Choose a valid due date that is today or later.');
            bodegaRedirectHere();
        }

        $error = bodegaInsert('inventory_transactions', [
            'item_id' => $itemId,
            'txn_type' => 'borrow',
            'qty' => $qty,
            'status' => 'pending',
            'borrower_type' => 'staff',
            'borrower_user_id' => $actor,
            'due_date' => $dueDate,
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ]);
        bodegaFlash($error === null ? 'success' : 'error', $error === null
            ? 'Borrow request submitted for approval.'
            : 'Could not submit the borrow request. ' . $error);
        bodegaRedirectHere();
    }

    if ($action === 'return_borrow') {
        $txnId = (string) ($_POST['txn_id'] ?? '');
        $transactions = preg_match('/^[0-9a-fA-F-]{36}$/', $txnId)
            ? bodegaList(
                'inventory_transactions?select=*&txn_id=eq.' . rawurlencode($txnId)
                . '&borrower_user_id=eq.' . rawurlencode($actor)
                . '&txn_type=eq.borrow&status=eq.released&limit=1'
            )
            : [];
        $transaction = $transactions[0] ?? null;
        $item = $transaction ? ($itemMap[(string) ($transaction['item_id'] ?? '')] ?? null) : null;

        if (!$transaction || !$item) {
            bodegaFlash('error', 'That active borrow was not found for your account.');
            bodegaRedirectHere();
        }

        $returned = bodegaRest('POST', 'rpc/return_inventory_transaction', [
            'p_txn_id' => $txnId,
            'p_returned_by' => $actor,
            'p_borrower_user_id' => $actor,
        ]);
        if (!$returned['success'] || !is_array($returned['data'])
            || ($returned['data']['returned'] ?? false) !== true) {
            bodegaFlash('error', 'Could not complete the return. '
                . (string) ($returned['error'] ?? 'Supabase did not confirm the return.'));
        } else {
            bodegaFlash('success', 'Item marked as returned.');
        }
        bodegaRedirectHere();
    }

    bodegaRedirectHere();
}

$items = bodegaItems();
$itemMap = bodegaItemMap($items);
$transactions = maintenanceBorrowTransactions($actor, $itemMap);
$pageError = bodegaSetError() ?? '';
$flash = bodegaTakeFlash();
$pending = array_values(array_filter($transactions, static fn(array $t): bool => ($t['status'] ?? '') === 'pending'));
$borrowed = array_values(array_filter($transactions, static fn(array $t): bool => ($t['status'] ?? '') === 'released'));
$returned = array_values(array_filter($transactions, static fn(array $t): bool => ($t['status'] ?? '') === 'returned'));
$declined = array_values(array_filter($transactions, static fn(array $t): bool => ($t['status'] ?? '') === 'rejected'));
$overdueCount = count(array_filter($borrowed, static fn(array $t): bool =>
    bodegaIsOverdue($t, $itemMap[(string) $t['item_id']])
));

renderHead('Borrow / Return', 'maintenance_Borrow.css', $pageRole);
?>
<div class="page">
    <div class="borrow-card">
        <?php renderPageHeader('Borrow / Return', 'Request Storage Unit items and track your loans', $pageRole); ?>

        <?php if ($pageError !== ''): ?>
            <div class="tr-alert error" role="alert"><?= e($pageError) ?></div>
        <?php endif; ?>
        <?php if ($flash): ?>
            <div class="tr-alert <?= e($flash['type']) ?>" role="status"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <section class="tr-kpis" aria-label="Borrow summary">
            <div class="tr-kpi"><span>Pending</span><strong><?= count($pending) ?></strong></div>
            <div class="tr-kpi"><span>Borrowed</span><strong><?= count($borrowed) ?></strong></div>
            <div class="tr-kpi"><span>Overdue</span><strong><?= $overdueCount ?></strong></div>
            <div class="tr-kpi"><span>Returned</span><strong><?= count($returned) ?></strong></div>
        </section>

        <section class="tr-panel">
            <h2 class="tr-section-title">Request an item</h2>
            <?php $availableItems = array_filter($items, static fn(array $item): bool =>
                ($item['item_type'] ?? '') === 'returnable' && bodegaAvailableQty($item) > 0
            ); ?>
            <?php if (!$availableItems): ?>
                <p class="tr-empty">No returnable bodega items are currently in stock.</p>
            <?php else: ?>
                <form method="post" class="tr-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="request_borrow">
                    <label class="tr-field tr-wide">Item
                        <select class="tr-select" name="item_id" required>
                            <option value="" disabled selected>Select an item</option>
                            <?php foreach ($availableItems as $item): ?>
                                <option value="<?= e($item['item_id']) ?>">
                                    <?= e($item['name'] ?? '') ?> · <?= bodegaAvailableQty($item) ?> in stock
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="tr-field">Quantity
                        <input class="tr-input" type="number" name="qty" min="1" value="1" required>
                    </label>
                    <label class="tr-field">Due date
                        <input class="tr-input" type="date" name="due_date" min="<?= e(date('Y-m-d')) ?>" required>
                    </label>
                    <label class="tr-field tr-wide">Notes (optional)
                        <input class="tr-input" type="text" name="notes" maxlength="200">
                    </label>
                    <div class="tr-form-actions"><button class="tr-btn" type="submit">Submit borrow request</button></div>
                </form>
                <p class="tr-hint">Your request stays pending until the treasurer approves it. Stock is deducted on approval.</p>
            <?php endif; ?>
        </section>

        <section class="tr-panel">
            <h2 class="tr-section-title">Pending requests <span class="tr-count"><?= count($pending) ?></span></h2>
            <?php if (!$pending): ?><p class="tr-empty">You have no pending requests.</p><?php else: ?>
                <div class="tr-table-wrap"><table class="tr-table">
                    <thead><tr><th>Requested</th><th>Item</th><th>Qty</th><th>Due</th><th>Status</th></tr></thead>
                    <tbody><?php foreach ($pending as $transaction): $item = $itemMap[(string) $transaction['item_id']]; ?>
                        <tr>
                            <td><?= e(bodegaDate($transaction['created_at'] ?? '')) ?></td>
                            <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                            <td><?= (int) $transaction['qty'] ?></td>
                            <td><?= e(bodegaDate($transaction['due_date'] ?? '')) ?></td>
                            <td><span class="tr-badge pending">Pending</span></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </section>

        <section class="tr-panel">
            <h2 class="tr-section-title">Borrowed items <span class="tr-count"><?= count($borrowed) ?></span></h2>
            <?php if (!$borrowed): ?><p class="tr-empty">You have no active borrows.</p><?php else: ?>
                <div class="tr-table-wrap"><table class="tr-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Borrowed</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody><?php foreach ($borrowed as $transaction):
                        $item = $itemMap[(string) $transaction['item_id']];
                        $overdue = bodegaIsOverdue($transaction, $item);
                    ?>
                        <tr>
                            <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                            <td><?= (int) $transaction['qty'] ?></td>
                            <td><?= e(bodegaDate($transaction['created_at'] ?? '')) ?></td>
                            <td><?= e(bodegaDate($transaction['due_date'] ?? '')) ?></td>
                            <td><span class="tr-badge <?= $overdue ? 'overdue' : 'released' ?>"><?= $overdue ? 'Overdue' : 'Borrowed' ?></span></td>
                            <td><form method="post" class="tr-inline" onsubmit="return confirm('Confirm that you are returning this item?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="return_borrow">
                                <input type="hidden" name="txn_id" value="<?= e($transaction['txn_id']) ?>">
                                <button class="tr-btn tr-btn-ghost" type="submit">Mark returned</button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </section>

        <section class="tr-panel">
            <h2 class="tr-section-title">Returned items <span class="tr-count"><?= count($returned) ?></span></h2>
            <?php if (!$returned): ?><p class="tr-empty">You have no returned borrows yet.</p><?php else: ?>
                <div class="tr-table-wrap"><table class="tr-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Due</th><th>Returned</th><th>Status</th></tr></thead>
                    <tbody><?php foreach ($returned as $transaction): $item = $itemMap[(string) $transaction['item_id']]; ?>
                        <tr>
                            <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                            <td><?= (int) $transaction['qty'] ?></td>
                            <td><?= e(bodegaDate($transaction['due_date'] ?? '')) ?></td>
                            <td><?= e(bodegaDate($transaction['returned_at'] ?? '')) ?></td>
                            <td><span class="tr-badge returned">Returned</span></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </section>

        <?php if ($declined): ?>
            <section class="tr-panel">
                <h2 class="tr-section-title">Declined requests <span class="tr-count"><?= count($declined) ?></span></h2>
                <div class="tr-table-wrap"><table class="tr-table">
                    <thead><tr><th>Requested</th><th>Item</th><th>Qty</th><th>Status</th></tr></thead>
                    <tbody><?php foreach ($declined as $transaction): $item = $itemMap[(string) $transaction['item_id']]; ?>
                        <tr>
                            <td><?= e(bodegaDate($transaction['created_at'] ?? '')) ?></td>
                            <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                            <td><?= (int) $transaction['qty'] ?></td>
                            <td><span class="tr-badge rejected">Declined</span></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php renderFoot(); ?>
