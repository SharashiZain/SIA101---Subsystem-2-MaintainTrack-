<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$stats = [
    ['title' => 'My Assigned Tasks', 'count' => 10, 'desc' => 'Pending / In-Progress', 'class' => ''],
    ['title' => 'Total Requests',    'count' => 10, 'desc' => 'This Month',            'class' => 'total'],
    ['title' => 'Completed',         'count' => 5,  'desc' => 'This Month',            'class' => 'completed'],
    ['title' => 'Pending',           'count' => 5,  'desc' => 'All Pending requests',  'class' => 'pending'],
];

$requests = [
    ['id' => 'MT-00124', 'issue' => "PC Won't turn on", 'equipment' => 'Computer unit #1',         'location' => 'Lab 1 (B1)', 'status' => 'Completed',   'date' => 'Sept 19, 2026', 'time' => '9:00 AM'],
    ['id' => 'MT-00127', 'issue' => 'Broken Aircon',    'equipment' => 'Air Conditioning unit #3', 'location' => 'Lab 3 (B1)', 'status' => 'Assigned',    'date' => 'Sept 16, 2026', 'time' => '8:45 AM'],
    ['id' => 'MT-00128', 'issue' => "PC Won't turn on", 'equipment' => 'Computer unit #1',         'location' => 'Lab 1 (B1)', 'status' => 'In Progress', 'date' => 'Sept 19, 2026', 'time' => '8:00 AM'],
    ['id' => 'MT-00129', 'issue' => 'Network drop',     'equipment' => 'Switch Port #4',           'location' => 'Lab 2 (B1)', 'status' => 'In Progress', 'date' => 'Sept 19, 2026', 'time' => '8:00 AM'],
];

$activities = [
    ['icon' => '✓', 'class' => 'completed-icon', 'title' => 'Request MT-00124 mark as completed.',       'equipment' => 'Computer unit #1',         'time' => '9:00 AM'],
    ['icon' => '👥', 'class' => 'assigned-icon',  'title' => 'Request MT-00127 assigned to you.',         'equipment' => 'Air Conditioning unit #3', 'time' => '8:45 AM'],
    ['icon' => '↻', 'class' => 'progress-icon',  'title' => 'Request MT-00128 updated to In-Progress.', 'equipment' => 'Air Conditioning unit #3', 'time' => '7:47 AM'],
];

renderHead('Maintenance Personnel Dashboard', 'Maintenance.css', 'regular');
?>
<div class="page">

    <?php renderPageHeader('Maintenance Personnel Dashboard', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <!-- STATISTICS -->
    <section class="stats-grid">
        <?php foreach ($stats as $item): ?>
            <div class="stat-card">
                <span class="stat-title"><?= e($item['title']) ?></span>
                <strong class="stat-number <?= e($item['class']) ?>"><?= (int)$item['count'] ?></strong>
                <span class="stat-description"><?= e($item['desc']) ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- LOWER CONTENT -->
    <section class="dashboard-grid">

        <!-- RECENT REQUESTS -->
        <div class="dashboard-panel requests-panel">
            <div class="panel-header">
                <h2>Recent Maintenance Requests</h2>
            </div>

            <div class="request-tools">
                <div class="search-box">
                    <input type="text" placeholder="Search requests...">
                    <span>⌕</span>
                </div>

                <select>
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>

                <button class="reset-button" type="button">Reset</button>
                <button class="view-button" type="button">View all</button>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ISSUE / DESCRIPTION</th>
                            <th>LOCATION</th>
                            <th>STATUS</th>
                            <th>DATE REPORTED</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><b><?= e($req['id']) ?></b></td>
                                <td>
                                    <?= e($req['issue']) ?>
                                    <small><?= e($req['equipment']) ?></small>
                                </td>
                                <td><?= e($req['location']) ?></td>
                                <td><?= statusBadge($req['status']) ?></td>
                                <td>
                                    <?= e($req['date']) ?>
                                    <small><?= e($req['time']) ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RECENT ACTIVITIES -->
        <div class="dashboard-panel activity-panel">
            <div class="panel-header">
                <h2>Recent Activities</h2>
            </div>

            <div class="activity-list">
                <?php foreach ($activities as $act): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= e($act['class']) ?>">
                            <?= e($act['icon']) ?>
                        </div>
                        <div class="activity-content">
                            <strong><?= e($act['title']) ?></strong>
                            <span><?= e($act['equipment']) ?></span>
                        </div>
                        <time><?= e($act['time']) ?></time>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </section>

</div>
<?php renderFoot(); ?>
</html>