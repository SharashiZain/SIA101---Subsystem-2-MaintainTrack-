<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$stats = [
    ['title' => 'My Assigned Tasks', 'count' => 10, 'desc' => 'Pending / In-Progress', 'class' => '',          'filter' => ''],
    ['title' => 'Total Requests',    'count' => 10, 'desc' => 'This Month',            'class' => 'total',     'filter' => ''],
    ['title' => 'Completed',         'count' => 5,  'desc' => 'This Month',            'class' => 'completed', 'filter' => 'completed'],
    ['title' => 'Pending',           'count' => 5,  'desc' => 'All Pending requests',  'class' => 'pending',   'filter' => 'pending'],
];

$requests = [
    ['id' => 'MT-00124', 'issue' => "PC Won't turn on", 'equipment' => 'Computer unit #1',         'location' => 'Lab 1 (B1)', 'status' => 'Completed',   'statusKey' => 'completed',   'date' => 'Sept 19, 2026', 'time' => '9:00 AM'],
    ['id' => 'MT-00127', 'issue' => 'Broken Aircon',    'equipment' => 'Air Conditioning unit #3', 'location' => 'Lab 3 (B1)', 'status' => 'Assigned',    'statusKey' => 'assigned',    'date' => 'Sept 16, 2026', 'time' => '8:45 AM'],
    ['id' => 'MT-00128', 'issue' => "PC Won't turn on", 'equipment' => 'Computer unit #1',         'location' => 'Lab 1 (B1)', 'status' => 'In Progress', 'statusKey' => 'in-progress', 'date' => 'Sept 19, 2026', 'time' => '8:00 AM'],
    ['id' => 'MT-00129', 'issue' => 'Network drop',     'equipment' => 'Switch Port #4',           'location' => 'Lab 2 (B1)', 'status' => 'In Progress', 'statusKey' => 'in-progress', 'date' => 'Sept 19, 2026', 'time' => '8:00 AM'],
];

$activities = [
    ['icon' => '✓', 'class' => 'completed-icon', 'title' => 'Request MT-00124 mark as completed.',       'equipment' => 'Computer unit #1',         'time' => '9:00 AM', 'id' => 'MT-00124'],
    ['icon' => '👥', 'class' => 'assigned-icon',  'title' => 'Request MT-00127 assigned to you.',         'equipment' => 'Air Conditioning unit #3', 'time' => '8:45 AM', 'id' => 'MT-00127'],
    ['icon' => '↻', 'class' => 'progress-icon',  'title' => 'Request MT-00128 updated to In-Progress.', 'equipment' => 'Air Conditioning unit #3', 'time' => '7:47 AM', 'id' => 'MT-00128'],
];

renderHead('Maintenance Personnel Dashboard', 'Maintenance.css', 'regular');
?>
<div class="page">

    <?php renderPageHeader('Maintenance Personnel Dashboard', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <!-- STATISTICS (Clickable) -->
    <section class="stats-grid">
        <?php foreach ($stats as $item): ?>
            <div class="stat-card" data-filter="<?= e($item['filter']) ?>" style="cursor: pointer;">
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
                <span class="total-count" id="maintenanceCount"><?= count($requests) ?> shown</span>
            </div>

            <div class="request-tools">
                <div class="search-box">
                    <input type="text" placeholder="Search requests..." id="maintenanceSearch">
                    <span>⌕</span>
                </div>

                <select id="maintenanceStatusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="in-progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>

                <button class="reset-button" type="button" id="maintenanceReset">Reset</button>
                <button class="view-button" type="button" onclick="window.location.href='Task.php'">View all</button>
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
                    <tbody id="maintenanceTableBody">
                        <?php foreach ($requests as $req): ?>
                            <tr data-status="<?= e($req['statusKey']) ?>" 
                                data-id="<?= e($req['id']) ?>"
                                onclick="window.location.href='Request.php?id=<?= e($req['id']) ?>'"
                                style="cursor: pointer;">
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

            <!-- NO RESULTS MESSAGE -->
            <div id="maintenanceNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
                <p style="font-size: 15px; font-weight: 600;">No requests found matching your filters.</p>
            </div>
        </div>

        <!-- RECENT ACTIVITIES -->
        <div class="dashboard-panel activity-panel">
            <div class="panel-header">
                <h2>Recent Activities</h2>
            </div>

            <div class="activity-list">
                <?php foreach ($activities as $act): ?>
                    <div class="activity-item" 
                         data-id="<?= e($act['id']) ?>"
                         onclick="window.location.href='Request.php?id=<?= e($act['id']) ?>'"
                         style="cursor: pointer;">
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('maintenanceSearch');
        const statusFilter = document.getElementById('maintenanceStatusFilter');
        const resetBtn = document.getElementById('maintenanceReset');
        const noResults = document.getElementById('maintenanceNoResults');
        const maintenanceCount = document.getElementById('maintenanceCount');
        const rows = document.querySelectorAll('#maintenanceTableBody tr');

        /* ---- Filter function ---- */
        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const status = statusFilter.value;
            let visibleCount = 0;

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const rowStatus = row.dataset.status || '';

                const matchesSearch = text.includes(query);
                const matchesStatus = !status || rowStatus === status;

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            maintenanceCount.textContent = visibleCount + ' request' + (visibleCount !== 1 ? 's' : '') + ' shown';
        }

        /* ---- Live search ---- */
        searchInput.addEventListener('input', applyFilters);

        /* ---- Status filter ---- */
        statusFilter.addEventListener('change', applyFilters);

        /* ---- Reset ---- */
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            statusFilter.value = '';
            applyFilters();
        });

        /* ---- Clickable stat cards ---- */
        document.querySelectorAll('.stat-card[data-filter]').forEach(function (card) {
            card.addEventListener('click', function () {
                const filter = this.dataset.filter;
                statusFilter.value = filter;
                applyFilters();
                document.querySelector('.requests-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    });
</script>
<?php renderFoot(); ?>
</html>