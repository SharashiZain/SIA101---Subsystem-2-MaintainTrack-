<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$filters = ['Status', 'Category', 'Lab', 'Date range'];

$requests = [
    ['id' => 'MT-00131', 'issue' => "PC won't turn on",       'equipment' => 'Computer Unit #1',         'location' => 'Lab 1 (01)', 'category' => 'Hardware',   'status' => 'In Progress', 'assignedTo' => 'Juan Dela Cruz', 'date' => 'Sept 19, 2026 9:30 AM'],
    ['id' => 'MT-00132', 'issue' => 'Broken aircon',          'equipment' => 'Air Conditioning Unit #3', 'location' => 'Lab 3 (01)', 'category' => 'Facility',   'status' => 'Assigned',    'assignedTo' => 'Maria Santos',   'date' => 'Sept 19, 2026 8:45 AM'],
    ['id' => 'MT-00133', 'issue' => 'No internet connection', 'equipment' => 'Switch #2',                 'location' => 'Lab 2 (01)', 'category' => 'Network',    'status' => 'Pending',     'assignedTo' => 'Unassigned',     'date' => 'Sept 19, 2026 8:30 AM'],
    ['id' => 'MT-00134', 'issue' => "Software won't launch",  'equipment' => 'Computer Unit #7',         'location' => 'Lab 1 (01)', 'category' => 'Software',   'status' => 'Completed',   'assignedTo' => 'Carlo Reyes',    'date' => 'Sept 18, 2026 4:10 PM'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights',      'equipment' => 'Lab 4 Lights',             'location' => 'Lab 4 (01)', 'category' => 'Electrical', 'status' => 'Pending',     'assignedTo' => 'Unassigned',     'date' => 'Sept 18, 2026 2:25 PM'],
];

renderHead('Maintenance Requests', 'Request.css');
?>
<div class="page">

    <?php renderPageHeader('Maintenance Requests'); ?>

    <section class="request-panel">

        <div class="filter-bar">
            <input type="text" placeholder="Search ID, issue, equipment">

            <?php foreach ($filters as $filter): ?>
                <button><?= e($filter) ?></button>
            <?php endforeach; ?>

            <button class="new-request">+ New Request</button>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ISSUE / DESCRIPTION</th>
                        <th>EQUIPMENT</th>
                        <th>LOCATION</th>
                        <th>CATEGORY</th>
                        <th>STATUS</th>
                        <th>ASSIGNED TO</th>
                        <th>DATE REPORTED</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?= e($r['id']) ?></td>
                            <td><b><?= e($r['issue']) ?></b></td>
                            <td><?= e($r['equipment']) ?></td>
                            <td><?= e($r['location']) ?></td>
                            <td><?= e($r['category']) ?></td>
                            <td><?= statusBadge($r['status']) ?></td>
                            <td><?= e($r['assignedTo']) ?></td>
                            <td><?= e($r['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty-lines">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div></div>
            <?php endfor; ?>
        </div>

        <div class="table-footer">
            <span>1–5 of 12 requests</span>

            <div class="pagination">
                <button>‹</button>
                <button class="active">1</button>
                <button>2</button>
                <button>›</button>
            </div>
        </div>

    </section>
</div>
<?php renderFoot(); ?>
