<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function facApi(string $endpoint): array {
    $base = defined('SUPABASE_URL') ? (string) SUPABASE_URL : '';
    $key  = defined('SUPABASE_SECRET_KEY') ? (string) SUPABASE_SECRET_KEY : '';
    if ($base === '' || $key === '' || !function_exists('curl_init')) {
        return ['success' => false, 'data' => [], 'error' => 'Supabase configuration or PHP cURL is unavailable.'];
    }
    $ch = curl_init(rtrim($base, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['apikey: ' . $key, 'Authorization: Bearer ' . $key, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return ['success' => false, 'data' => [], 'error' => $err ?: 'Request failed.'];
    $decoded = json_decode($raw, true);
    $ok = $code >= 200 && $code < 300;
    return [
        'success' => $ok,
        'data'    => is_array($decoded) ? $decoded : [],
        'error'   => $ok ? null : (is_array($decoded) ? (string) ($decoded['message'] ?? $raw) : $raw),
    ];
}

function facDate(?string $value): string {
    if (!$value) return '';
    try {
        return (new DateTime($value, new DateTimeZone('Asia/Manila')))->format('M j, Y');
    } catch (Throwable $t) {
        return '';
    }
}

function facIsClosed(string $status): bool {
    return in_array(strtolower(trim($status)), ['completed', 'cancelled', 'closed'], true);
}

$pageError = '';
$currentUserId = trim((string) ($_SESSION['user_id'] ?? ''));

// Equipment, with lookups for facility and floor names.
$equipmentRows = [];
$equipmentResult = facApi('equipment?select=equipment_id,display_id,name,type,category,location,specific_area,facility_id,floor_id,room_id,age_years,last_serviced,status,is_recurring,total_repairs&order=name.asc');
if (!$equipmentResult['success']) {
    $pageError = 'Unable to load equipment. ' . (string) $equipmentResult['error'];
} else {
    $equipmentRows = $equipmentResult['data'];
}

$facilityNames = [];
foreach (facApi('facilities?select=facility_id,name')['data'] as $x) {
    $facilityNames[(string) ($x['facility_id'] ?? '')] = (string) ($x['name'] ?? '');
}
$floorNames = [];
foreach (facApi('facility_floors?select=floor_id,name')['data'] as $x) {
    $floorNames[(string) ($x['floor_id'] ?? '')] = (string) ($x['name'] ?? '');
}

// All requests linked to equipment, newest first. Used for record counts and the current task.
$requestsByEquipment = [];
$requestResult = facApi('maintenance_requests?select=request_id,display_id,concern,status,equipment_id,assigned_to,date_reported,created_at&equipment_id=not.is.null&order=date_reported.desc.nullslast');
if ($requestResult['success']) {
    foreach ($requestResult['data'] as $r) {
        $eid = (string) ($r['equipment_id'] ?? '');
        if ($eid !== '') $requestsByEquipment[$eid][] = $r;
    }
}

// Build one row per equipment.
$rows = [];
foreach ($equipmentRows as $eq) {
    $eid      = (string) ($eq['equipment_id'] ?? '');
    $facility = $facilityNames[(string) ($eq['facility_id'] ?? '')] ?? '';
    $floor    = $floorNames[(string) ($eq['floor_id'] ?? '')] ?? '';
    $area     = trim((string) ($eq['specific_area'] ?? ''));

    $locationParts = array_values(array_filter([
        $facility,
        $floor !== '' ? $floor : $area,
    ], fn($p) => $p !== ''));
    $location = $locationParts ? implode(' · ', $locationParts) : (trim((string) ($eq['location'] ?? '')) ?: 'Location not specified');

    $category = trim((string) ($eq['type'] ?? '')) ?: trim((string) ($eq['category'] ?? '')) ?: 'Other';

    $requests = $requestsByEquipment[$eid] ?? [];
    $openRequests = array_values(array_filter($requests, fn($r) => !facIsClosed((string) ($r['status'] ?? ''))));

    // The open request assigned to the logged-in personnel, if any. That is the only one this user can open.
    $ownTask = null;
    foreach ($openRequests as $r) {
        if ($currentUserId !== '' && (string) ($r['assigned_to'] ?? '') === $currentUserId) {
            $ownTask = $r;
            break;
        }
    }

    $age = (isset($eq['age_years']) && $eq['age_years'] !== null && $eq['age_years'] !== '') ? (int) $eq['age_years'] : null;

    $rows[] = [
        'name'      => (string) ($eq['name'] ?? 'Equipment'),
        'code'      => trim((string) ($eq['display_id'] ?? '')),
        'location'  => $location,
        'facility'  => $facility,
        'category'  => $category,
        'age'       => $age,
        'records'   => count($requests),
        'last'      => facDate($eq['last_serviced'] ?? null) ?: 'Not recorded',
        'mine'      => $ownTask !== null,
        'task_id'   => $ownTask ? trim((string) ($ownTask['display_id'] ?? '')) : '',
        'task_text' => $ownTask ? trim((string) ($ownTask['concern'] ?? 'Maintenance request')) : '',
        'href'      => $ownTask ? 'maintenance_request.php?id=' . rawurlencode((string) $ownTask['request_id']) : '',
    ];
}

// Filter options come from the data itself.
$facilityOptions = array_values(array_unique(array_filter(array_column($rows, 'facility'), fn($v) => $v !== '')));
sort($facilityOptions);
$categoryOptions = array_values(array_unique(array_filter(array_column($rows, 'category'), fn($v) => $v !== '')));
sort($categoryOptions);

renderHead('Facilities & Equipment', 'Facility.css', 'maintenance');
?>
<div class="page">

    <!-- ONE CONTAINER: banner + all page content -->
    <div class="one-card">

    <?php renderPageHeader('Facilities & Equipment', 'Maintenance Personnel Portal • Barangay Gulod', 'maintenance'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="task-alert" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <section class="filter-bar fac-filters">
        <div class="search-input">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" placeholder="Search name, code, location, category..." id="facilitySearch" aria-label="Search equipment">
        </div>

        <select class="filter-select" id="facilityFilter" aria-label="Filter by facility">
            <option value="" disabled hidden selected>All Facilities</option>
            <?php foreach ($facilityOptions as $name): ?>
                <option value="<?= e($name) ?>"><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filter-select" id="categoryFilter" aria-label="Filter by category">
            <option value="" disabled hidden selected>All Categories</option>
            <?php foreach ($categoryOptions as $name): ?>
                <option value="<?= e($name) ?>"><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filter-select" id="statusFilter" aria-label="Filter by your task">
            <option value="" disabled hidden selected>All Statuses</option>
            <option value="mine">Assigned to me</option>
            <option value="none">Not assigned to me</option>
        </select>

        <select class="filter-select" id="sortFilter" aria-label="Sort equipment">
            <option value="name">Sort: Name A–Z</option>
            <option value="records">Sort: Most records</option>
            <option value="age">Sort: Oldest age</option>
        </select>

        <button class="reset-btn" type="button" id="facilityReset">Reset</button>
    </section>

    <p class="fac-count" id="facCount"></p>

    <div class="fac-table-wrap">
        <table class="fac-table">
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>Location</th>
                    <th>Category</th>
                    <th>Age</th>
                    <th>Records</th>
                    <th>Last Serviced</th>
                    <th>Your Task</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="facBody">
                <?php foreach ($rows as $row): ?>
                    <tr data-search="<?= e(strtolower($row['name'] . ' ' . $row['code'] . ' ' . $row['location'] . ' ' . $row['category'] . ' ' . $row['task_id'] . ' ' . $row['task_text'])) ?>"
                        data-facility="<?= e($row['facility']) ?>"
                        data-category="<?= e($row['category']) ?>"
                        data-status="<?= $row['mine'] ? 'mine' : 'none' ?>"
                        data-name="<?= e(strtolower($row['name'])) ?>"
                        data-records="<?= (int) $row['records'] ?>"
                        data-age="<?= $row['age'] === null ? '' : (int) $row['age'] ?>">
                        <td>
                            <strong><?= e($row['name']) ?></strong>
                            <small><?= e($row['code']) ?></small>
                        </td>
                        <td><?= e($row['location']) ?></td>
                        <td><span class="fac-chip"><?= e($row['category']) ?></span></td>
                        <td><?= $row['age'] === null ? '—' : e((string) $row['age']) . ' yrs' ?></td>
                        <td><span class="fac-chip fac-chip-blue"><?= (int) $row['records'] ?></span></td>
                        <td><?= e($row['last']) ?></td>
                        <td>
                            <?php if ($row['mine']): ?>
                                <span class="fac-chip fac-chip-amber"><?= e($row['task_id']) ?></span>
                                <small class="fac-sub"><?= e($row['task_text']) ?></small>
                            <?php else: ?>
                                <span class="fac-muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="fac-action">
                            <?php if ($row['href'] !== ''): ?>
                                <button class="btn-primary fac-btn" type="button" data-href="<?= e($row['href']) ?>">View Details</button>
                            <?php else: ?>
                                <button class="btn-primary fac-btn" type="button" disabled title="No open task assigned to you for this equipment">No Open Task</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="facilityNoResults" class="fac-empty" style="display: none;">
        <p>No equipment found matching your filters.</p>
    </div>

    </div>

</div>

<style>
.fac-filters .search-input { min-width: 260px; }
.fac-count { margin: 0 0 10px; font-size: 12px; font-weight: 600; color: #687985; }
.fac-table-wrap { overflow-x: auto; border: 1px solid #e3ecf2; border-radius: 12px; background: #ffffff; }
.fac-table { width: 100%; border-collapse: collapse; font-size: 13px; color: #1f2d3a; }
.fac-table th {
    position: sticky; top: 0; z-index: 1;
    text-align: left; padding: 12px 14px; background: #f4f8fb;
    font-size: 11px; font-weight: 700; color: #4a6072;
    text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e3ecf2; white-space: nowrap;
}
.fac-table td { padding: 12px 14px; border-bottom: 1px solid #eef3f7; vertical-align: middle; }
.fac-table tbody tr:last-child td { border-bottom: none; }
.fac-table tbody tr:hover { background: #f8fbfd; }
.fac-table td strong { display: block; font-size: 13px; font-weight: 700; color: #123a5e; }
.fac-table td small { display: block; margin-top: 2px; font-size: 11px; color: #71839a; }
.fac-sub { overflow-wrap: anywhere; max-width: 260px; }
.fac-muted { color: #9aa9b7; font-size: 12px; }
.fac-chip {
    display: inline-block; padding: 3px 9px; border-radius: 999px;
    background: #eef5fb; color: #1d4f91; font-size: 11px; font-weight: 700; white-space: nowrap;
}
.fac-chip-blue { background: #e4f0fc; color: #1f6fb8; }
.fac-chip-amber { background: #fff3d9; color: #9a6500; }
.fac-action { text-align: right; }
.fac-btn { height: 36px; padding: 0 14px; font-size: 12px; white-space: nowrap; }
.fac-empty { text-align: center; padding: 40px; color: #687985; }
.fac-empty p { font-size: 15px; font-weight: 600; margin: 0; }
@media (max-width: 900px) { .fac-filters .search-input { min-width: 100%; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const body = document.getElementById('facBody');
    const rows = Array.from(body.querySelectorAll('tr[data-search]'));
    const searchInput = document.getElementById('facilitySearch');
    const facilityFilter = document.getElementById('facilityFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const sortFilter = document.getElementById('sortFilter');
    const resetBtn = document.getElementById('facilityReset');
    const noResults = document.getElementById('facilityNoResults');
    const countLabel = document.getElementById('facCount');

    // Show or hide rows based on every filter at once.
    function applyFilters() {
        const q = searchInput.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach(function (row) {
            const ok = (!q || row.dataset.search.includes(q))
                && (!facilityFilter.value || row.dataset.facility === facilityFilter.value)
                && (!categoryFilter.value || row.dataset.category === categoryFilter.value)
                && (!statusFilter.value || row.dataset.status === statusFilter.value);
            row.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        countLabel.textContent = 'Showing ' + shown + ' of ' + rows.length + ' equipment';
        noResults.style.display = (rows.length && shown === 0) ? 'block' : 'none';
    }

    // Reorder the rows in the table. Reordering does not hide anything.
    function sortRows() {
        const mode = sortFilter.value;
        const sorted = rows.slice().sort(function (a, b) {
            if (mode === 'records') {
                return (Number(b.dataset.records) - Number(a.dataset.records)) || a.dataset.name.localeCompare(b.dataset.name);
            }
            if (mode === 'age') {
                const ageA = a.dataset.age === '' ? -1 : Number(a.dataset.age);
                const ageB = b.dataset.age === '' ? -1 : Number(b.dataset.age);
                return (ageB - ageA) || a.dataset.name.localeCompare(b.dataset.name);
            }
            return a.dataset.name.localeCompare(b.dataset.name);
        });
        sorted.forEach(function (row) { body.appendChild(row); });
    }

    searchInput.addEventListener('input', applyFilters);
    facilityFilter.addEventListener('change', applyFilters);
    categoryFilter.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    sortFilter.addEventListener('change', function () { sortRows(); applyFilters(); });

    resetBtn.addEventListener('click', function () {
        searchInput.value = '';
        facilityFilter.value = '';
        categoryFilter.value = '';
        statusFilter.value = '';
        sortFilter.value = 'name';
        sortRows();
        applyFilters();
    });

    document.querySelectorAll('.fac-table [data-href]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.location.href = button.dataset.href;
        });
    });

    sortRows();
    applyFilters();
});
</script>
<?php renderFoot(); ?>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>