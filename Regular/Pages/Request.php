<?php
require_once '../../Components/layout.php';
require_once '../../Components/data.php';

$requestId = $_GET['id'] ?? '';
$requests = getDemoRequests();

/* Find the request by ID */
$data = null;
foreach ($requests as $req) {
    if ($req['id'] === $requestId) {
        $data = $req;
        break;
    }
}

/* Default to first if not found */
if (!$data) {
    $data = $requests[0];
    $requestId = $data['id'];
}

$status = $data['status'];

/* Stepper state */
$steps = [
    ['key' => 'submitted',   'label' => 'Submitted',   'time' => 'Sept 10 · 8:15 AM'],
    ['key' => 'assigned',    'label' => 'Assigned',    'time' => 'Sept 10 · 9:00 AM'],
    ['key' => 'in-progress', 'label' => 'In Progress', 'time' => 'Sept 10 · 11:30 AM'],
    ['key' => 'completed',   'label' => 'Completed',   'time' => ''],
];

$doneCount = 0;
$activeIndex = 0;
$onHold = false;

if ($status === 'Pending') {
    $doneCount = 1; $activeIndex = 1;
} elseif ($status === 'Assigned') {
    $doneCount = 2; $activeIndex = 2;
} elseif ($status === 'In Progress') {
    $doneCount = 3; $activeIndex = 2;
} elseif ($status === 'On Hold') {
    $doneCount = 3; $activeIndex = 2; $onHold = true;
} elseif ($status === 'Completed') {
    $doneCount = 4; $activeIndex = 4;
}

renderHead('Request Details', 'Request.css', 'regular');
?>
<div class="request-page">

    <section class="request-container">
       <?php renderPageHeader('My request', 'Maintenance Reporting Portal · Barangay Gulod', 'regular'); ?>

        <div class="request-body">
        <div class="left-column">
            
            <div class="request-id-card">
                <h2>Request ID</h2>
                <span class="request-id-value">ID#<?= e($requestId) ?></span>
            </div>

            <!-- STATUS STEPPER -->
            <div class="stepper-card">
                <h3 class="stepper-title">Request Progress</h3>
                <ol class="stepper">
                    <?php foreach ($steps as $i => $step): ?>
                        <?php
                        $class = '';
                        $icon = '';
                        if ($onHold && $step['key'] === 'in-progress') {
                            $class = 'hold';
                            $icon = '⏸';
                        } elseif ($i < $doneCount) {
                            $class = 'done';
                            $icon = '✓';
                        } elseif ($i === $activeIndex && $status !== 'Completed') {
                            $class = 'active';
                            $icon = '●';
                        }
                        ?>
                        <li class="step <?= $class ?>">
                            <span class="step-dot"><?= $icon ?></span>
                            <span class="step-label"><?= e($step['label']) ?></span>
                            <span class="step-time"><?= e($step['time'] ?: '—') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($onHold): ?>
                    <div class="stepper-hold-reason">On hold — Waiting for replacement parts.</div>
                <?php endif; ?>
            </div>

            <!-- MAINTENANCE DETAILS -->
            <div class="maintenance-card">
                <div class="maintenance-header">
                    <h2>Maintenance Details</h2>
                </div>
                <div class="details-grid">
                    <div class="detail-box">
                        <span class="detail-label">Concern</span>
                        <span class="detail-value"><?= e($data['title']) ?></span>
                    </div>
                    <div class="detail-box">
                        <span class="detail-label">Category</span>
                        <span class="detail-value"><?= e($data['category']) ?></span>
                    </div>
                    <div class="detail-box">
                        <span class="detail-label">Facility</span>
                        <span class="detail-value"><?= e($data['facility']) ?></span>
                    </div>
                    <div class="detail-box">
                        <span class="detail-label">Equipment</span>
                        <span class="detail-value"><?= e($data['equipment']) ?></span>
                    </div>
                    <div class="detail-box">
                        <span class="detail-label">Specific Area</span>
                        <span class="detail-value"><?= e($data['area']) ?></span>
                    </div>
                    <div class="detail-box">
                        <span class="detail-label">Date Reported</span>
                        <span class="detail-value"><?= e($data['date']) ?></span>
                    </div>
                    <div class="detail-box">
                        <span class="detail-label">Technician</span>
                        <span class="detail-value"><?= e($data['technician']) ?></span>
                    </div>
                    <div class="detail-box">
                        <span class="detail-label">Status</span>
                        <span class="detail-value"><?= e($data['status']) ?></span>
                    </div>
                </div>
                <div class="description-box">
                    <span class="detail-label">Description</span>
                    <span class="detail-value"><?= e($data['description']) ?></span>
                </div>
            </div>

        </div>

        <div class="right-column">
            <div class="right-card">
                <div class="card-title">Attached Photo</div>
                <div class="card-placeholder">
                    <svg viewBox="0 0 24 24" width="34" height="34">
                        <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                        <circle cx="9" cy="9.5" r="1.5"></circle>
                        <path d="M5 18l5-5.5 3 3.5 2.5-3 3.5 5"></path>
                    </svg>
                    <span>No image attached</span>
                </div>
            </div>

            <div class="right-card small">
                <div class="card-title">Updates</div>
                <div class="card-placeholder">
                    <span>No updates yet</span>
                </div>
            </div>
        </div>
    </div>
    </section>

</div>
<?php renderFoot(); ?>
</html>