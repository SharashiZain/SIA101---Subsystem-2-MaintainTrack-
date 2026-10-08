<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$stats = [
    ['label' => 'Total Requests',        'value' => 10, 'note' => 'This Month',      'color' => '',        'filter' => ''],
    ['label' => 'Pending / Unassigned',  'value' => 5,  'note' => 'All Pending',     'color' => 'orange',  'filter' => 'pending'],
    ['label' => 'In Progress',           'value' => 3,  'note' => 'Active Requests', 'color' => 'purple',  'filter' => 'in-progress'],
    ['label' => 'Completed',             'value' => 2,  'note' => 'This Month',      'color' => 'green',   'filter' => 'completed'],
];

$secondaryStats = [
    ['label' => 'Avg. Response Time',          'value' => '2h 18m', 'color' => '',       'note' => 'Based on all available requests'],
    ['label' => 'Recurring-Issue Equipment',   'value' => 3,        'color' => 'orange', 'note' => 'units with repeat repairs'],
];

$requests = [
    ['id' => 'MT-00131', 'issue' => "PC won't turn on",       'equipment' => 'Computer Unit #1',        'lab' => 'Lab 1 (01)', 'category' => 'Hardware',   'status' => 'In Progress', 'statusKey' => 'in-progress'],
    ['id' => 'MT-00132', 'issue' => 'Broken aircon',          'equipment' => 'Air Conditioning Unit #2', 'lab' => 'Lab 3 (01)', 'category' => 'Facility',   'status' => 'Assigned',    'statusKey' => 'assigned'],
    ['id' => 'MT-00133', 'issue' => 'No internet connection', 'equipment' => 'Switch #2',                'lab' => 'Lab 2 (01)', 'category' => 'Network',    'status' => 'Pending',     'statusKey' => 'pending'],
    ['id' => 'MT-00134', 'issue' => "Software won't launch",  'equipment' => 'Computer Unit #7',        'lab' => 'Lab 1 (01)', 'category' => 'Software',   'status' => 'Completed',   'statusKey' => 'completed'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights',      'equipment' => 'Lab 4 Lights',            'lab' => 'Lab 4 (01)', 'category' => 'Electrical', 'status' => 'Pending',     'statusKey' => 'pending'],
];

$activities = [
    ['text' => 'Request MT-0034 completed',          'time' => '9:10 AM', 'id' => 'MT-0034'],
    ['text' => 'Request MT-0032 assigned to Maria',  'time' => '8:45 AM', 'id' => 'MT-0032'],
    ['text' => 'Request MT-0031 updated',            'time' => '7:45 AM', 'id' => 'MT-0031'],
];

renderHead('Administrator Dashboard', 'Dashboard.css');
?>
    <div class="dashboard">

        <?php renderPageHeader(
            'Administrator Dashboard',
            'Maintenance Reporting Portal · Bautista Building, IT Computer Laboratories'
        ); ?>

        <!-- STATISTICS (Clickable) -->
        <section class="stats">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-card" data-filter="<?= e($stat['filter']) ?>" style="cursor: pointer;">
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
                    <input type="text" placeholder="Search requests" id="dashboardSearch">
                    <button id="dashboardFilterBtn">Filter</button>
                    <a href="#" id="dashboardReset">Reset</a>
                    <a href="Request.php" class="view-all">View all</a>
                </div>

                <!-- FILTER PANEL (hidden by default) -->
                <div class="filter-panel" id="dashboardFilterPanel" style="display: none;">
                    <select id="dashboardStatusFilter">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="assigned">Assigned</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                    <select id="dashboardCategoryFilter">
                        <option value="">All Categories</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Software">Software</option>
                        <option value="Network">Network</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Facility">Facility</option>
                    </select>
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
                        <tbody id="dashboardTableBody">
                            <?php foreach ($requests as $r): ?>
                                <tr data-status="<?= e($r['statusKey']) ?>" data-category="<?= e($r['category']) ?>" onclick="window.location.href='Request.php?id=<?= e($r['id']) ?>'" style="cursor: pointer;">
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

                <!-- NO RESULTS MESSAGE -->
                <div id="dashboardNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
                    <p style="font-size: 15px; font-weight: 600;">No requests found matching your search.</p>
                </div>
            </section>

            <!-- RECENT ACTIVITIES -->
            <aside class="panel activities-panel">
                <h2>Recent Activities</h2>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity" onclick="window.location.href='Request.php?id=<?= e($activity['id']) ?>'" style="cursor: pointer;">
                        <b><?= e($activity['text']) ?></b>
                        <small><?= e($activity['time']) ?></small>
                    </div>
                <?php endforeach; ?>
            </aside>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('dashboardSearch');
            const filterBtn = document.getElementById('dashboardFilterBtn');
            const filterPanel = document.getElementById('dashboardFilterPanel');
            const statusFilter = document.getElementById('dashboardStatusFilter');
            const categoryFilter = document.getElementById('dashboardCategoryFilter');
            const resetBtn = document.getElementById('dashboardReset');
            const noResults = document.getElementById('dashboardNoResults');
            const rows = document.querySelectorAll('#dashboardTableBody tr');

            /* ---- Filter function ---- */
            function applyFilters() {
                const query = searchInput.value.toLowerCase();
                const status = statusFilter ? statusFilter.value : '';
                const category = categoryFilter ? categoryFilter.value : '';
                let visibleCount = 0;

                rows.forEach(function (row) {
                    const text = row.textContent.toLowerCase();
                    const rowStatus = row.dataset.status || '';
                    const rowCategory = row.dataset.category || '';

                    const matchesSearch = text.includes(query);
                    const matchesStatus = !status || rowStatus === status;
                    const matchesCategory = !category || rowCategory === category;

                    if (matchesSearch && matchesStatus && matchesCategory) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }

            /* ---- Live search ---- */
            searchInput.addEventListener('input', applyFilters);

            /* ---- Toggle filter panel ---- */
            filterBtn.addEventListener('click', function () {
                filterPanel.style.display = filterPanel.style.display === 'none' ? 'flex' : 'none';
            });

            /* ---- Filter changes ---- */
            if (statusFilter) statusFilter.addEventListener('change', applyFilters);
            if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);

            /* ---- Reset ---- */
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                searchInput.value = '';
                if (statusFilter) statusFilter.value = '';
                if (categoryFilter) categoryFilter.value = '';
                filterPanel.style.display = 'none';
                applyFilters();
            });

            /* ---- Clickable stat cards ---- */
            document.querySelectorAll('.stat-card[data-filter]').forEach(function (card) {
                card.addEventListener('click', function () {
                    const filter = this.dataset.filter;
                    if (statusFilter) {
                        statusFilter.value = filter;
                        filterPanel.style.display = 'flex';
                        applyFilters();
                        // Scroll to table
                        document.querySelector('.requests-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        });
    </script>
<?php renderFoot(); ?>