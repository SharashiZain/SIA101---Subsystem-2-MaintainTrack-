<?php
require_once '../../Components/layout.php';

/* ---- Data ---- */
/* ---- Counts computed from the request list (from reference) ---- */
$counts = ['total' => 0, 'pending' => 0, 'assigned' => 0, 'in-progress' => 0, 'completed' => 0];

$requests = [
    ['id' => 'MT-00131', 'issue' => "PC won't turn on",       'equipment' => 'Computer Unit #1',        'lab' => 'Lab 1', 'status' => 'In Progress', 'statusKey' => 'in-progress', 'assignedTo' => 'Juan D.'],
    ['id' => 'MT-00132', 'issue' => 'Broken aircon',          'equipment' => 'Air Conditioning Unit #2', 'lab' => 'Lab 3', 'status' => 'Assigned',    'statusKey' => 'assigned',    'assignedTo' => 'Maria S.'],
    ['id' => 'MT-00133', 'issue' => 'No internet connection', 'equipment' => 'Switch #2',                'lab' => 'Lab 2', 'status' => 'Pending',     'statusKey' => 'pending',     'assignedTo' => '—'],
    ['id' => 'MT-00134', 'issue' => "Software won't launch",  'equipment' => 'Computer Unit #7',        'lab' => 'Lab 1', 'status' => 'Completed',   'statusKey' => 'completed',   'assignedTo' => 'Carlo R.'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights',      'equipment' => 'Lab 4 Lights',            'lab' => 'Lab 4', 'status' => 'Pending',     'statusKey' => 'pending',     'assignedTo' => '—'],
];

$counts['total'] = count($requests);
foreach ($requests as $r) {
    if (isset($counts[$r['statusKey']])) $counts[$r['statusKey']]++;
}

$stats = [
    ['label' => 'Total',       'value' => $counts['total'],       'color' => '',       'filter' => ''],
    ['label' => 'Pending',     'value' => $counts['pending'],     'color' => 'orange', 'filter' => 'pending'],
    ['label' => 'Assigned',    'value' => $counts['assigned'],    'color' => 'blue',   'filter' => 'assigned'],
    ['label' => 'In Progress', 'value' => $counts['in-progress'], 'color' => 'purple', 'filter' => 'in-progress'],
    ['label' => 'Completed',   'value' => $counts['completed'],   'color' => 'green',  'filter' => 'completed'],
];

$activities = [
    ['text' => 'MT-0034 completed',         'time' => '9:10 AM'],
    ['text' => 'MT-0032 assigned to Maria', 'time' => '8:45 AM'],
    ['text' => 'MT-0031 updated',           'time' => '7:45 AM'],
];

$metrics = [
    ['label' => 'Avg. Response',    'value' => '2h 18m'],
    ['label' => 'Avg. Resolution',  'value' => '1d 4h'],
    ['label' => 'Resolution Rate',  'value' => '92%'],
    ['label' => 'Recurring Equip.', 'value' => '3', 'warn' => true],
];

renderHead('Administrator Dashboard', 'Dashboard.css');
?>
<div class="dashboard">

    

    <div class="dash-card">
        <?php renderPageHeader('Administrator Dashboard', 'Maintenance Reporting Portal · Barangay Gulod'); ?>
         
        <!-- HEADER: TITLE + STATS + LINK -->
        <div class="card-head">
            <h2>Recent Maintenance Requests</h2>

            <div class="stats-row">
                <?php foreach ($stats as $stat): ?>
                    <button type="button" class="stat-chip" data-filter="<?= e($stat['filter']) ?>">
                        <span class="stat-label"><?= e($stat['label']) ?></span>
                        <strong class="stat-value<?= $stat['color'] ? ' ' . e($stat['color']) : '' ?>"><?= e($stat['value']) ?></strong>
                    </button>
                <?php endforeach; ?>
            </div>

            <a href="Request.php" class="view-all">View all →</a>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <input type="text" placeholder="Search requests" id="dashboardSearch">
            <select id="dashboardStatus">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="assigned">Assigned</option>
                <option value="in-progress">In Progress</option>
                <option value="completed">Completed</option>
            </select>
            <button type="button" id="dashboardReset">Reset</button>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Issue</th>
                        <th>Lab</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="dashboardTableBody">
                    <?php foreach ($requests as $r): ?>
                        <tr data-status="<?= e($r['statusKey']) ?>"
                            onclick="window.location.href='Request.php?id=<?= e($r['id']) ?>'">
                            <td class="col-id"><?= e($r['id']) ?></td>
                            <td>
                                <b><?= e($r['issue']) ?></b>
                                <small><?= e($r['equipment']) ?></small>
                            </td>
                            <td><?= e($r['lab']) ?></td>
                            <td><?= e($r['assignedTo']) ?></td>
                            <td><?= statusBadge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="dashboardEmpty" style="display: none;">
            <p>No requests found matching your filters.</p>
        </div>

        <!-- FOOTER: METRICS + ACTIVITY -->
        <div class="card-foot">
            <div class="foot-col">
                <h3>Metrics</h3>
                <div class="metric-list">
                    <?php foreach ($metrics as $m): ?>
                        <div class="metric-item">
                            <span class="metric-label"><?= e($m['label']) ?></span>
                            <span class="metric-value<?= !empty($m['warn']) ? ' warn' : '' ?>"><?= e($m['value']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="foot-col">
                <h3>Recent Activity</h3>
                <ul class="activity-list">
                    <?php foreach ($activities as $a): ?>
                        <li>
                            <b><?= e($a['text']) ?></b>
                            <small><?= e($a['time']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('dashboardSearch');
        const status = document.getElementById('dashboardStatus');
        const reset  = document.getElementById('dashboardReset');
        const rows   = document.querySelectorAll('#dashboardTableBody tr');
        const empty  = document.getElementById('dashboardEmpty');

        function apply() {
            const q = search.value.toLowerCase();
            const s = status.value;
            let visible = 0;

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const rowStatus = row.dataset.status || '';
                const show = (!q || text.includes(q)) && (!s || rowStatus === s);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            empty.style.display = visible === 0 ? 'block' : 'none';
        }

        search.addEventListener('input', apply);
        status.addEventListener('change', apply);
        reset.addEventListener('click', function () {
            search.value = '';
            status.value = '';
            apply();
        });

        document.querySelectorAll('.stat-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                status.value = this.dataset.filter;
                apply();
                document.querySelector('.dash-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    });
</script>
<?php renderFoot(); ?>