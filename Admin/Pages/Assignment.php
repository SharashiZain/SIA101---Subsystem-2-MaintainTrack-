<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
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
            ['id' => 'MT-00132', 'issue' => 'Broken aircon', 'status' => 'Assigned'],
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
            ['id' => 'MT-00125', 'issue' => 'Software update', 'status' => 'Pending'],
        ],
    ],
];

$unassigned = [
    ['id' => 'MT-00133', 'issue' => 'No internet connection'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights'],
];

renderHead('Assignments & Personnel', 'Assignment.css');
?>
<div class="page">

    <?php renderPageHeader('Assignments & Personnel'); ?>

    <div class="assignment-grid">

        <!-- IT SUPPORT STAFF -->
        <section class="assignment-card">
            <h2>IT Support Staff</h2>

            <div class="staff-list">
                <?php foreach ($staff as $index => $member): ?>
                    <div class="staff-item" data-staff-index="<?= $index ?>">
                        <div class="staff-info">
                            <div class="profile-circle"><?= e($member['initials']) ?></div>
                            <div>
                                <strong><?= e($member['name']) ?></strong>
                                <small><?= e(openTasksLabel($member['openTasks'])) ?></small>
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

            <div class="unassigned-list">
                <?php foreach ($unassigned as $request): ?>
                    <div class="unassigned-item" data-request-id="<?= e($request['id']) ?>">
                        <div>
                            <strong><?= e($request['id']) ?></strong>
                            <small><?= e($request['issue']) ?></small>
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
            <ul class="workload-task-list" id="workloadTaskList">
                <!-- Populated by JavaScript -->
            </ul>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {

        /* ---- Staff data for workload modal ---- */
        const staffData = <?= json_encode($staff) ?>;

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
            workloadModalRole.textContent = member.role + ' · ' + member.openTasks + ' open tasks';

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

        function closeWorkloadModal() {
            workloadModal.style.display = 'none';
        }

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
        let currentRequestElement = null;

        function openAssignModal(requestId, issue, element) {
            currentRequestId = requestId;
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

        document.getElementById('assignModalConfirm').addEventListener('click', function () {
            const staff = assignStaffSelect.value;

            if (!staff) {
                alert('Please select a staff member.');
                return;
            }

            // Update the unassigned item to show as assigned
            if (currentRequestElement) {
                const info = currentRequestElement.querySelector('div');
                const button = currentRequestElement.querySelector('.assign-button');

                info.innerHTML = `
                    <strong>${currentRequestId}</strong>
                    <small>Assigned to <b>${staff}</b></small>
                `;

                if (button) {
                    button.textContent = 'Reassign';
                    button.classList.add('reassigned');
                }
            }

            alert('Request ' + currentRequestId + ' assigned to ' + staff + '.');
            closeAssignModal();
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
                workloadModal.style.display = 'none';
                assignModal.style.display = 'none';
            }
        });
    });
</script>
<?php renderFoot(); ?>