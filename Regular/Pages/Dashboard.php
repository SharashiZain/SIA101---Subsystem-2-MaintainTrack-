<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$stats = [
    [
        'title'       => 'MY REQUESTS',
        'count'       => 5,
        'description' => 'All submitted requests',
        'colorClass'  => '',
        'filter'      => '',
    ],
    [
        'title'       => 'PENDING',
        'count'       => 1,
        'description' => 'Waiting for assignment',
        'colorClass'  => 'pending-number',
        'filter'      => 'pending',
    ],
    [
        'title'       => 'IN PROGRESS',
        'count'       => 1,
        'description' => 'Currently being handled',
        'colorClass'  => 'progress-number',
        'filter'      => 'progress',
    ],
    [
        'title'       => 'COMPLETED',
        'count'       => 3,
        'description' => 'Successfully resolved',
        'colorClass'  => 'completed-number',
        'filter'      => 'complete',
    ],
];

$recentRequests = [
    [
        'title'       => 'Broken Aircon',
        'status'      => 'IN PROGRESS',
        'statusClass' => 'in-progress',
        'info'        => 'Reported Sep 10, 2026 • Assigned to Pedro Santos',
        'ref'         => 'MT-00124 • Barangay Hall – 2nd Floor',
        'id'          => 'MT-00124',
    ],
    [
        'title'       => 'Printer Not Working',
        'status'      => 'PENDING',
        'statusClass' => 'pending',
        'info'        => 'Reported Sep 9, 2026 • Waiting for assignment',
        'ref'         => 'MT-00122 • Barangay Hall – Records Office',
        'id'          => 'MT-00122',
    ],
    [
        'title'       => 'Broken Light',
        'status'      => 'COMPLETED',
        'statusClass' => 'completed',
        'info'        => 'Reported Sep 7, 2026 • Completed Sep 8, 2026',
        'ref'         => 'MT-00118 • Covered Court',
        'id'          => 'MT-00118',
    ],
];

$snapshots = [
    [
        'title'     => 'MT-00124 • Broken Aircon',
        'info'      => 'In Progress • Assigned to Pedro Santos',
        'progress'  => 78,
        'fillClass' => '',
        'id'        => 'MT-00124',
    ],
    [
        'title'     => 'MT-00122 • Printer Not Working',
        'info'      => 'Pending • Waiting for assignment',
        'progress'  => 25,
        'fillClass' => 'pending-progress',
        'id'        => 'MT-00122',
    ],
    [
        'title'     => 'MT-00118 • Broken Light',
        'info'      => 'Completed • Repair finished',
        'progress'  => 100,
        'fillClass' => 'completed-progress',
        'id'        => 'MT-00118',
    ],
];

$howToSteps = [
    ['num' => 1, 'title' => 'Identify the concern',                'desc' => 'Observe the facility or equipment that needs maintenance.'],
    ['num' => 2, 'title' => 'Click "Report a Maintenance Concern"', 'desc' => 'Start a new maintenance request from the dashboard.'],
    ['num' => 3, 'title' => 'Select the facility and equipment',   'desc' => 'Choose the affected facility, area, and equipment.'],
    ['num' => 4, 'title' => 'Describe the problem',                'desc' => 'Provide a clear description of the maintenance concern.'],
    ['num' => 5, 'title' => 'Submit the request',                  'desc' => 'Review the details and submit your maintenance request.'],
    ['num' => 6, 'title' => 'Track the request',                   'desc' => 'Monitor the status and maintenance updates through your dashboard.'],
];

renderHead('My Dashboard', 'reg_user_dashboard.css', 'regular');
?>
<div class="page">

    <?php renderPageHeader('My Dashboard', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <section class="welcome-card">
        <div class="welcome-content">
            <h2>Good Morning, Juan!</h2>
            <p>May maintenance concern? Report it here and track its progress.</p>
            <button type="button" class="report-button" onclick="window.location.href='Report.php'">
                <span>+</span>
                Report a Maintenance Concern
            </button>
        </div>
    </section>

    <section class="stats-grid">
        <?php foreach ($stats as $item): ?>
            <div class="stat-card" data-filter="<?= e($item['filter']) ?>" style="cursor: pointer;">
                <div class="stat-content">
                    <span class="stat-title"><?= e($item['title']) ?></span>
                    <strong class="stat-number <?= e($item['colorClass']) ?>"><?= e($item['count']) ?></strong>
                    <span class="stat-description"><?= e($item['description']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="dashboard-grid">

        <div class="dashboard-panel recent-panel">
            <div class="panel-header">
                <h2>My Recent Requests</h2>
                <span class="total-count"><?= count($recentRequests) ?> total</span>
            </div>

            <div class="request-list">
                <?php foreach ($recentRequests as $req): ?>
                    <div class="request-card" data-id="<?= e($req['id']) ?>" onclick="window.location.href='Request.php?id=<?= e($req['id']) ?>'">
                        <div class="request-top">
                            <strong><?= e($req['title']) ?></strong>
                            <span class="status <?= e($req['statusClass']) ?>"><?= e($req['status']) ?></span>
                        </div>
                        <p><?= e($req['info']) ?></p>
                        <small><?= e($req['ref']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dashboard-panel snapshot-panel">
            <div class="panel-header">
                <h2>Request Status Snapshot</h2>
            </div>

            <div class="snapshot-list">
                <?php foreach ($snapshots as $snap): ?>
                    <div class="snapshot-card" data-id="<?= e($snap['id']) ?>" onclick="window.location.href='Request.php?id=<?= e($snap['id']) ?>'" style="cursor: pointer;">
                        <div class="snapshot-title"><?= e($snap['title']) ?></div>
                        <div class="snapshot-info"><?= e($snap['info']) ?></div>
                        <div class="progress-track">
                            <div class="progress-fill <?= e($snap['fillClass']) ?>" style="width: <?= (int)$snap['progress'] ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dashboard-panel instructions-panel">
            <div class="panel-header">
                <h2>How to Report a Maintenance Concern</h2>
            </div>

            <div class="instruction-list">
                <?php foreach ($howToSteps as $step): ?>
                    <div class="instruction-step">
                        <div class="step-number"><?= (int)$step['num'] ?></div>
                        <div class="step-content">
                            <strong><?= e($step['title']) ?></strong>
                            <p><?= e($step['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </section>

</div>
<?php renderFoot(); ?>
</html>