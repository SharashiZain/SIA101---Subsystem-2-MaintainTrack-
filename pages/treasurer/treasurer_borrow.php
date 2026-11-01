<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/bodega.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$actor = bodegaActorId();

/* ---- Maintenance workers for the release form (read straight from users) ---- */
function trReleaseWorkers(): array {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
        return [];
    }
    $url = rtrim(SUPABASE_URL, '/')
        . '/rest/v1/users?select=user_id,full_name&role=eq.maintenance&status=eq.Active&order=full_name.asc';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = $raw ? json_decode($raw, true) : null;
    return ($code >= 200 && $code < 300 && is_array($data)) ? $data : [];
}

/* ---- Letter view: short-lived link to the scanned letter ---- */
if (isset($_GET['letter'])) {
    $txnId = (string) $_GET['letter'];
    $rows  = preg_match('/^[0-9a-fA-F-]{36}$/', $txnId)
        ? bodegaList('inventory_transactions?select=letter_path,item_id&txn_id=eq.' . rawurlencode($txnId) . '&limit=1')
        : [];
    $path = (string) ($rows[0]['letter_path'] ?? '');
    $url  = $path !== '' ? bodegaSignedUrl($path) : '';
    if ($url === '') {
        bodegaFlash('error', 'The letter could not be opened.');
        bodegaRedirectHere();
    }
    bodegaRedirectUrl($url);
}

/* ---- Actions (POST, then redirect back) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $items   = bodegaItems();
    $itemMap = bodegaItemMap($items);

    if (!$itemMap) {
        bodegaFlash('error', bodegaSetError() ?? 'No bodega items found.');
        bodegaRedirectHere();
    }

    /* Approve / reject / return work on an existing transaction. */
    if (in_array($action, ['approve', 'reject', 'return'], true)) {
        $txnId = (string) ($_POST['txn_id'] ?? '');
        $rows  = preg_match('/^[0-9a-fA-F-]{36}$/', $txnId)
            ? bodegaList('inventory_transactions?select=*&txn_id=eq.' . rawurlencode($txnId) . '&limit=1')
            : [];
        $txn  = $rows[0] ?? null;
        $item = $txn ? ($itemMap[(string) $txn['item_id']] ?? null) : null;
        if (!$txn || !$item) {
            bodegaFlash('error', 'That record was not found in the bodega.');
            bodegaRedirectHere();
        }
        $qty = (int) $txn['qty'];
        $filter = 'txn_id=eq.' . rawurlencode($txnId);

        if ($action === 'reject') {
            if (($txn['status'] ?? '') !== 'pending') {
                bodegaFlash('error', 'Only pending requests can be rejected.');
            } else {
                $err = bodegaUpdate('inventory_transactions', $filter, ['status' => 'rejected']);
                bodegaFlash($err === null ? 'success' : 'error', $err === null ? 'Request rejected.' : 'Could not reject. ' . $err);
            }
            bodegaRedirectHere();
        }

        if ($action === 'approve') {
            if (($txn['status'] ?? '') !== 'pending') {
                bodegaFlash('error', 'Only pending requests can be approved.');
                bodegaRedirectHere();
            }
            $err = bodegaAdjustStock((string) $item['item_id'], -$qty);
            if ($err === null) {
                $fields = ['status' => 'released', 'released_by' => $actor];
                if (($txn['txn_type'] ?? '') === 'borrow' && empty($txn['due_date'])) {
                    $fields['due_date'] = date('Y-m-d', strtotime('+7 days'));
                }
                $err = bodegaUpdate('inventory_transactions', $filter, $fields);
                if ($err !== null) {
                    bodegaAdjustStock((string) $item['item_id'], $qty); // undo the stock change
                }
            }
            bodegaFlash($err === null ? 'success' : 'error', $err === null ? 'Request approved and released.' : 'Could not approve. ' . $err);
            bodegaRedirectHere();
        }

        /* return */
        if (!(($txn['txn_type'] ?? '') === 'borrow' && ($txn['status'] ?? '') === 'released')) {
            bodegaFlash('error', 'Only released borrows can be returned.');
            bodegaRedirectHere();
        }
        $err = bodegaAdjustStock((string) $item['item_id'], $qty);
        if ($err === null) {
            $err = bodegaUpdate('inventory_transactions', $filter, ['status' => 'returned', 'returned_at' => date('c')]);
            if ($err !== null) {
                bodegaAdjustStock((string) $item['item_id'], -$qty);
            } else {
                bodegaInsert('inventory_transactions', [
                    'item_id'          => $item['item_id'],
                    'txn_type'         => 'return',
                    'qty'              => $qty,
                    'status'           => 'returned',
                    'borrower_type'    => $txn['borrower_type'] ?? 'staff',
                    'borrower_user_id' => $txn['borrower_user_id'] ?? null,
                    'outsider_name'    => $txn['outsider_name'] ?? null,
                    'outsider_contact' => $txn['outsider_contact'] ?? null,
                    'released_by'      => $actor,
                    'notes'            => 'Returned (borrow ' . $txnId . ')',
                ]);
            }
        }
        bodegaFlash($err === null ? 'success' : 'error', $err === null ? 'Item marked as returned.' : 'Could not return. ' . $err);
        bodegaRedirectHere();
    }

    /* New release from the treasurer's own encoding. */
    if ($action === 'new_transaction') {
        $borrowerType = (string) ($_POST['borrower_type'] ?? '');
        $itemId       = (string) ($_POST['item_id'] ?? '');
        $qty          = (int) ($_POST['qty'] ?? 0);
        $dueDate      = trim((string) ($_POST['due_date'] ?? ''));
        $item         = $itemMap[$itemId] ?? null;

        if (!$item || $qty < 1) {
            bodegaFlash('error', 'Pick an item and a quantity of at least 1.');
            bodegaRedirectHere();
        }
        $isConsumable = ($item['item_type'] ?? '') === 'consumable';
        $row = [
            'item_id'     => $item['item_id'],
            'qty'         => $qty,
            'status'      => 'released',
            'released_by' => $actor,
            'notes'       => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ];

        if ($borrowerType === 'staff') {
            $staffId = (string) ($_POST['borrower_user_id'] ?? '');
            if (!preg_match('/^[0-9a-fA-F-]{36}$/', $staffId)) {
                bodegaFlash('error', 'Pick the maintenance worker who is taking the item.');
                bodegaRedirectHere();
            }
            $row['borrower_type']    = 'staff';
            $row['borrower_user_id'] = $staffId;
            if ($isConsumable) {
                $row['txn_type'] = 'use';
            } else {
                if ($dueDate === '') {
                    bodegaFlash('error', 'Set a due date for a returnable item.');
                    bodegaRedirectHere();
                }
                $row['txn_type'] = 'borrow';
                $row['due_date'] = $dueDate;
            }
        } elseif ($borrowerType === 'outsider') {
            if ($isConsumable) {
                bodegaFlash('error', 'Outsiders can only borrow returnable items.');
                bodegaRedirectHere();
            }
            $name    = trim((string) ($_POST['outsider_name'] ?? ''));
            $contact = trim((string) ($_POST['outsider_contact'] ?? ''));
            $letterDate = trim((string) ($_POST['letter_date'] ?? ''));
            if ($name === '' || $letterDate === '' || $dueDate === '') {
                bodegaFlash('error', 'Outsider borrows need a name, the letter date and a due date.');
                bodegaRedirectHere();
            }
            $checked = bodegaCheckFile($_FILES['letter'] ?? ['error' => UPLOAD_ERR_NO_FILE], true);
            if (!$checked['ok']) {
                bodegaFlash('error', $checked['error']);
                bodegaRedirectHere();
            }
            [$letterPath, $uploadErr] = bodegaUpload('letters', $checked);
            if ($uploadErr !== null) {
                bodegaFlash('error', $uploadErr);
                bodegaRedirectHere();
            }
            $row['borrower_type']  = 'outsider';
            $row['outsider_name']  = $name;
            $row['outsider_contact'] = $contact ?: null;
            $row['letter_path']    = $letterPath;
            $row['letter_date']    = $letterDate;
            $row['txn_type']       = 'borrow';
            $row['due_date']       = $dueDate;
        } else {
            bodegaFlash('error', 'Pick whether this is a staff member or an outsider.');
            bodegaRedirectHere();
        }

        $err = bodegaAdjustStock((string) $item['item_id'], -$qty);
        if ($err === null) {
            $err = bodegaInsert('inventory_transactions', $row);
            if ($err !== null) {
                bodegaAdjustStock((string) $item['item_id'], $qty); // undo the stock change
            }
        }
        bodegaFlash($err === null ? 'success' : 'error', $err === null ? 'Release recorded.' : 'Not recorded. ' . $err);
        bodegaRedirectHere();
    }

    bodegaRedirectHere();
}

/* ---- Data ---- */
$items    = bodegaItems();
$itemMap  = bodegaItemMap($items);
$txns     = bodegaTransactions($itemMap);
$pageError = bodegaSetError() ?? '';
$flash    = bodegaTakeFlash();
$staff    = trReleaseWorkers();

$userIds = [];
foreach ($txns as $t) {
    if (!empty($t['borrower_user_id'])) $userIds[] = $t['borrower_user_id'];
}
$names = bodegaUserNames($userIds);

$pending = array_values(array_filter($txns, fn($t) => ($t['status'] ?? '') === 'pending'));
$active  = array_values(array_filter($txns, fn($t) => ($t['txn_type'] ?? '') === 'borrow' && ($t['status'] ?? '') === 'released'));
$recent  = array_slice(array_values(array_filter($txns, fn($t) => !in_array($t['txn_type'] ?? '', ['borrow'], true) || ($t['status'] ?? '') !== 'released')), 0, 20);

function trBorrower(array $t, array $names): string {
    if (($t['borrower_type'] ?? '') === 'outsider') {
        return ($t['outsider_name'] ?? '') . ' (outsider)';
    }
    $id = (string) ($t['borrower_user_id'] ?? '');
    return $names[$id] ?? ($id !== '' ? 'Staff' : '—');
}

renderHead('Borrow / Return', 'Borrow.css', 'treasurer');
?>
<div class="page">
    <div class="one-card">

    <?php renderPageHeader('Borrow / Return', 'Releases, returns and staff requests', 'treasurer'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="tr-alert error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="tr-alert <?= e($flash['type']) ?>" role="status"><?= e($flash['text']) ?></div>
    <?php endif; ?>

    <!-- Pending requests from maintenance workers -->
    <section class="tr-panel">
        <h2 class="tr-section-title">Pending requests <span class="tr-count"><?= count($pending) ?></span></h2>
        <?php if (!$pending): ?>
            <p class="tr-empty">No pending requests.</p>
        <?php else: ?>
        <div class="tr-table-wrap">
            <table class="tr-table">
                <thead><tr><th>Requested</th><th>Item</th><th>Qty</th><th>Type</th><th>Requested by</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($pending as $t): $item = $itemMap[(string) $t['item_id']] ?? []; ?>
                    <tr>
                        <td><?= e(bodegaDate($t['created_at'] ?? '')) ?></td>
                        <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                        <td><?= (int) $t['qty'] ?></td>
                        <td><span class="tr-badge <?= e($item['item_type'] ?? '') ?>"><?= e(ucfirst((string) ($item['item_type'] ?? ''))) ?></span></td>
                        <td><?= e(trBorrower($t, $names)) ?></td>
                        <td class="tr-actions">
                            <form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="txn_id" value="<?= e($t['txn_id']) ?>"><button class="tr-btn" type="submit">Approve</button></form>
                            <form method="post"><input type="hidden" name="action" value="reject"><input type="hidden" name="txn_id" value="<?= e($t['txn_id']) ?>"><button class="tr-btn tr-btn-danger" type="submit">Reject</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Active borrows -->
    <section class="tr-panel">
        <h2 class="tr-section-title">Borrowed, not yet returned <span class="tr-count"><?= count($active) ?></span></h2>
        <?php if (!$active): ?>
            <p class="tr-empty">Nothing is out on loan.</p>
        <?php else: ?>
        <div class="tr-table-wrap">
            <table class="tr-table">
                <thead><tr><th>Item</th><th>Qty</th><th>Borrower</th><th>Due</th><th>Status</th><th>Letter</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($active as $t):
                    $item = $itemMap[(string) $t['item_id']] ?? [];
                    $overdue = bodegaIsOverdue($t, $item);
                ?>
                    <tr>
                        <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                        <td><?= (int) $t['qty'] ?></td>
                        <td><?= e(trBorrower($t, $names)) ?></td>
                        <td><?= e(bodegaDate($t['due_date'] ?? '')) ?></td>
                        <td><span class="tr-badge <?= $overdue ? 'overdue' : 'released' ?>"><?= $overdue ? 'Overdue' : 'Out' ?></span></td>
                        <td>
                            <?php if (!empty($t['letter_path'])): ?>
                                <a class="tr-link" href="?letter=<?= e($t['txn_id']) ?>" target="_blank" rel="noopener">View letter</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post"><input type="hidden" name="action" value="return"><input type="hidden" name="txn_id" value="<?= e($t['txn_id']) ?>"><button class="tr-btn tr-btn-ghost" type="submit">Mark returned</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- New release -->
    <details class="tr-panel tr-add" open>
        <summary>+ New release</summary>
        <form method="post" enctype="multipart/form-data" class="tr-form" id="releaseForm">
            <input type="hidden" name="action" value="new_transaction">

            <fieldset class="tr-field tr-wide tr-radio">
                <legend>Who is taking it?</legend>
                <label><input type="radio" name="borrower_type" value="staff" checked> Maintenance worker</label>
                <label><input type="radio" name="borrower_type" value="outsider"> Outsider (with letter)</label>
            </fieldset>

            <label class="tr-field tr-wide">Item
                <select class="tr-select" name="item_id" required>
                    <option value="" disabled hidden selected>Select an item</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= e($item['item_id']) ?>">
                            <?= e($item['name']) ?> · <?= (int) $item['current_qty'] ?> in stock · <?= e(ucfirst((string) $item['item_type'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="tr-field">Quantity
                <input class="tr-input" type="number" name="qty" min="1" value="1" required>
            </label>

            <div class="tr-field tr-staff-only">Maintenance worker
                <select class="tr-select" name="borrower_user_id">
                    <?php if ($staff): ?>
                        <option value="" disabled hidden selected>Select a worker</option>
                        <?php foreach ($staff as $s): ?>
                            <option value="<?= e($s['user_id']) ?>"><?= e($s['full_name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled selected>No maintenance workers found</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="tr-field tr-outsider-only" hidden>Outsider name
                <input class="tr-input" type="text" name="outsider_name" maxlength="120">
            </div>
            <div class="tr-field tr-outsider-only" hidden>Contact
                <input class="tr-input" type="text" name="outsider_contact" maxlength="60">
            </div>
            <div class="tr-field tr-outsider-only" hidden>Letter date
                <input class="tr-input" type="date" name="letter_date">
            </div>
            <div class="tr-field tr-outsider-only" hidden>Letter (JPG, PNG or PDF, max 5 MB)
                <input class="tr-input" type="file" name="letter" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
            </div>

            <label class="tr-field tr-borrow-only">Due date
                <input class="tr-input" type="date" name="due_date">
            </label>

            <label class="tr-field tr-wide">Notes (optional)
                <input class="tr-input" type="text" name="notes" maxlength="200">
            </label>

            <div class="tr-form-actions">
                <button class="tr-btn" type="submit">Record release</button>
            </div>
        </form>
        <p class="tr-hint">Consumables are used up and need no return. Returnable items need a due date. Outsiders can only borrow returnable items, and the borrow is not saved without the letter.</p>
    </details>

    <!-- Recent movements -->
    <section class="tr-panel">
        <h2 class="tr-section-title">Recent movements</h2>
        <?php if (!$recent): ?>
            <p class="tr-empty">No movements yet.</p>
        <?php else: ?>
        <div class="tr-table-wrap">
            <table class="tr-table">
                <thead><tr><th>When</th><th>Item</th><th>Movement</th><th>Qty</th><th>Who</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $t): $item = $itemMap[(string) $t['item_id']] ?? []; ?>
                    <tr>
                        <td><?= e(bodegaDate($t['created_at'] ?? '')) ?></td>
                        <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                        <td><?= e(ucfirst((string) ($t['txn_type'] ?? ''))) ?></td>
                        <td><?= (int) $t['qty'] ?></td>
                        <td><?= e(trBorrower($t, $names)) ?></td>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('releaseForm');
        const radios = form.querySelectorAll('input[name="borrower_type"]');

        function sync() {
            const outsider = form.querySelector('input[name="borrower_type"]:checked').value === 'outsider';
            form.querySelectorAll('.tr-outsider-only').forEach(function (el) {
                el.hidden = !outsider;
                el.querySelectorAll('input').forEach(function (input) { input.disabled = !outsider; });
            });
            form.querySelectorAll('.tr-staff-only').forEach(function (el) {
                el.hidden = outsider;
                el.querySelectorAll('select').forEach(function (s) { s.disabled = outsider; });
            });
        }
        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    });
</script>
<?php renderFoot(); ?>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>