<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$requests = [
    ['id' => 'MT-00131', 'issue' => "PC won't turn on",       'equipment' => 'Computer Unit #1',         'location' => 'Lab 1 (01)', 'category' => 'Hardware',   'status' => 'In Progress', 'statusKey' => 'in-progress', 'assignedTo' => 'Juan Dela Cruz', 'date' => 'Sept 19, 2026 9:30 AM'],
    ['id' => 'MT-00132', 'issue' => 'Broken aircon',          'equipment' => 'Air Conditioning Unit #3', 'location' => 'Lab 3 (01)', 'category' => 'Facility',   'status' => 'Assigned',    'statusKey' => 'assigned',    'assignedTo' => 'Maria Santos',   'date' => 'Sept 19, 2026 8:45 AM'],
    ['id' => 'MT-00133', 'issue' => 'No internet connection', 'equipment' => 'Switch #2',                 'location' => 'Lab 2 (01)', 'category' => 'Network',    'status' => 'Pending',     'statusKey' => 'pending',     'assignedTo' => 'Unassigned',     'date' => 'Sept 19, 2026 8:30 AM'],
    ['id' => 'MT-00134', 'issue' => "Software won't launch",  'equipment' => 'Computer Unit #7',         'location' => 'Lab 1 (01)', 'category' => 'Software',   'status' => 'Completed',   'statusKey' => 'completed',   'assignedTo' => 'Carlo Reyes',    'date' => 'Sept 18, 2026 4:10 PM'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights',      'equipment' => 'Lab 4 Lights',             'location' => 'Lab 4 (01)', 'category' => 'Electrical', 'status' => 'Pending',     'statusKey' => 'pending',     'assignedTo' => 'Unassigned',     'date' => 'Sept 18, 2026 2:25 PM'],
];

renderHead('Maintenance Requests', 'Request.css');
?>
<div class="page">

    <?php renderPageHeader('Maintenance Requests'); ?>

    <section class="request-panel">

        <!-- SIMPLIFIED FILTER BAR -->
        <div class="filter-bar">
            <input type="text" placeholder="Search ID, issue, equipment" id="requestSearch">

            <select id="requestStatusFilter">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="assigned">Assigned</option>
                <option value="in-progress">In Progress</option>
                <option value="completed">Completed</option>
            </select>

            <select id="requestCategoryFilter">
                <option value="">All Categories</option>
                <option value="Hardware">Hardware</option>
                <option value="Software">Software</option>
                <option value="Network">Network</option>
                <option value="Electrical">Electrical</option>
                <option value="Facility">Facility</option>
            </select>

            <select id="requestLabFilter">
                <option value="">All Labs</option>
                <option value="Lab 1 (01)">Lab 1 (01)</option>
                <option value="Lab 2 (01)">Lab 2 (01)</option>
                <option value="Lab 3 (01)">Lab 3 (01)</option>
                <option value="Lab 4 (01)">Lab 4 (01)</option>
            </select>

            <button type="button" id="requestReset">Reset</button>

            <button class="new-request" type="button" id="newRequestBtn">+ New Request</button>
        </div>

        <!-- TABLE -->
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
                <tbody id="requestTableBody">
                    <?php foreach ($requests as $r): ?>
                        <tr data-status="<?= e($r['statusKey']) ?>" 
                            data-category="<?= e($r['category']) ?>" 
                            data-location="<?= e($r['location']) ?>" 
                            onclick="window.location.href='Request.php?id=<?= e($r['id']) ?>'" 
                            style="cursor: pointer;">
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

        <!-- NO RESULTS MESSAGE -->
        <div id="requestNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
            <p style="font-size: 15px; font-weight: 600;">No requests found matching your filters.</p>
        </div>

        <!-- PAGINATION -->
        <div class="table-footer">
            <span id="requestCount"><?= count($requests) ?> requests shown</span>

            <div class="pagination">
                <button type="button" class="page-btn" data-page="prev">‹</button>
                <button type="button" class="page-btn active" data-page="1">1</button>
                <button type="button" class="page-btn" data-page="2">2</button>
                <button type="button" class="page-btn" data-page="next">›</button>
            </div>
        </div>

    </section>
</div>

<!-- NEW REQUEST MODAL -->
<div class="static-modal" id="newRequestModal" style="display: none;">
    <div class="static-modal-content">
        <h3>New Maintenance Request</h3>

        <div class="modal-field">
            <label for="newIssue">Issue / Description</label>
            <input type="text" id="newIssue" placeholder="e.g. PC won't turn on">
        </div>

        <div class="modal-field">
            <label for="newEquipment">Equipment</label>
            <input type="text" id="newEquipment" placeholder="e.g. Computer Unit #1">
        </div>

        <div class="modal-field">
            <label for="newLocation">Location</label>
            <select id="newLocation">
                <option value="">Select location</option>
                <option value="Lab 1 (01)">Lab 1 (01)</option>
                <option value="Lab 2 (01)">Lab 2 (01)</option>
                <option value="Lab 3 (01)">Lab 3 (01)</option>
                <option value="Lab 4 (01)">Lab 4 (01)</option>
            </select>
        </div>

        <div class="modal-field">
            <label for="newCategory">Category</label>
            <select id="newCategory">
                <option value="">Select category</option>
                <option value="Hardware">Hardware</option>
                <option value="Software">Software</option>
                <option value="Network">Network</option>
                <option value="Electrical">Electrical</option>
                <option value="Facility">Facility</option>
            </select>
        </div>

        <div class="modal-field">
            <label for="newAssignedTo">Assigned To</label>
            <select id="newAssignedTo">
                <option value="Unassigned">Unassigned</option>
                <option value="Juan Dela Cruz">Juan Dela Cruz</option>
                <option value="Maria Santos">Maria Santos</option>
                <option value="Carlo Reyes">Carlo Reyes</option>
            </select>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="newRequestCancel">Cancel</button>
            <button type="button" class="modal-save" id="newRequestSave">Create Request</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('requestSearch');
        const statusFilter = document.getElementById('requestStatusFilter');
        const categoryFilter = document.getElementById('requestCategoryFilter');
        const labFilter = document.getElementById('requestLabFilter');
        const resetBtn = document.getElementById('requestReset');
        const noResults = document.getElementById('requestNoResults');
        const requestCount = document.getElementById('requestCount');
        const rows = document.querySelectorAll('#requestTableBody tr');

        /* ---- Filter function ---- */
        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const status = statusFilter.value;
            const category = categoryFilter.value;
            const lab = labFilter.value;
            let visibleCount = 0;

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const rowStatus = row.dataset.status || '';
                const rowCategory = row.dataset.category || '';
                const rowLocation = row.dataset.location || '';

                const matchesSearch = text.includes(query);
                const matchesStatus = !status || rowStatus === status;
                const matchesCategory = !category || rowCategory === category;
                const matchesLab = !lab || rowLocation === lab;

                if (matchesSearch && matchesStatus && matchesCategory && matchesLab) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }

            if (requestCount) {
                requestCount.textContent = visibleCount + ' request' + (visibleCount !== 1 ? 's' : '') + ' shown';
            }
        }

        /* ---- Live search ---- */
        searchInput.addEventListener('input', applyFilters);

        /* ---- Filter dropdowns ---- */
        statusFilter.addEventListener('change', applyFilters);
        categoryFilter.addEventListener('change', applyFilters);
        labFilter.addEventListener('change', applyFilters);

        /* ---- Reset ---- */
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            statusFilter.value = '';
            categoryFilter.value = '';
            labFilter.value = '';
            applyFilters();
        });

        /* ---- New Request Modal ---- */
        const newRequestBtn = document.getElementById('newRequestBtn');
        const newRequestModal = document.getElementById('newRequestModal');
        const newRequestCancel = document.getElementById('newRequestCancel');
        const newRequestSave = document.getElementById('newRequestSave');

        newRequestBtn.addEventListener('click', function () {
            newRequestModal.style.display = 'flex';
        });

        newRequestCancel.addEventListener('click', function () {
            newRequestModal.style.display = 'none';
        });

        newRequestSave.addEventListener('click', function () {
            const issue = document.getElementById('newIssue').value.trim();
            const equipment = document.getElementById('newEquipment').value.trim();
            const location = document.getElementById('newLocation').value;
            const category = document.getElementById('newCategory').value;
            const assignedTo = document.getElementById('newAssignedTo').value;

            if (!issue || !equipment || !location || !category) {
                alert('Please fill in all required fields.');
                return;
            }

            const tbody = document.getElementById('requestTableBody');
            const newRow = document.createElement('tr');
            newRow.dataset.status = 'pending';
            newRow.dataset.category = category;
            newRow.dataset.location = location;
            newRow.style.cursor = 'pointer';
            newRow.innerHTML = `
                <td>MT-${Math.floor(Math.random() * 90000) + 10000}</td>
                <td><b>${issue}</b></td>
                <td>${equipment}</td>
                <td>${location}</td>
                <td>${category}</td>
                <td><span class="status status-pending">Pending</span></td>
                <td>${assignedTo}</td>
                <td>Just now</td>
            `;
            tbody.insertBefore(newRow, tbody.firstChild);

            newRequestModal.style.display = 'none';
            document.getElementById('newIssue').value = '';
            document.getElementById('newEquipment').value = '';
            document.getElementById('newLocation').value = '';
            document.getElementById('newCategory').value = '';
            document.getElementById('newAssignedTo').value = 'Unassigned';

            alert('New request created successfully!');
        });

        /* ---- Pagination ---- */
        document.querySelectorAll('.page-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (this.dataset.page === 'prev' || this.dataset.page === 'next') return;
                document.querySelectorAll('.page-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    });
</script>
<?php renderFoot(); ?>