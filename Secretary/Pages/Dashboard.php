<?php
require_once __DIR__ . '/../../Components/layout.php';

/* =========================================================
   REQUESTS DATA
========================================================= */
$requests = [
    [
        'id' => 'MT-00131', 'title' => 'PC won\'t turn on',
        'facility' => 'Lab 1', 'equipment' => 'Computer Unit #01',
        'submitter' => 'Juan Dela Cruz', 'date' => 'Oct 14, 2026',
        'urgency' => 'High', 'status' => 'Pending Approval', 'statusKey' => 'pending',
    ],
    [
        'id' => 'MT-00132', 'title' => 'Broken aircon',
        'facility' => 'Lab 1', 'equipment' => 'Aircon Unit 02',
        'submitter' => 'Maria Santos', 'date' => 'Oct 14, 2026',
        'urgency' => 'Medium', 'status' => 'Pending Approval', 'statusKey' => 'pending',
    ],
    [
        'id' => 'MT-00133', 'title' => 'No internet connection',
        'facility' => 'Lab 2', 'equipment' => 'Switch #2',
        'submitter' => 'Carlo Reyes', 'date' => 'Oct 13, 2026',
        'urgency' => 'High', 'status' => 'Approved', 'statusKey' => 'approved',
    ],
    [
        'id' => 'MT-00134', 'title' => 'Software won\'t launch',
        'facility' => 'Lab 1', 'equipment' => 'Computer Unit #07',
        'submitter' => 'Pedro Santos', 'date' => 'Oct 13, 2026',
        'urgency' => 'Low', 'status' => 'Approved', 'statusKey' => 'approved',
    ],
    [
        'id' => 'MT-00135', 'title' => 'Flickering lights',
        'facility' => 'Covered Court', 'equipment' => 'Light Fixture A1',
        'submitter' => 'Ana Reyes', 'date' => 'Oct 12, 2026',
        'urgency' => 'Low', 'status' => 'Rejected', 'statusKey' => 'rejected',
    ],
];

/* =========================================================
   FACILITIES & EQUIPMENT DATA
========================================================= */
$facilities = [
    [
        'id' => 'AC-L1-02', 'name' => 'Aircon Unit 02', 'category' => 'Air Conditioning',
        'location' => 'Lab 1', 'status' => 'Active', 'statusKey' => 'active',
        'condition' => 'Good', 'lastServiced' => 'Sept 8, 2026',
        'serial' => 'AC-2022-0042', 'age' => '2 years old',
        'relatedRequest' => 'MT-00132',
    ],
    [
        'id' => 'PC-L1-01', 'name' => 'Computer Unit #01', 'category' => 'Computer',
        'location' => 'Lab 1', 'status' => 'Needs Repair', 'statusKey' => 'repair',
        'condition' => 'Needs Repair', 'lastServiced' => 'Aug 25, 2026',
        'serial' => 'PC-2023-0117', 'age' => '3 years old',
        'relatedRequest' => 'MT-00131',
    ],
    [
        'id' => 'PR-RO-01', 'name' => 'Printer', 'category' => 'Office Equipment',
        'location' => 'Records Office', 'status' => 'Active', 'statusKey' => 'active',
        'condition' => 'Good', 'lastServiced' => 'July 15, 2026',
        'serial' => 'PR-2024-0011', 'age' => '1 year old',
        'relatedRequest' => null,
    ],
    [
        'id' => 'NW-L2-02', 'name' => 'Switch #2', 'category' => 'Networking',
        'location' => 'Lab 2', 'status' => 'Needs Repair', 'statusKey' => 'repair',
        'condition' => 'Faulty', 'lastServiced' => 'Sept 2, 2026',
        'serial' => 'NW-2023-0008', 'age' => '2 years old',
        'relatedRequest' => 'MT-00133',
    ],
    [
        'id' => 'FD-AO-01', 'name' => 'Water Dispenser', 'category' => 'Facility',
        'location' => 'Admin Office', 'status' => 'Active', 'statusKey' => 'active',
        'condition' => 'Good', 'lastServiced' => 'Aug 12, 2026',
        'serial' => 'FD-2024-0003', 'age' => '1 year old',
        'relatedRequest' => null,
    ],
    [
        'id' => 'EL-CC-01', 'name' => 'Light Fixture A1', 'category' => 'Electrical',
        'location' => 'Covered Court', 'status' => 'Needs Repair', 'statusKey' => 'repair',
        'condition' => 'Flickering', 'lastServiced' => 'June 20, 2026',
        'serial' => 'EL-2021-0021', 'age' => '3 years old',
        'relatedRequest' => 'MT-00135',
    ],
];

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

$pending = array_filter($requests, fn($r) => $r['statusKey'] === 'pending');

renderHead('Secretary Dashboard', 'Dashboard.css', 'secretary');

/* Explicit stylesheet link: Secretary/Components/css/Dashboard.css
   (relative to Secretary/Pages/). ?v= forces the browser to reload it. */
$dashCssFile = __DIR__ . '/../Components/css/Dashboard.css';
$dashCssVer  = is_file($dashCssFile) ? filemtime($dashCssFile) : time();
?>
<link rel="stylesheet" href="../Components/css/Dashboard.css?v=<?= (int)$dashCssVer ?>">
<div class="page">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('Secretary Dashboard', 'Approval & Concern Portal · Barangay Gulod', 'secretary'); ?>

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
                <a href="Approvals.php" class="panel-link">View all →</a>
            </div>

            <?php if (empty($pending)): ?>
                <div class="empty-state">
                    <p>No pending submissions. Everything is up to date.</p>
                </div>
            <?php else: ?>
                <div class="approval-list">
                    <?php foreach ($pending as $req): ?>
                        <div class="approval-item" onclick="window.location.href='Approvals.php?id=<?= e($req['id']) ?>&highlight=1'">
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

            <div class="utility-list">
                <?php foreach ($facilities as $f): ?>
                    <div class="utility-item"
                         data-status="<?= e($f['statusKey']) ?>"
                         data-utility='<?= e(json_encode($f)) ?>'
                         data-related-request="<?= e($f['relatedRequest'] ?? '') ?>"
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
                    <span class="detail-cell-label">Serial Number</span>
                    <span class="detail-cell-value" id="facSerial">—</span>
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
       EQUIPMENT CLICK → REDIRECT TO APPROVALS + HIGHLIGHT
    ========================================================= */
    function handleEquipmentClick(el) {
        const requestId = el.dataset.relatedRequest || '';

        if (!requestId) {
            showToast('No matching request for this equipment.');
            return;
        }

        /* Redirect to Approvals with highlight parameter */
        window.location.href = 'Approvals.php?id=' + encodeURIComponent(requestId) + '&highlight=1';
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
        document.getElementById('facLocation').textContent = data.location;
        document.getElementById('facCondition').textContent = data.condition;
        document.getElementById('facAge').textContent = data.age;
        document.getElementById('facSerial').textContent = data.serial;
        document.getElementById('facLastServiced').textContent = data.lastServiced;

        const relatedWrap = document.getElementById('facRelatedWrap');
        const relatedLink = document.getElementById('facRelated');

        if (data.relatedRequest) {
            relatedWrap.style.display = 'block';
            relatedLink.textContent = data.relatedRequest + ' →';
            relatedLink.href = 'Approvals.php?id=' + encodeURIComponent(data.relatedRequest) + '&highlight=1';
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
                    window.location.href = 'Approvals.php';
                } else {
                    window.location.href = 'Approvals.php?status=' + filter;
                }
            });
        });
    });
</script>
<?php renderFoot(); ?>
</html>