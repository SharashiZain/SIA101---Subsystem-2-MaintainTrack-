<?php
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../includes/PriorityScore.php';

/* =========================================================
   Supabase helper (server-side only)
   Uses the secret key so row-level security does not hide rows from
   this page. The key stays in PHP and is never sent to the browser.
========================================================= */
function dashRest(string $path): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'data' => null];
    }

    $ch = curl_init(rtrim(SUPABASE_URL, '/') . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'data' => null];
    }

    return [
        'ok'   => $status >= 200 && $status < 300,
        'data' => json_decode($response, true),
    ];
}

/* Where a request opens: still waiting -> Approvals, already decided -> History */
function dashRequestUrl(string $id, string $statusKey): string
{
    return ($statusKey === 'pending' ? 'secretary_approvals.php' : 'secretary_history.php')
        . '?id=' . rawurlencode($id) . '&highlight=1';
}

/* =========================================================
   LOAD DATA FROM SUPABASE
========================================================= */
$requests = [];
$facilities = [];
$loadError = '';

$reqRes = dashRest(
    '/rest/v1/maintenance_requests'
    . '?select=request_id,display_id,concern,description,category,priority,status,approval_status,date_reported,specific_area,equipment_id,facility_id,reported_by,created_at'
    . '&order=created_at.desc'
);
$facRes = dashRest('/rest/v1/facilities?select=facility_id,name');
$eqRes  = dashRest(
    '/rest/v1/equipment'
    . '?select=equipment_id,display_id,name,type,facility_id,location,specific_area,status,age_years,last_serviced,registration_date'
    . '&order=display_id.asc'
);
$usrRes = dashRest('/rest/v1/users?select=user_id,full_name');

if (!$reqRes['ok'] || !is_array($reqRes['data'])) {
    $loadError = 'Could not load data from the database.';
} else {
    $facilityNames = [];
    $equipmentNames = [];
    $users = [];

    if ($facRes['ok'] && is_array($facRes['data'])) foreach ($facRes['data'] as $f) $facilityNames[$f['facility_id']] = $f['name'];
    if ($usrRes['ok'] && is_array($usrRes['data'])) foreach ($usrRes['data'] as $u) $users[$u['user_id']] = $u['full_name'];
    if ($eqRes['ok']  && is_array($eqRes['data']))  foreach ($eqRes['data']  as $q) $equipmentNames[$q['equipment_id']] = $q['name'];

    $manila = new DateTimeZone('Asia/Manila');
    $formatDate = function (?string $value, string $format = 'M j, Y') use ($manila): string {
        if (empty($value)) return '';
        $dt = new DateTime($value);
        $dt->setTimezone($manila);
        return $dt->format($format);
    };

    /* ---------- Requests ---------- */
    $openByEquipment = []; // equipment_id => open request (newest first, so the first one wins)

    foreach ($reqRes['data'] as $row) {
        $approval  = $row['approval_status'] ?: 'Pending';
        $statusKey = strtolower($approval); // pending | approved | rejected

        $facility = $facilityNames[$row['facility_id'] ?? ''] ?? 'Unknown facility';
        if (!empty($row['specific_area'])) {
            $facility .= ' – ' . $row['specific_area'];
        }

        $requests[] = [
            'id'          => $row['display_id'],
            'title'       => $row['concern'],
            'facility'    => $facility,
            'equipment'   => $equipmentNames[$row['equipment_id'] ?? ''] ?? 'N/A',
            'submitter'   => $users[$row['reported_by'] ?? ''] ?? 'Unknown',
            'date'        => $formatDate($row['date_reported'] ?? null),
            'category'    => $row['category'] ?: 'General',
            'urgency'     => $row['priority'] ?: 'Medium',
            'status'      => $statusKey === 'pending' ? 'Pending Approval' : $approval,
            'statusKey'   => $statusKey,
            'description' => $row['description'] ?: '',
        ];

        // An equipment item "needs repair" while it has an approved/pending request that is not completed yet
        $eqId = $row['equipment_id'] ?? null;
        if ($eqId && $approval !== 'Rejected' && ($row['status'] ?? '') !== 'Completed' && !isset($openByEquipment[$eqId])) {
            $openByEquipment[$eqId] = ['id' => $row['display_id'], 'statusKey' => $statusKey, 'concern' => $row['concern']];
        }
    }

    /* ---------- Facilities & equipment ---------- */
    if ($eqRes['ok'] && is_array($eqRes['data'])) {
        foreach ($eqRes['data'] as $row) {
            $open = $openByEquipment[$row['equipment_id']] ?? null;
            $needsRepair = $open !== null || (($row['status'] ?? 'Active') !== 'Active');

            $facilityName = $facilityNames[$row['facility_id'] ?? ''] ?? '';
            $place = $facilityName;
            if (!empty($row['location'])) $place .= ($place !== '' ? ' · ' : '') . $row['location'];
            if ($place === '') $place = '—';

            $placeFull = $place;
            if (!empty($row['specific_area'])) $placeFull .= ($place !== '—' ? ' · ' : '') . $row['specific_area'];

            $facilities[] = [
                'id'             => $row['display_id'],
                'name'           => $row['name'],
                'category'       => $row['type'] ?: 'Equipment',
                'location'       => $place,
                'locationFull'   => $placeFull,
                'status'         => $needsRepair ? 'Needs Repair' : 'Active',
                'statusKey'      => $needsRepair ? 'repair' : 'active',
                'condition'      => $open ? $open['concern'] : (($row['status'] ?? 'Active') === 'Active' ? 'Good' : $row['status']),
                'lastServiced'   => !empty($row['last_serviced']) ? $formatDate($row['last_serviced']) : 'Not yet serviced',
                'registered'     => !empty($row['registration_date']) ? $formatDate($row['registration_date']) : '—',
                'age'            => ($row['age_years'] !== null && $row['age_years'] !== '') ? ((int)$row['age_years'] . ' year' . ((int)$row['age_years'] === 1 ? '' : 's') . ' old') : '—',
                'relatedRequest' => $open['id'] ?? null,
                'relatedUrl'     => $open ? dashRequestUrl($open['id'], $open['statusKey']) : null,
            ];
        }

        // Equipment that needs repair first, then everything else
        usort($facilities, fn($a, $b) => [$a['statusKey'] === 'repair' ? 0 : 1, $a['id']] <=> [$b['statusKey'] === 'repair' ? 0 : 1, $b['id']]);
    }
}

/* =========================================================
   COMPUTE COUNTS
========================================================= */
$requestCounts = ['total' => count($requests), 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($requests as $r) {
    if (isset($requestCounts[$r['statusKey']])) $requestCounts[$r['statusKey']]++;
}

$facilityCounts = ['total' => count($facilities), 'active' => 0, 'repair' => 0];
foreach ($facilities as $f) {
    if (isset($facilityCounts[$f['statusKey']])) $facilityCounts[$f['statusKey']]++;
}

$requestStats = [
    ['label' => 'Total Requests', 'value' => $requestCounts['total'],    'color' => '',       'filter' => ''],
    ['label' => 'Pending',        'value' => $requestCounts['pending'],  'color' => 'orange', 'filter' => 'pending'],
    ['label' => 'Approved',       'value' => $requestCounts['approved'], 'color' => 'green',  'filter' => 'approved'],
    ['label' => 'Rejected',       'value' => $requestCounts['rejected'], 'color' => 'red',    'filter' => 'rejected'],
];

$facilityStats = [
    ['label' => 'Total Assets', 'value' => $facilityCounts['total'],  'color' => 'blue',  'filter' => 'facilities'],
    ['label' => 'Active',       'value' => $facilityCounts['active'], 'color' => 'green', 'filter' => 'facilities'],
    ['label' => 'Needs Repair', 'value' => $facilityCounts['repair'], 'color' => 'red',   'filter' => 'facilities'],
];

/* Waiting for review: same priority order as the Approvals page */
$allRequests = $requests; // recurrence is counted across every request, decided or not
$pending = array_values(array_filter($requests, fn($r) => $r['statusKey'] === 'pending'));
if (function_exists('buildPriorityQueue')) {
    $pending = array_values(buildPriorityQueue($pending));
}

renderHead('Secretary Dashboard', 'Dashboard.css', 'secretary');

/* Explicit stylesheet link: assets/css/secretary/secretary_Dashboard.css */
$dashCssFile = dirname(__DIR__, 2) . '/assets/css/secretary/secretary_Dashboard.css';
$dashCssVer  = is_file($dashCssFile) ? filemtime($dashCssFile) : time();
?>
<link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/secretary/secretary_Dashboard.css')) ?>">
<div class="page">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('Secretary Dashboard', 'Approval & Concern Portal · Barangay Gulod', 'secretary'); ?>

    <?php if ($loadError): ?>
        <div style="padding: 14px 18px; margin-bottom: 14px; border-radius: 10px; background: #fee2e2; color: #991b1b; font-size: 13px; font-weight: 600;">
            <?= e($loadError) ?>
        </div>
    <?php endif; ?>

    <!-- REQUESTS STATS -->
    <section class="stats-section">
        <h3 class="stats-section-label">Requests Overview</h3>
        <div class="stats stats-4">
            <?php foreach ($requestStats as $stat): ?>
                <div class="stat-card" data-filter="<?= e($stat['filter']) ?>" style="cursor: pointer;">
                    <span><?= e($stat['label']) ?></span>
                    <strong<?= $stat['color'] ? ' class="' . e($stat['color']) . '"' : '' ?>><?= (int)$stat['value'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- FACILITIES & EQUIPMENT STATS -->
    <section class="stats-section">
        <h3 class="stats-section-label">Facilities & Equipment Overview</h3>
        <div class="stats stats-3">
            <?php foreach ($facilityStats as $stat): ?>
                <div class="stat-card" data-filter="<?= e($stat['filter']) ?>" style="cursor: pointer;">
                    <span><?= e($stat['label']) ?></span>
                    <strong<?= $stat['color'] ? ' class="' . e($stat['color']) . '"' : '' ?>><?= (int)$stat['value'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="dashboard-grid">

        <!-- PENDING APPROVALS -->
        <section class="panel">
            <div class="panel-head">
                <h2>Waiting for Your Review</h2>
                <a href="secretary_approvals.php" class="panel-link">View all →</a>
            </div>

            <?php if (empty($pending)): ?>
                <div class="empty-state">
                    <p>No pending submissions. Everything is up to date.</p>
                </div>
            <?php else: ?>
                <div class="approval-list">
                    <?php foreach ($pending as $req): ?>
                        <div class="approval-item" onclick="window.location.href='<?= e(dashRequestUrl($req['id'], 'pending')) ?>'">
                            <div class="approval-main">
                                <div class="approval-id"><?= e($req['id']) ?></div>
                                <div class="approval-title"><?= e($req['title']) ?></div>
                                <div class="approval-meta">
                                    <?= e($req['facility']) ?> · <?= e($req['equipment']) ?> · <?= e($req['submitter']) ?>
                                </div>
                            </div>
                            <div class="approval-side">
                                <span class="urgency urgency-<?= strtolower($req['urgency']) ?>"><?= e($req['urgency']) ?></span>
                                <span class="chevron" aria-hidden="true">›</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- FACILITIES & EQUIPMENT LIST -->
        <section class="panel">
            <div class="panel-head">
                <h2>Facilities & Equipment</h2>
                <span class="panel-count"><?= (int)$facilityCounts['total'] ?> total</span>
            </div>

            <?php if (empty($facilities)): ?>
                <div class="empty-state">
                    <p>No equipment has been registered yet.</p>
                </div>
            <?php endif; ?>

            <!-- Scrolls inside the panel because the list can be long -->
            <div class="utility-list" style="max-height: 560px; overflow-y: auto; padding-right: 4px;">
                <?php foreach ($facilities as $f): ?>
                    <div class="utility-item"
                         data-status="<?= e($f['statusKey']) ?>"
                         data-utility='<?= e(json_encode($f)) ?>'
                         data-related-url="<?= e($f['relatedUrl'] ?? '') ?>"
                         onclick="handleEquipmentClick(this)">
                        <div class="utility-main">
                            <div class="utility-id">
                                <?= e($f['id']) ?>
                                <?php if (!empty($f['relatedRequest'])): ?>
                                    <span class="linked-badge" title="Has a related request">●</span>
                                <?php endif; ?>
                            </div>
                            <div class="utility-name"><?= e($f['name']) ?></div>
                            <div class="utility-meta">
                                <?= e($f['category']) ?> · <?= e($f['location']) ?>
                            </div>
                        </div>
                        <div class="utility-side">
                            <span class="utility-pill utility-<?= e($f['statusKey']) ?>"><?= e($f['status']) ?></span>
                            <button type="button"
                                    class="utility-info-btn"
                                    title="View details"
                                    aria-label="View details"
                                    onclick="event.stopPropagation(); openFacilityModal(this.closest('.utility-item'));">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>

    </div><!-- /.one-card -->
</div>

<!-- FACILITY DETAIL MODAL -->
<div class="static-modal" id="facilityModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3 id="facilityModalTitle">Facility Details</h3>
            <button type="button" class="modal-close" id="facilityModalClose">✕</button>
        </div>

        <div class="utility-detail">
            <div class="utility-detail-grid">
                <div class="detail-cell">
                    <span class="detail-cell-label">Equipment ID</span>
                    <span class="detail-cell-value" id="facId">—</span>
                </div>
                <div class="detail-cell">
                    <span class="detail-cell-label">Status</span>
                    <span class="detail-cell-value" id="facStatus">—</span>
                </div>
                <div class="detail-cell">
                    <span class="detail-cell-label">Category</span>
                    <span class="detail-cell-value" id="facCategory">—</span>
                </div>
                <div class="detail-cell">
                    <span class="detail-cell-label">Location</span>
                    <span class="detail-cell-value" id="facLocation">—</span>
                </div>
                <div class="detail-cell">
                    <span class="detail-cell-label">Condition</span>
                    <span class="detail-cell-value" id="facCondition">—</span>
                </div>
                <div class="detail-cell">
                    <span class="detail-cell-label">Age</span>
                    <span class="detail-cell-value" id="facAge">—</span>
                </div>
                <div class="detail-cell">
                    <span class="detail-cell-label">Registered</span>
                    <span class="detail-cell-value" id="facRegistered">—</span>
                </div>
                <div class="detail-cell">
                    <span class="detail-cell-label">Last Serviced</span>
                    <span class="detail-cell-value" id="facLastServiced">—</span>
                </div>
            </div>

            <div class="utility-related" id="facRelatedWrap" style="display: none;">
                <span class="detail-cell-label">Related Request</span>
                <a href="#" class="utility-related-link" id="facRelated">—</a>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="facilityModalCancel">Close</button>
        </div>
    </div>
</div>

<!-- TOAST NOTIFICATION -->
<div class="toast" id="toast" role="status" aria-live="polite" style="display: none;">
    <span class="toast-message" id="toastMessage">Message</span>
</div>

<script>
    /* =========================================================
       EQUIPMENT CLICK → OPEN THE RELATED REQUEST + HIGHLIGHT
       (Approvals if still pending, History if already decided)
    ========================================================= */
    function handleEquipmentClick(el) {
        const url = el.dataset.relatedUrl || '';

        if (!url) {
            showToast('No matching request for this equipment.');
            return;
        }

        window.location.href = url;
    }

    /* =========================================================
       TOAST
    ========================================================= */
    let toastTimer = null;
    function showToast(message) {
        const toast = document.getElementById('toast');
        const msg = document.getElementById('toastMessage');
        msg.textContent = message;
        toast.style.display = 'flex';

        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.style.display = 'none';
        }, 2200);
    }

    /* =========================================================
       FACILITY MODAL
    ========================================================= */
    function openFacilityModal(el) {
        const data = JSON.parse(el.dataset.utility);

        document.getElementById('facilityModalTitle').textContent = data.name;
        document.getElementById('facId').textContent = data.id;
        document.getElementById('facStatus').textContent = data.status;
        document.getElementById('facCategory').textContent = data.category;
        document.getElementById('facLocation').textContent = data.locationFull;
        document.getElementById('facCondition').textContent = data.condition;
        document.getElementById('facAge').textContent = data.age;
        document.getElementById('facRegistered').textContent = data.registered;
        document.getElementById('facLastServiced').textContent = data.lastServiced;

        const relatedWrap = document.getElementById('facRelatedWrap');
        const relatedLink = document.getElementById('facRelated');

        if (data.relatedRequest) {
            relatedWrap.style.display = 'block';
            relatedLink.textContent = data.relatedRequest + ' →';
            relatedLink.href = data.relatedUrl;
        } else {
            relatedWrap.style.display = 'none';
        }

        document.getElementById('facilityModal').style.display = 'flex';
    }

    function closeFacilityModal() {
        document.getElementById('facilityModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('facilityModalClose').addEventListener('click', closeFacilityModal);
        document.getElementById('facilityModalCancel').addEventListener('click', closeFacilityModal);

        document.getElementById('facilityModal').addEventListener('click', function (e) {
            if (e.target === this) closeFacilityModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFacilityModal();
        });

        /* Stat card clicks */
        document.querySelectorAll('.stat-card').forEach(function (card) {
            card.addEventListener('click', function () {
                const filter = this.dataset.filter;
                if (filter === 'facilities') {
                    document.querySelector('.utility-list').scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else if (filter === '') {
                    window.location.href = 'secretary_approvals.php';
                } else {
                    window.location.href = 'secretary_approvals.php?status=' + filter;
                }
            });
        });
    });
</script>
<?php renderFoot(); ?>
</html>