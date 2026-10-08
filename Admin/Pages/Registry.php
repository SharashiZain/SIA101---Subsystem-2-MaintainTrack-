<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$equipment = [
    [
        'id'        => 'PC-L1-01',
        'type'      => 'Computer Unit',
        'lab'       => 'Lab 1 (B1)',
        'status'    => 'Active',
        'repairs'   => 4,
        'recurring' => true,
        'brand'     => 'Dell OptiPlex 3080',
        'acquired'  => 'Aug 12, 2022',
    ],
    [
        'id'        => 'AC-L3-02',
        'type'      => 'Air Conditioning',
        'lab'       => 'Lab 3 (B1)',
        'status'    => 'Active',
        'repairs'   => 2,
        'recurring' => false,
        'brand'     => 'Carrier 2.0 HP',
        'acquired'  => 'Jan 5, 2023',
    ],
    [
        'id'        => 'Router-L2-01',
        'type'      => 'Networking',
        'lab'       => 'Lab 2 (B1)',
        'status'    => 'Active',
        'repairs'   => 1,
        'recurring' => false,
        'brand'     => 'Cisco RV340',
        'acquired'  => 'Mar 20, 2024',
    ],
    [
        'id'        => 'PC-L1-07',
        'type'      => 'Computer Unit',
        'lab'       => 'Lab 1 (B1)',
        'status'    => 'Active',
        'repairs'   => 0,
        'recurring' => false,
        'brand'     => 'HP ProDesk 400',
        'acquired'  => 'Jun 1, 2024',
    ],
];

renderHead('Equipment Registry', 'Registry.css');
?>
<div class="container">

    <?php renderPageHeader('Equipment Registry'); ?>

    <main class="card">
        <div class="card-header">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" placeholder="Search equipment ID, type, or lab" class="search-bar" id="registrySearch">
            </div>

            <select class="registry-filter" id="registryTypeFilter">
                <option value="">All Types</option>
                <option value="Computer Unit">Computer Unit</option>
                <option value="Air Conditioning">Air Conditioning</option>
                <option value="Networking">Networking</option>
            </select>

            <select class="registry-filter" id="registryLabFilter">
                <option value="">All Labs</option>
                <option value="Lab 1 (B1)">Lab 1 (B1)</option>
                <option value="Lab 2 (B1)">Lab 2 (B1)</option>
                <option value="Lab 3 (B1)">Lab 3 (B1)</option>
            </select>

            <button class="reset-btn" type="button" id="registryReset">Reset</button>
            <button class="add-btn" type="button" id="addEquipmentBtn">+ Add Equipment</button>
        </div>

        <table class="equipment-table">
            <thead>
                <tr>
                    <th>Equipment ID</th>
                    <th>Type</th>
                    <th>Lab</th>
                    <th>Status</th>
                    <th>Total Repairs</th>
                    <th>Recurring</th>
                </tr>
            </thead>
            <tbody id="equipmentTableBody">
                <?php foreach ($equipment as $item): ?>
                    <tr data-type="<?= e($item['type']) ?>" 
                        data-lab="<?= e($item['lab']) ?>" 
                        data-recurring="<?= $item['recurring'] ? 'true' : 'false' ?>"
                        data-id="<?= e($item['id']) ?>"
                        onclick="window.location.href='Registry.php?id=<?= e($item['id']) ?>'"
                        style="cursor: pointer;">
                        <td class="font-bold"><?= e($item['id']) ?></td>
                        <td class="font-bold"><?= e($item['type']) ?></td>
                        <td class="text-gray"><?= e($item['lab']) ?></td>
                        <td class="text-gray"><?= e($item['status']) ?></td>
                        <td class="text-gray"><?= e($item['repairs']) ?></td>
                        <?php if ($item['recurring']): ?>
                            <td><span class="badge badge-recurring">Recurring</span></td>
                        <?php else: ?>
                            <td class="text-gray">—</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- NO RESULTS MESSAGE -->
        <div id="registryNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
            <p style="font-size: 15px; font-weight: 600;">No equipment found matching your filters.</p>
        </div>

        <div class="table-footer">
            <span id="registryCount"><?= count($equipment) ?> items shown</span>
        </div>
    </main>

</div>

<!-- ADD EQUIPMENT MODAL -->
<div class="static-modal" id="addEquipmentModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3>Add Equipment</h3>
            <button type="button" class="modal-close" id="addEquipmentClose">✕</button>
        </div>

        <div class="modal-field">
            <label for="newEquipId">Equipment ID</label>
            <input type="text" id="newEquipId" placeholder="e.g. PC-L2-05">
        </div>

        <div class="modal-field">
            <label for="newEquipType">Type</label>
            <select id="newEquipType">
                <option value="">Select type</option>
                <option value="Computer Unit">Computer Unit</option>
                <option value="Air Conditioning">Air Conditioning</option>
                <option value="Networking">Networking</option>
            </select>
        </div>

        <div class="modal-field">
            <label for="newEquipLab">Lab</label>
            <select id="newEquipLab">
                <option value="">Select lab</option>
                <option value="Lab 1 (B1)">Lab 1 (B1)</option>
                <option value="Lab 2 (B1)">Lab 2 (B1)</option>
                <option value="Lab 3 (B1)">Lab 3 (B1)</option>
            </select>
        </div>

        <div class="modal-field">
            <label for="newEquipStatus">Status</label>
            <select id="newEquipStatus">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Under Repair">Under Repair</option>
            </select>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="addEquipmentCancel">Cancel</button>
            <button type="button" class="modal-save" id="addEquipmentSave">Add Equipment</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const searchInput = document.getElementById('registrySearch');
        const typeFilter = document.getElementById('registryTypeFilter');
        const labFilter = document.getElementById('registryLabFilter');
        const resetBtn = document.getElementById('registryReset');
        const noResults = document.getElementById('registryNoResults');
        const registryCount = document.getElementById('registryCount');
        const rows = document.querySelectorAll('#equipmentTableBody tr');

        /* ==========================================
           FILTER FUNCTION
           ========================================== */
        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const type = typeFilter.value;
            const lab = labFilter.value;
            let visibleCount = 0;

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const rowType = row.dataset.type || '';
                const rowLab = row.dataset.lab || '';

                const matchesSearch = text.includes(query);
                const matchesType = !type || rowType === type;
                const matchesLab = !lab || rowLab === lab;

                if (matchesSearch && matchesType && matchesLab) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            registryCount.textContent = visibleCount + ' item' + (visibleCount !== 1 ? 's' : '') + ' shown';
        }

        /* ---- Live search ---- */
        searchInput.addEventListener('input', applyFilters);

        /* ---- Dropdown filters ---- */
        typeFilter.addEventListener('change', applyFilters);
        labFilter.addEventListener('change', applyFilters);

        /* ---- Reset ---- */
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            typeFilter.value = '';
            labFilter.value = '';
            applyFilters();
        });

        /* ==========================================
           ADD EQUIPMENT MODAL
           ========================================== */
        const addModal = document.getElementById('addEquipmentModal');
        const addBtn = document.getElementById('addEquipmentBtn');
        const addClose = document.getElementById('addEquipmentClose');
        const addCancel = document.getElementById('addEquipmentCancel');
        const addSave = document.getElementById('addEquipmentSave');

        function openModal() {
            addModal.style.display = 'flex';
        }

        function closeModal() {
            addModal.style.display = 'none';
            document.getElementById('newEquipId').value = '';
            document.getElementById('newEquipType').value = '';
            document.getElementById('newEquipLab').value = '';
            document.getElementById('newEquipStatus').value = 'Active';
        }

        addBtn.addEventListener('click', openModal);
        addClose.addEventListener('click', closeModal);
        addCancel.addEventListener('click', closeModal);

        addSave.addEventListener('click', function () {
            const id = document.getElementById('newEquipId').value.trim();
            const type = document.getElementById('newEquipType').value;
            const lab = document.getElementById('newEquipLab').value;
            const status = document.getElementById('newEquipStatus').value;

            if (!id || !type || !lab) {
                alert('Please fill in all required fields.');
                return;
            }

            const tbody = document.getElementById('equipmentTableBody');
            const newRow = document.createElement('tr');
            newRow.dataset.type = type;
            newRow.dataset.lab = lab;
            newRow.dataset.recurring = 'false';
            newRow.dataset.id = id;
            newRow.style.cursor = 'pointer';
            newRow.innerHTML = `
                <td class="font-bold">${id}</td>
                <td class="font-bold">${type}</td>
                <td class="text-gray">${lab}</td>
                <td class="text-gray">${status}</td>
                <td class="text-gray">0</td>
                <td class="text-gray">—</td>
            `;
            tbody.insertBefore(newRow, tbody.firstChild);

            // Re-bind filter to include the new row
            applyFilters();

            alert('Equipment ' + id + ' added successfully!');
            closeModal();
        });

        /* ==========================================
           CLOSE MODAL ON OUTSIDE CLICK
           ========================================== */
        addModal.addEventListener('click', function (e) {
            if (e.target === addModal) closeModal();
        });

        /* ==========================================
           ESCAPE KEY CLOSES MODAL
           ========================================== */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    });
</script>
<?php renderFoot(); ?>