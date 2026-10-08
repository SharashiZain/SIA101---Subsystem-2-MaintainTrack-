<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$users = [
    [
        'id'         => 'U-001',
        'name'       => 'Admin User',
        'email'      => 'admin.user@qcu.edu.ph',
        'role'       => 'Admin',
        'status'     => 'Active',
        'lastActive' => 'Sept 19, 2026',
        'initials'   => 'A',
    ],
    [
        'id'         => 'U-002',
        'name'       => 'Juan Dela Cruz',
        'email'      => 'juan.delacruz@qcu.edu.ph',
        'role'       => 'IT Support Staff',
        'status'     => 'Active',
        'lastActive' => 'Sept 19, 2026',
        'initials'   => 'J',
    ],
    [
        'id'         => 'U-003',
        'name'       => 'Maria Santos',
        'email'      => 'maria.santos@qcu.edu.ph',
        'role'       => 'IT Support Staff',
        'status'     => 'Active',
        'lastActive' => 'Sept 19, 2026',
        'initials'   => 'M',
    ],
    [
        'id'         => 'U-004',
        'name'       => 'Carlo Reyes',
        'email'      => 'carlo.reyes@qcu.edu.ph',
        'role'       => 'IT Support Staff',
        'status'     => 'Active',
        'lastActive' => 'Sept 18, 2026',
        'initials'   => 'C',
    ],
];

renderHead('User & Role Management', 'Users.css');
?>
<div class="container">

    <?php renderPageHeader('User & Role Management'); ?>

    <main class="card border-highlight">

        <!-- CARD HEADER -->
        <div class="card-header">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" placeholder="Search users by name, email, or role" class="search-bar" id="userSearch">
            </div>

            <select class="user-filter" id="userRoleFilter">
                <option value="">All Roles</option>
                <option value="Admin">Admin</option>
                <option value="IT Support Staff">IT Support Staff</option>
                <option value="Maintenance Personnel">Maintenance Personnel</option>
            </select>

            <select class="user-filter" id="userStatusFilter">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Suspended">Suspended</option>
            </select>

            <button class="reset-btn" type="button" id="userReset">Reset</button>
            <button class="add-btn" type="button" id="addUserBtn">+ Add User</button>
        </div>

        <!-- TABLE -->
        <table class="user-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                <?php foreach ($users as $user): ?>
                    <tr data-role="<?= e($user['role']) ?>" 
                        data-status="<?= e($user['status']) ?>"
                        data-id="<?= e($user['id']) ?>">
                        <td class="font-bold"><?= e($user['name']) ?></td>
                        <td class="text-gray"><?= e($user['email']) ?></td>
                        <td class="text-gray"><?= e($user['role']) ?></td>
                        <td>
                            <?php if ($user['status'] === 'Active'): ?>
                                <span class="status-badge status-active">Active</span>
                            <?php elseif ($user['status'] === 'Inactive'): ?>
                                <span class="status-badge status-inactive">Inactive</span>
                            <?php else: ?>
                                <span class="status-badge status-suspended">Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-gray"><?= e($user['lastActive']) ?></td>
                        <td>
                            <button class="btn-edit" 
                                    data-user-id="<?= e($user['id']) ?>" 
                                    data-user-name="<?= e($user['name']) ?>" 
                                    data-user-email="<?= e($user['email']) ?>" 
                                    data-user-role="<?= e($user['role']) ?>" 
                                    data-user-status="<?= e($user['status']) ?>">
                                Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- NO RESULTS MESSAGE -->
        <div id="userNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
            <p style="font-size: 15px; font-weight: 600;">No users found matching your filters.</p>
        </div>

        <!-- TABLE FOOTER -->
        <div class="table-footer">
            <span id="userCount"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?> shown</span>
        </div>

    </main>

</div>

<!-- ADD USER MODAL -->
<div class="static-modal" id="addUserModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button type="button" class="modal-close" id="addUserClose">✕</button>
        </div>

        <div class="modal-field">
            <label for="newUserName">Full Name</label>
            <input type="text" id="newUserName" placeholder="e.g. Juan Dela Cruz">
        </div>

        <div class="modal-field">
            <label for="newUserEmail">Email Address</label>
            <input type="email" id="newUserEmail" placeholder="e.g. juan@qcu.edu.ph">
        </div>

        <div class="modal-field">
            <label for="newUserRole">Role</label>
            <select id="newUserRole">
                <option value="">Select role</option>
                <option value="Admin">Admin</option>
                <option value="IT Support Staff">IT Support Staff</option>
                <option value="Maintenance Personnel">Maintenance Personnel</option>
            </select>
        </div>

        <div class="modal-field">
            <label for="newUserStatus">Status</label>
            <select id="newUserStatus">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Suspended">Suspended</option>
            </select>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="addUserCancel">Cancel</button>
            <button type="button" class="modal-save" id="addUserSave">Add User</button>
        </div>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="static-modal" id="editUserModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button type="button" class="modal-close" id="editUserClose">✕</button>
        </div>

        <div class="modal-field">
            <label for="editUserName">Full Name</label>
            <input type="text" id="editUserName" placeholder="Full name">
        </div>

        <div class="modal-field">
            <label for="editUserEmail">Email Address</label>
            <input type="email" id="editUserEmail" placeholder="Email address">
        </div>

        <div class="modal-field">
            <label for="editUserRole">Role</label>
            <select id="editUserRole">
                <option value="Admin">Admin</option>
                <option value="IT Support Staff">IT Support Staff</option>
                <option value="Maintenance Personnel">Maintenance Personnel</option>
            </select>
        </div>

        <div class="modal-field">
            <label for="editUserStatus">Status</label>
            <select id="editUserStatus">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Suspended">Suspended</option>
            </select>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="editUserCancel">Cancel</button>
            <button type="button" class="modal-save" id="editUserSave">Save Changes</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const searchInput = document.getElementById('userSearch');
        const roleFilter = document.getElementById('userRoleFilter');
        const statusFilter = document.getElementById('userStatusFilter');
        const resetBtn = document.getElementById('userReset');
        const noResults = document.getElementById('userNoResults');
        const userCount = document.getElementById('userCount');
        const rows = document.querySelectorAll('#userTableBody tr');

        /* ==========================================
           FILTER FUNCTION
           ========================================== */
        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const role = roleFilter.value;
            const status = statusFilter.value;
            let visibleCount = 0;

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const rowRole = row.dataset.role || '';
                const rowStatus = row.dataset.status || '';

                const matchesSearch = text.includes(query);
                const matchesRole = !role || rowRole === role;
                const matchesStatus = !status || rowStatus === status;

                if (matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            userCount.textContent = visibleCount + ' user' + (visibleCount !== 1 ? 's' : '') + ' shown';
        }

        /* ---- Live search ---- */
        searchInput.addEventListener('input', applyFilters);

        /* ---- Dropdown filters ---- */
        roleFilter.addEventListener('change', applyFilters);
        statusFilter.addEventListener('change', applyFilters);

        /* ---- Reset ---- */
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            roleFilter.value = '';
            statusFilter.value = '';
            applyFilters();
        });

        /* ==========================================
           ADD USER MODAL
           ========================================== */
        const addModal = document.getElementById('addUserModal');
        const addBtn = document.getElementById('addUserBtn');
        const addClose = document.getElementById('addUserClose');
        const addCancel = document.getElementById('addUserCancel');
        const addSave = document.getElementById('addUserSave');

        function openAddModal() {
            addModal.style.display = 'flex';
        }

        function closeAddModal() {
            addModal.style.display = 'none';
            document.getElementById('newUserName').value = '';
            document.getElementById('newUserEmail').value = '';
            document.getElementById('newUserRole').value = '';
            document.getElementById('newUserStatus').value = 'Active';
        }

        addBtn.addEventListener('click', openAddModal);
        addClose.addEventListener('click', closeAddModal);
        addCancel.addEventListener('click', closeAddModal);

        addSave.addEventListener('click', function () {
            const name = document.getElementById('newUserName').value.trim();
            const email = document.getElementById('newUserEmail').value.trim();
            const role = document.getElementById('newUserRole').value;
            const status = document.getElementById('newUserStatus').value;

            if (!name || !email || !role) {
                alert('Please fill in all required fields.');
                return;
            }

            const initials = name.split(' ').map(function (n) { return n[0]; }).join('').toUpperCase().slice(0, 2);

            const tbody = document.getElementById('userTableBody');
            const newRow = document.createElement('tr');
            newRow.dataset.role = role;
            newRow.dataset.status = status;
            newRow.dataset.id = 'U-' + Math.floor(Math.random() * 900 + 100);
            newRow.innerHTML = `
                <td class="font-bold">${name}</td>
                <td class="text-gray">${email}</td>
                <td class="text-gray">${role}</td>
                <td><span class="status-badge status-${status.toLowerCase()}">${status}</span></td>
                <td class="text-gray">Just now</td>
                <td><button class="btn-edit" data-user-id="${newRow.dataset.id}" data-user-name="${name}" data-user-email="${email}" data-user-role="${role}" data-user-status="${status}">Edit</button></td>
            `;
            tbody.insertBefore(newRow, tbody.firstChild);

            bindEditButtons();
            applyFilters();

            alert('User ' + name + ' added successfully!');
            closeAddModal();
        });

        /* ==========================================
           EDIT USER MODAL
           ========================================== */
        const editModal = document.getElementById('editUserModal');
        const editClose = document.getElementById('editUserClose');
        const editCancel = document.getElementById('editUserCancel');
        const editSave = document.getElementById('editUserSave');
        let editingRow = null;

        function openEditModal(button) {
            editingRow = button.closest('tr');
            document.getElementById('editUserName').value = button.dataset.userName;
            document.getElementById('editUserEmail').value = button.dataset.userEmail;
            document.getElementById('editUserRole').value = button.dataset.userRole;
            document.getElementById('editUserStatus').value = button.dataset.userStatus;
            editModal.style.display = 'flex';
        }

        function closeEditModal() {
            editModal.style.display = 'none';
            editingRow = null;
        }

        editClose.addEventListener('click', closeEditModal);
        editCancel.addEventListener('click', closeEditModal);

        editSave.addEventListener('click', function () {
            if (!editingRow) return;

            const name = document.getElementById('editUserName').value.trim();
            const email = document.getElementById('editUserEmail').value.trim();
            const role = document.getElementById('editUserRole').value;
            const status = document.getElementById('editUserStatus').value;

            if (!name || !email || !role) {
                alert('Please fill in all required fields.');
                return;
            }

            editingRow.dataset.role = role;
            editingRow.dataset.status = status;

            const cells = editingRow.querySelectorAll('td');
            cells[0].textContent = name;
            cells[1].textContent = email;
            cells[2].textContent = role;
            cells[3].innerHTML = `<span class="status-badge status-${status.toLowerCase()}">${status}</span>`;
            cells[4].textContent = 'Just now';

            const editBtn = editingRow.querySelector('.btn-edit');
            editBtn.dataset.userName = name;
            editBtn.dataset.userEmail = email;
            editBtn.dataset.userRole = role;
            editBtn.dataset.userStatus = status;

            applyFilters();
            alert('User updated successfully!');
            closeEditModal();
        });

        /* ==========================================
           BIND EDIT BUTTONS
           ========================================== */
        function bindEditButtons() {
            document.querySelectorAll('.btn-edit').forEach(function (btn) {
                btn.onclick = function (e) {
                    e.stopPropagation();
                    openEditModal(this);
                };
            });
        }

        bindEditButtons();

        /* ==========================================
           CLOSE MODALS ON OUTSIDE CLICK
           ========================================== */
        [addModal, editModal].forEach(function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        /* ==========================================
           ESCAPE KEY CLOSES MODALS
           ========================================== */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                addModal.style.display = 'none';
                editModal.style.display = 'none';
            }
        });
    });
</script>
<?php renderFoot(); ?>