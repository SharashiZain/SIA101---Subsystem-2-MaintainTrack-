<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function facApi(string $endpoint): array {
    $base = defined('SUPABASE_URL') ? (string)SUPABASE_URL : '';
    $key  = defined('SUPABASE_SECRET_KEY') ? (string)SUPABASE_SECRET_KEY : '';
    if ($base === '' || $key === '' || !function_exists('curl_init')) {
        return ['success'=>false, 'data'=>[], 'error'=>'Supabase configuration or PHP cURL is unavailable.'];
    }
    $ch = curl_init(rtrim($base, '/').'/rest/v1/'.ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['apikey: '.$key, 'Authorization: Bearer '.$key, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return ['success'=>false, 'data'=>[], 'error'=>$err ?: 'Request failed.'];
    $decoded = json_decode($raw, true);
    $ok = $code >= 200 && $code < 300;
    return [
        'success' => $ok,
        'data'    => is_array($decoded) ? $decoded : [],
        'error'   => $ok ? null : (is_array($decoded) ? (string)($decoded['message'] ?? $raw) : $raw),
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
$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));

// Equipment, with lookups for facility, floor and room names.
$equipmentRows = [];
$equipmentResult = facApi('equipment?select=equipment_id,display_id,name,type,category,location,specific_area,facility_id,floor_id,room_id,age_years,last_serviced,status,is_recurring,total_repairs&order=name.asc');
if (!$equipmentResult['success']) {
    $pageError = 'Unable to load equipment. '.(string)$equipmentResult['error'];
} else {
    $equipmentRows = $equipmentResult['data'];
}

$facilityNames = [];
foreach (facApi('facilities?select=facility_id,name')['data'] as $x) {
    $facilityNames[(string)($x['facility_id'] ?? '')] = (string)($x['name'] ?? '');
}
$floorNames = [];
foreach (facApi('facility_floors?select=floor_id,name')['data'] as $x) {
    $floorNames[(string)($x['floor_id'] ?? '')] = (string)($x['name'] ?? '');
}

// All requests linked to equipment, newest first. Used for record counts and the current task.
$requestsByEquipment = [];
$requestResult = facApi('maintenance_requests?select=request_id,display_id,concern,status,equipment_id,assigned_to,date_reported,created_at&equipment_id=not.is.null&order=date_reported.desc.nullslast');
if ($requestResult['success']) {
    foreach ($requestResult['data'] as $r) {
        $eid = (string)($r['equipment_id'] ?? '');
        if ($eid !== '') $requestsByEquipment[$eid][] = $r;
    }
}

// Build the card data.
$cards = [];
foreach ($equipmentRows as $eq) {
    $eid      = (string)($eq['equipment_id'] ?? '');
    $facility = $facilityNames[(string)($eq['facility_id'] ?? '')] ?? '';
    $floor    = $floorNames[(string)($eq['floor_id'] ?? '')] ?? '';
    $area     = trim((string)($eq['specific_area'] ?? ''));

    $locationParts = array_values(array_filter([
        trim((string)($eq['display_id'] ?? '')),
        $facility,
        $floor !== '' ? $floor : $area,
    ], fn($p) => $p !== ''));
    $location = $locationParts ? implode(' · ', $locationParts) : (trim((string)($eq['location'] ?? '')) ?: 'Location not specified');

    $typeLabel = trim((string)($eq['type'] ?? '')) ?: trim((string)($eq['category'] ?? '')) ?: 'Other';

    $requests = $requestsByEquipment[$eid] ?? [];
    $openRequests = array_values(array_filter($requests, fn($r) => !facIsClosed((string)($r['status'] ?? ''))));

    // Prefer an open request assigned to the logged-in personnel; that is the only one this user can open.
    $ownTask = null;
    foreach ($openRequests as $r) {
        if ($currentUserId !== '' && (string)($r['assigned_to'] ?? '') === $currentUserId) {
            $ownTask = $r;
            break;
        }
    }

    if ($ownTask) {
        $statusLabel = 'Current Task:';
        $statusValue = trim((string)($ownTask['display_id'] ?? '')).' · '.trim((string)($ownTask['concern'] ?? 'Maintenance request'));
    } else {
        $statusLabel = 'Last Serviced:';
        $statusValue = facDate($eq['last_serviced'] ?? null) ?: 'Not recorded';
    }

    $tags = [trim((string)($eq['category'] ?? '')) ?: 'Equipment'];
    if (isset($eq['age_years']) && $eq['age_years'] !== null && $eq['age_years'] !== '') {
        $tags[] = (int)$eq['age_years'].' yrs old';
    }
    $tags[] = count($requests).' Records';

    $cards[] = [
        'name'        => (string)($eq['name'] ?? 'Equipment'),
        'location'    => $location,
        'tags'        => $tags,
        'statusLabel' => $statusLabel,
        'statusValue' => $statusValue,
        'href'        => $ownTask ? 'maintenance_request.php?id='.rawurlencode((string)$ownTask['request_id']) : '',
        'category'    => $typeLabel,
        'facility'    => $facility,
    ];
}

// Filter options come from the data itself.
$facilityOptions = array_values(array_unique(array_filter(array_column($cards, 'facility'), fn($v) => $v !== '')));
sort($facilityOptions);
$categoryOptions = array_values(array_unique(array_filter(array_column($cards, 'category'), fn($v) => $v !== '')));
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

    <section class="filter-bar">
        <div class="search-input">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" placeholder="Search equipment..." id="facilitySearch">
        </div>
        <button class="reset-btn" type="button" id="facilityReset">Reset</button>

        <select class="filter-select" id="facilityFilter">
            <option value="">All Facilities</option>
            <?php foreach ($facilityOptions as $name): ?>
                <option value="<?= e($name) ?>"><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filter-select" id="categoryFilter">
            <option value="">All Category</option>
            <?php foreach ($categoryOptions as $name): ?>
                <option value="<?= e($name) ?>"><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
    </section>

    <section class="equip-grid" id="equipGrid">
        <?php foreach ($cards as $item): ?>
            <article class="equip-card"
                     data-category="<?= e($item['category']) ?>"
                     data-facility="<?= e($item['facility']) ?>">
                <h3><?= e($item['name']) ?></h3>
                <span class="equip-loc"><?= e($item['location']) ?></span>
                <div class="equip-tags">
                    <?php foreach ($item['tags'] as $tag): ?>
                        <span class="meta-chip"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="equip-status">
                    <span><?= e($item['statusLabel']) ?></span> <?= e($item['statusValue']) ?>
                </div>
                <?php if ($item['href'] !== ''): ?>
                    <button class="btn-primary" type="button" data-href="<?= e($item['href']) ?>">View Details</button>
                <?php else: ?>
                    <button class="btn-primary" type="button" disabled title="No open task assigned to you for this equipment">No Open Task</button>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>

    <div id="facilityNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
        <p style="font-size: 15px; font-weight: 600;">No equipment found matching your filters.</p>
    </div>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('facilitySearch');
        const facilityFilter = document.getElementById('facilityFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const resetBtn = document.getElementById('facilityReset');
        const noResults = document.getElementById('facilityNoResults');
        const cards = document.querySelectorAll('.equip-card');

        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const facility = facilityFilter.value;
            const category = categoryFilter.value;
            let visible = 0;

            cards.forEach(function (card) {
                const text = card.textContent.toLowerCase();
                const matchesSearch = text.includes(query);
                const matchesFacility = !facility || (card.dataset.facility || '') === facility;
                const matchesCategory = !category || (card.dataset.category || '') === category;

                if (matchesSearch && matchesFacility && matchesCategory) {
                    card.style.display = 'flex';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            noResults.style.display = visible === 0 ? 'block' : 'none';
        }

        searchInput.addEventListener('input', applyFilters);
        facilityFilter.addEventListener('change', applyFilters);
        categoryFilter.addEventListener('change', applyFilters);

        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            facilityFilter.value = '';
            categoryFilter.value = '';
            applyFilters();
        });

        document.querySelectorAll('.equip-card [data-href]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.location.href = button.dataset.href;
            });
        });
    });
</script>
<?php renderFoot(); ?>
</html>