<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/bodega.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$actor = bodegaActorId();
$room  = bodegaRoomId();

/* ---- Actions (POST, then redirect back) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($room === '') {
        bodegaFlash('error', 'The ground floor bodega is not set up yet.');
        bodegaRedirectHere();
    }

    if ($action === 'add_item') {
        $name     = trim((string) ($_POST['name'] ?? ''));
        $category = (string) ($_POST['category'] ?? '');
        $unit     = trim((string) ($_POST['unit'] ?? '')) ?: 'pc';
        $type     = (string) ($_POST['item_type'] ?? '');
        $reorder  = max(0, (int) ($_POST['reorder_level'] ?? 0));
        $opening  = max(0, (int) ($_POST['opening_qty'] ?? 0));

        if ($name === '' || !in_array($category, BODEGA_CATEGORIES, true)) {
            bodegaFlash('error', 'Enter an item name and pick a valid category.');
            bodegaRedirectHere();
        }
        if (!in_array($type, ['consumable', 'returnable'], true)) {
            $type = bodegaDefaultType($category);
        }

        $created = bodegaRest('POST', 'inventory_items', [
            'name'          => $name,
            'category'      => $category,
            'item_type'     => $type,
            'unit'          => $unit,
            'reorder_level' => $reorder,
            'current_qty'   => $opening,
            'room_id'       => $room,
        ]);
        if (!$created['success']) {
            bodegaFlash('error', 'Item was not added. ' . $created['error']);
            bodegaRedirectHere();
        }

        if ($opening > 0) {
            $itemId = (string) ($created['data'][0]['item_id'] ?? '');
            $err = bodegaInsert('inventory_transactions', [
                'item_id'     => $itemId,
                'txn_type'    => 'adjustment',
                'qty'         => $opening,
                'status'      => 'released',
                'released_by' => $actor,
                'notes'       => 'Opening stock',
            ]);
            if ($err !== null) {
                bodegaFlash('error', 'Item added, but the opening stock was not logged. ' . $err);
                bodegaRedirectHere();
            }
        }
        bodegaFlash('success', $name . ' was added to the bodega.');
        bodegaRedirectHere();
    }

    if ($action === 'update_item') {
        $itemId  = (string) ($_POST['item_id'] ?? '');
        $type    = (string) ($_POST['item_type'] ?? '');
        $reorder = max(0, (int) ($_POST['reorder_level'] ?? 0));

        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $itemId) || !in_array($type, ['consumable', 'returnable'], true)) {
            bodegaFlash('error', 'Invalid item update.');
            bodegaRedirectHere();
        }
        $err = bodegaUpdate('inventory_items', 'item_id=eq.' . rawurlencode($itemId) . '&room_id=eq.' . rawurlencode($room), [
            'item_type'     => $type,
            'reorder_level' => $reorder,
        ]);
        bodegaFlash($err === null ? 'success' : 'error', $err === null ? 'Item updated.' : 'Update failed. ' . $err);
        bodegaRedirectHere();
    }

    bodegaRedirectHere();
}

/* ---- Data ---- */
$items    = bodegaItems();
$pageError = bodegaSetError() ?? '';
$flash    = bodegaTakeFlash();
$filterCategory = (string) ($_GET['category'] ?? '');

renderHead('Inventory', 'Inventory.css', 'treasurer');
?>
<div class="page">
    <div class="one-card">

    <?php renderPageHeader('Inventory', 'Bodega stock · Ground floor', 'treasurer'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="tr-alert error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="tr-alert <?= e($flash['type']) ?>" role="status"><?= e($flash['text']) ?></div>
    <?php endif; ?>

    <details class="tr-panel tr-add">
        <summary>+ Add item</summary>
        <form method="post" class="tr-form">
            <input type="hidden" name="action" value="add_item">
            <label class="tr-field">Item name
                <input class="tr-input" type="text" name="name" required maxlength="120">
            </label>
            <label class="tr-field">Category
                <select class="tr-select" name="category" id="addCategory" required>
                    <?php foreach (BODEGA_CATEGORIES as $cat): ?>
                        <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="tr-field">Type
                <select class="tr-select" name="item_type">
                    <option value="" disabled hidden selected>Default for category</option>
                    <option value="returnable">Returnable (borrowed and returned)</option>
                    <option value="consumable">Consumable (used up)</option>
                </select>
            </label>
            <label class="tr-field">Unit
                <input class="tr-input" type="text" name="unit" placeholder="pc, box, pack" maxlength="20">
            </label>
            <label class="tr-field">Opening stock
                <input class="tr-input" type="number" name="opening_qty" min="0" value="0">
            </label>
            <label class="tr-field">Reorder at
                <input class="tr-input" type="number" name="reorder_level" min="0" value="0">
            </label>
            <div class="tr-form-actions">
                <button class="tr-btn" type="submit">Add item</button>
            </div>
        </form>
    </details>

    <section class="tr-toolbar">
        <div class="tr-search">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" id="itemSearch" placeholder="Search items...">
        </div>
        <form method="get" class="tr-inline">
            <select class="tr-select" name="category" onchange="this.form.submit()">
                <option value="" disabled hidden <?= $filterCategory === '' ? 'selected' : '' ?>>All categories</option>
                <?php foreach (BODEGA_CATEGORIES as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <?php /* One form per item, linked to its row's inputs with the form attribute (a form cannot sit inside a table row). */ ?>
    <div hidden>
        <?php foreach ($items as $item): ?>
            <form method="post" id="form-<?= e($item['item_id']) ?>">
                <input type="hidden" name="action" value="update_item">
                <input type="hidden" name="item_id" value="<?= e($item['item_id']) ?>">
            </form>
        <?php endforeach; ?>
    </div>

    <div class="tr-table-wrap">
        <table class="tr-table" id="itemTable">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>In stock</th>
                    <th>Reorder at</th>
                    <th>Status</th>
                    <th>Save</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item):
                if ($filterCategory !== '' && ($item['category'] ?? '') !== $filterCategory) continue;
                $low = bodegaIsLow($item);
                $type = (string) ($item['item_type'] ?? 'returnable');
                $formId = 'form-' . $item['item_id'];
            ?>
                <tr data-search="<?= e(strtolower(($item['name'] ?? '') . ' ' . ($item['category'] ?? ''))) ?>">
                    <td class="tr-strong"><?= e($item['name'] ?? '') ?></td>
                    <td><?= e($item['category'] ?? '') ?></td>
                    <td>
                        <select class="tr-select tr-small" name="item_type" form="<?= e($formId) ?>">
                            <option value="returnable" <?= $type === 'returnable' ? 'selected' : '' ?>>Returnable</option>
                            <option value="consumable" <?= $type === 'consumable' ? 'selected' : '' ?>>Consumable</option>
                        </select>
                    </td>
                    <td><?= (int) ($item['current_qty'] ?? 0) ?> <?= e($item['unit'] ?? '') ?></td>
                    <td>
                        <input class="tr-input tr-small" type="number" name="reorder_level" min="0" form="<?= e($formId) ?>" value="<?= (int) ($item['reorder_level'] ?? 0) ?>">
                    </td>
                    <td>
                        <span class="tr-badge <?= $low ? 'low' : 'ok' ?>"><?= $low ? 'Low stock' : 'OK' ?></span>
                    </td>
                    <td>
                        <button class="tr-btn tr-btn-ghost" type="submit" form="<?= e($formId) ?>">Save</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$items && $pageError === ''): ?>
            <p class="tr-empty">No items yet. Use "Add item" above.</p>
        <?php endif; ?>
    </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('itemSearch');
        const rows = document.querySelectorAll('#itemTable tbody tr');
        search.addEventListener('input', function () {
            const q = search.value.trim().toLowerCase();
            rows.forEach(function (row) {
                row.style.display = (row.dataset.search || '').includes(q) ? '' : 'none';
            });
        });
    });
</script>
<?php renderFoot(); ?>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>