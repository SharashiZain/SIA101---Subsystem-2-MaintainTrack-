<?php
require_once '../../Components/layout.php';

/* ---- Initial staff data (with tasks) ---- */
$staff = [
    [
        'name'      => 'Jones',
        'initials'  => 'J',
        'openTasks' => 3,
        'email'     => 'jones@qcu.edu.ph',
        'role'      => 'IT Support Staff',
        'tasks'     => [
            ['id' => 'MT-00131', 'issue' => "PC won't turn on", 'status' => 'In Progress'],
            ['id' => 'MT-00128', 'issue' => 'Network drop',     'status' => 'In Progress'],
            ['id' => 'MT-00127', 'issue' => 'Broken Aircon',    'status' => 'Assigned'],
        ],
    ],
    [
        'name'      => 'Marco',
        'initials'  => 'M',
        'openTasks' => 2,
        'email'     => 'marco@qcu.edu.ph',
        'role'      => 'IT Support Staff',
        'tasks'     => [
            ['id' => 'MT-00132', 'issue' => 'Broken aircon',   'status' => 'Assigned'],
            ['id' => 'MT-00126', 'issue' => 'Printer offline', 'status' => 'Pending'],
        ],
    ],
    [
        'name'      => 'LeBron',
        'initials'  => 'L',
        'openTasks' => 1,
        'email'     => 'lebron@qcu.edu.ph',
        'role'      => 'IT Support Staff',
        'tasks'     => [
            ['id' => 'MT-00133', 'issue' => 'No internet connection', 'status' => 'Assigned'],
        ],
    ],
];

/* ---- Unassigned requests ---- */
$unassigned = [
    ['id' => 'MT-00133', 'issue' => 'No internet connection'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights'],
];

renderHead('Assignments & Personnel', 'Assignment.css');
?>
<div class="page">

    <div class="one-card">
        <?php renderPageHeader('Assignments & Personnel'); ?>
    <div class="assignment-grid">
    
        <!-- IT SUPPORT STAFF -->
        <section class="assignment-card">
            <h2>IT Support Staff</h2>

            <div class="staff-list" id="staffList">
                <?php foreach ($staff as $index => $member): ?>
                    <div class="staff-item" data-staff-index="<?= $index ?>" data-staff-name="<?= e($member['name']) ?>">
                        <div class="staff-info">
                            <div class="profile-circle"><?= e($member['initials']) ?></div>
                            <div>
                                <strong><?= e($member['name']) ?></strong>
                                <small class="staff-task-count"><?= e(openTasksLabel($member['openTasks'])) ?></small>
                            </div>
                        </div>
                        <button class="workload-button" data-staff-index="<?= $index ?>">View workload</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- UNASSIGNED REQUESTS -->
        <section class="assignment-card">
            <h2>Unassigned Requests</h2>

            <div class="unassigned-list" id="unassignedList">
                <?php foreach ($unassigned as $request): ?>
                    <div class="unassigned-item" data-request-id="<?= e($request['id']) ?>">
                        <div class="unassigned-info">
                            <strong><?= e($request['id']) ?></strong>
                            <small class="unassigned-status"><?= e($request['issue']) ?></small>
                        </div>
                        <button class="assign-button" data-request-id="<?= e($request['id']) ?>" data-request-issue="<?= e($request['issue']) ?>">
                            Assign / Reassign
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
    </div>
</div>

<!-- WORKLOAD MODAL -->
<div class="static-modal" id="workloadModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3 id="workloadModalTitle">Staff Workload</h3>
            <button type="button" class="modal-close" id="workloadModalClose">✕</button>
        </div>
        <div class="modal-body">
            <p class="modal-subtitle" id="workloadModalEmail">—</p>
            <p class="modal-role" id="workloadModalRole">—</p>
            <h4 class="modal-section-title">Assigned Tasks</h4>
            <ul class="workload-task-list" id="workloadTaskList"></ul>
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="workloadModalCloseBtn">Close</button>
        </div>
    </div>
</div>

<!-- ASSIGN MODAL -->
<div class="static-modal" id="assignModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3>Assign Request</h3>
            <button type="button" class="modal-close" id="assignModalClose">✕</button>
        </div>
        <div class="modal-body">
            <p class="modal-subtitle">
                Request <strong id="assignModalRequestId">—</strong>
            </p>
            <p class="modal-role" id="assignModalIssue">—</p>

            <div class="modal-field">
                <label for="assignStaffSelect">Assign to</label>
                <select id="assignStaffSelect">
                    <option value="">Select staff member</option>
                    <?php foreach ($staff as $member): ?>
                        <option value="<?= e($member['name']) ?>"><?= e($member['name']) ?> (<?= e($member['openTasks']) ?> open tasks)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-field">
                <label for="assignNotes">Notes (optional)</label>
                <textarea id="assignNotes" placeholder="Add any notes for the assigned staff..."></textarea>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="assignModalCancel">Cancel</button>
            <button type="button" class="modal-save" id="assignModalConfirm">Confirm Assignment</button>
        </div>
    </div>
</div>

<!-- SYSTEM MODAL (validation + success) -->
<div class="system-modal-overlay" id="systemModal" role="dialog" aria-modal="true" style="display: none;">
    <div class="system-modal">
        <div class="system-modal-icon" id="systemModalIcon"></div>
        <h3 class="system-modal-title" id="systemModalTitle">Title</h3>
        <p class="system-modal-text" id="systemModalText">Message</p>
        <div class="system-modal-actions" id="systemModalActions"></div>
    </div>
</div>

<script>
    /* ==========================================
       SYSTEM MODAL HELPER
       ========================================== */
    window.SystemModal = (function () {
        const overlay = document.getElementById('systemModal');
        const iconBox = document.getElementById('systemModalIcon');
        const titleEl = document.getElementById('systemModalTitle');
        const textEl = document.getElementById('systemModalText');
        const actionsEl = document.getElementById('systemModalActions');

        const icons = {
            question: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            warning:  '<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            success:  '<svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            danger:   '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
        };

        function open(options) {
            const { icon = 'question', title = '', text = '', confirmText = 'OK', cancelText = null, confirmClass = 'system-btn-confirm', onConfirm = () => {}, onCancel = () => {} } = options;

            iconBox.className = 'system-modal-icon icon-' + icon;
            iconBox.innerHTML = icons[icon] || icons.question;
            titleEl.textContent = title;
            textEl.textContent = text;
            actionsEl.innerHTML = '';

            if (cancelText) {
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'system-btn system-btn-cancel';
                cancelBtn.textContent = cancelText;
                cancelBtn.addEventListener('click', function () { close(); onCancel(); });
                actionsEl.appendChild(cancelBtn);
            }

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'system-btn ' + confirmClass;
            confirmBtn.textContent = confirmText;
            confirmBtn.addEventListener('click', function () { close(); onConfirm(); });
            actionsEl.appendChild(confirmBtn);

            overlay.style.display = 'flex';
        }

        function close() { overlay.style.display = 'none'; }

        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display === 'flex') close();
        });

        return { open, close };
    })();

    /* ==========================================
       ASSIGNMENT PAGE LOGIC
       ========================================== */
    document.addEventListener('DOMContentLoaded', function () {

        /* ---- Live staff data (mutable) ---- */
        const staffData = <?= json_encode($staff) ?>;

        /* ==========================================
           UPDATE STAFF COUNT IN THE LIST
           ========================================== */
        function updateStaffCount(index) {
            const item = document.querySelector(`.staff-item[data-staff-index="${index}"]`);
            if (!item) return;

            const countEl = item.querySelector('.staff-task-count');
            if (!countEl) return;

            const count = staffData[index].tasks.length;
            countEl.textContent = count === 1 ? '1 open task' : count + ' open tasks';
        }

        /* ==========================================
           UPDATE STAFF DROPDOWN OPTIONS
           ========================================== */
        function updateDropdownOptions() {
            const select = document.getElementById('assignStaffSelect');
            const currentValue = select.value;

            select.innerHTML = '<option value="">Select staff member</option>';

            staffData.forEach(function (member) {
                const option = document.createElement('option');
                option.value = member.name;
                option.textContent = member.name + ' (' + member.tasks.length + ' open tasks)';
                select.appendChild(option);
            });

            select.value = currentValue;
        }

        /* ==========================================
           WORKLOAD MODAL
           ========================================== */
        const workloadModal = document.getElementById('workloadModal');
        const workloadModalTitle = document.getElementById('workloadModalTitle');
        const workloadModalEmail = document.getElementById('workloadModalEmail');
        const workloadModalRole = document.getElementById('workloadModalRole');
        const workloadTaskList = document.getElementById('workloadTaskList');

        function openWorkloadModal(index) {
            const member = staffData[index];
            if (!member) return;

            workloadModalTitle.textContent = member.name + "'s Workload";
            workloadModalEmail.textContent = member.email;
            workloadModalRole.textContent = member.role + ' · ' + member.tasks.length + ' open tasks';

            workloadTaskList.innerHTML = '';

            if (member.tasks.length === 0) {
                const li = document.createElement('li');
                li.className = 'workload-empty';
                li.textContent = 'No assigned tasks.';
                workloadTaskList.appendChild(li);
            } else {
                member.tasks.forEach(function (task) {
                    const li = document.createElement('li');
                    li.className = 'workload-task';
                    li.innerHTML = `
                        <div class="workload-task-info">
                            <strong>${task.id}</strong>
                            <span>${task.issue}</span>
                        </div>
                        <span class="workload-task-status status-${task.status.toLowerCase().replace(/\s+/g, '-')}">${task.status}</span>
                    `;
                    workloadTaskList.appendChild(li);
                });
            }

            workloadModal.style.display = 'flex';
        }

        function closeWorkloadModal() { workloadModal.style.display = 'none'; }

        document.querySelectorAll('.workload-button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const index = parseInt(this.dataset.staffIndex, 10);
                openWorkloadModal(index);
            });
        });

        document.getElementById('workloadModalClose').addEventListener('click', closeWorkloadModal);
        document.getElementById('workloadModalCloseBtn').addEventListener('click', closeWorkloadModal);

        /* ==========================================
           ASSIGN MODAL
           ========================================== */
        const assignModal = document.getElementById('assignModal');
        const assignModalRequestId = document.getElementById('assignModalRequestId');
        const assignModalIssue = document.getElementById('assignModalIssue');
        const assignStaffSelect = document.getElementById('assignStaffSelect');
        const assignNotes = document.getElementById('assignNotes');
        let currentRequestId = null;
        let currentRequestIssue = null;
        let currentRequestElement = null;

        function openAssignModal(requestId, issue, element) {
            currentRequestId = requestId;
            currentRequestIssue = issue;
            currentRequestElement = element;
            assignModalRequestId.textContent = requestId;
            assignModalIssue.textContent = issue;
            assignStaffSelect.value = '';
            assignNotes.value = '';
            assignModal.style.display = 'flex';
        }

        function closeAssignModal() {
            assignModal.style.display = 'none';
            currentRequestId = null;
            currentRequestIssue = null;
            currentRequestElement = null;
        }

        document.querySelectorAll('.assign-button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const requestId = this.dataset.requestId;
                const issue = this.dataset.requestIssue;
                const item = this.closest('.unassigned-item');
                openAssignModal(requestId, issue, item);
            });
        });

        document.getElementById('assignModalClose').addEventListener('click', closeAssignModal);
        document.getElementById('assignModalCancel').addEventListener('click', closeAssignModal);

        /* ==========================================
           CONFIRM ASSIGNMENT — updates data + UI
           ========================================== */
        document.getElementById('assignModalConfirm').addEventListener('click', function () {
            const staffName = assignStaffSelect.value;

            if (!staffName) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No staff selected',
                    text: 'Please choose a staff member to assign this request to.',
                    confirmText: 'OK'
                });
                return;
            }

            /* ---- 1. Remove the task from any previous owner ---- */
            staffData.forEach(function (member, idx) {
                const before = member.tasks.length;
                member.tasks = member.tasks.filter(function (t) {
                    return t.id !== currentRequestId;
                });
                if (member.tasks.length !== before) {
                    updateStaffCount(idx);
                }
            });

            /* ---- 2. Add the task to the new owner ---- */
            const targetIndex = staffData.findIndex(function (m) {
                return m.name === staffName;
            });

            if (targetIndex !== -1) {
                staffData[targetIndex].tasks.push({
                    id: currentRequestId,
                    issue: currentRequestIssue,
                    status: 'Assigned'
                });
                updateStaffCount(targetIndex);
            }

            /* ---- 3. Update the unassigned item ---- */
            if (currentRequestElement) {
                const info = currentRequestElement.querySelector('.unassigned-info');
                const button = currentRequestElement.querySelector('.assign-button');

                if (info) {
                    info.innerHTML = `
                        <strong>${currentRequestId}</strong>
                        <small class="unassigned-status">Assigned to <b>${staffName}</b></small>
                    `;
                }

                if (button) {
                    button.textContent = 'Reassign';
                    button.classList.add('reassigned');
                }
            }

            /* ---- 4. Refresh the dropdown so counts stay accurate ---- */
            updateDropdownOptions();

            /* ---- 5. Show success modal ---- */
            const notes = assignNotes.value.trim();
            closeAssignModal();

            SystemModal.open({
                icon: 'success',
                title: 'Request assigned',
                text: `Request ${currentRequestId} has been assigned to ${staffName}.${notes ? ' Notes added.' : ''}`,
                confirmText: 'OK'
            });
        });

        /* ==========================================
           CLICK STAFF ITEM → OPEN WORKLOAD
           ========================================== */
        document.querySelectorAll('.staff-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                if (e.target.closest('.workload-button')) return;
                const index = parseInt(this.dataset.staffIndex, 10);
                openWorkloadModal(index);
            });
        });

        /* ==========================================
           CLOSE MODALS ON OUTSIDE CLICK
           ========================================== */
        [workloadModal, assignModal].forEach(function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) modal.style.display = 'none';
            });
        });

        /* ==========================================
           ESCAPE KEY CLOSES MODALS
           ========================================== */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                workloadModal.style.display = 'none';
                assignModal.style.display = 'none';
            }
        });
    });
</script>
<?php renderFoot(); ?>
</html>