<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$stats = [
    ['label' => 'Total Requests',        'value' => 10, 'note' => 'This Month',      'color' => ''],
    ['label' => 'Pending / Unassigned',  'value' => 5,  'note' => 'All Pending',     'color' => 'orange'],
    ['label' => 'In Progress',           'value' => 3,  'note' => 'Active Requests', 'color' => 'purple'],
    ['label' => 'Completed',             'value' => 2,  'note' => 'This Month',      'color' => 'green'],
];

$secondaryStats = [
    ['label' => 'Avg. Response Time',          'value' => '2h 18m', 'color' => '',       'note' => 'Based on all available requests'],
    ['label' => 'Recurring-Issue Equipment',   'value' => 3,        'color' => 'orange', 'note' => 'units with repeat repairs'],
];

$requests = [
    ['id' => 'MT-00131', 'issue' => "PC won't turn on",       'equipment' => 'Computer Unit #1',        'lab' => 'Lab 1 (01)', 'category' => 'Hardware',   'status' => 'In Progress'],
    ['id' => 'MT-00132', 'issue' => 'Broken aircon',          'equipment' => 'Air Conditioning Unit #2', 'lab' => 'Lab 3 (01)', 'category' => 'Facility',   'status' => 'Assigned'],
    ['id' => 'MT-00133', 'issue' => 'No internet connection', 'equipment' => 'Switch #2',                'lab' => 'Lab 2 (01)', 'category' => 'Network',    'status' => 'Pending'],
    ['id' => 'MT-00134', 'issue' => "Software won't launch",  'equipment' => 'Computer Unit #7',        'lab' => 'Lab 1 (01)', 'category' => 'Software',   'status' => 'Completed'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights',      'equipment' => 'Lab 4 Lights',            'lab' => 'Lab 4 (01)', 'category' => 'Electrical', 'status' => 'Pending'],
];

$activities = [
    ['text' => 'Request MT-0034 completed',          'time' => '9:10 AM'],
    ['text' => 'Request MT-0032 assigned to Maria',  'time' => '8:45 AM'],
    ['text' => 'Request MT-0031 updated',            'time' => '7:45 AM'],
];

renderHead('Administrator Dashboard', 'Dashboard.css');
?>
    <div class="dashboard">

        <?php renderPageHeader(
            'Administrator Dashboard',
            'Maintenance Reporting Portal · Bautista Building, IT Computer Laboratories'
        ); ?>

        <!-- STATISTICS -->
        <section class="stats">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-card">
                    <span><?= e($stat['label']) ?></span>
                    <strong<?= $stat['color'] ? ' class="' . e($stat['color']) . '"' : '' ?>><?= e($stat['value']) ?></strong>
                    <small><?= e($stat['note']) ?></small>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- SECONDARY STATISTICS -->
        <section class="secondary-stats">
            <?php foreach ($secondaryStats as $stat): ?>
                <div class="wide-stat">
                    <div>
                        <span><?= e($stat['label']) ?></span>
                        <strong<?= $stat['color'] ? ' class="' . e($stat['color']) . '"' : '' ?>><?= e($stat['value']) ?></strong>
                    </div>
                    <small><?= e($stat['note']) ?></small>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- MAIN CONTENT -->
        <div class="content-grid">

            <!-- MAINTENANCE REQUESTS -->
            <section class="panel requests-panel">
                <div class="panel-title">
                    <h2>Recent Maintenance Requests</h2>
                </div>

                <div class="toolbar">
                    <input type="text" placeholder="Search requests">
                    <button>Filter</button>
                    <a href="#">Reset</a>
                    <a href="#" class="view-all">View all</a>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ISSUE / DESCRIPTION</th>
                                <th>EQUIPMENT</th>
                                <th>LAB</th>
                                <th>CATEGORY</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?= e($r['id']) ?></td>
                                    <td><b><?= e($r['issue']) ?></b></td>
                                    <td><?= e($r['equipment']) ?></td>
                                    <td><?= e($r['lab']) ?></td>
                                    <td><?= e($r['category']) ?></td>
                                    <td><?= statusBadge($r['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- RECENT ACTIVITIES -->
            <aside class="panel activities-panel">
                <h2>Recent Activities</h2>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity">
                        <b><?= e($activity['text']) ?></b>
                        <small><?= e($activity['time']) ?></small>
                    </div>
                <?php endforeach; ?>
            </aside>

        </div>
    </div>
<?php renderFoot(); ?>
