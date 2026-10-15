<?php
require_once '../../Components/layout.php';

renderHead('Request Details', 'Request.css', 'maintenance');
?>
<div class="page">

    <!-- ONE CONTAINER: banner + all page content -->
    <div class="one-card">

    <?php renderPageHeader('Request Details', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <div class="rd-layout">
        <div class="rd-main">
            <section class="panel">
                <div class="rd-title-row">
                    <div>
                        <h2 class="rd-title" id="requestTitle">—</h2>
                        <span class="rd-sub" id="requestSub">—</span>
                    </div>
                    <div class="rd-actions">
                        <span class="tag tag-mid" id="requestStatusTag" style="display: none;">—</span>
                        <button class="btn-primary" type="button" id="updateMaintenanceBtn" style="display: none;">Update Maintenance</button>
                    </div>
                </div>

                <h3 class="section-label">Request Information</h3>
                <div class="form-grid" id="requestInfoGrid">
                    <div class="field">
                        <label>Request ID</label>
                        <div class="field-value" id="infoRequestId">—</div>
                    </div>
                    <div class="field">
                        <label>Date Reported</label>
                        <div class="field-value" id="infoDate">—</div>
                    </div>
                    <div class="field">
                        <label>Facility</label>
                        <div class="field-value" id="infoFacility">—</div>
                    </div>
                    <div class="field">
                        <label>Specific Area</label>
                        <div class="field-value" id="infoArea">—</div>
                    </div>
                    <div class="field">
                        <label>Affected Equipment</label>
                        <div class="field-value" id="infoEquipment">—</div>
                    </div>
                    <div class="field">
                        <label>Category</label>
                        <div class="field-value" id="infoCategory">—</div>
                    </div>
                    <div class="field">
                        <label>Reported By</label>
                        <div class="field-value" id="infoReportedBy">—</div>
                    </div>
                </div>
                <div class="field field-full">
                    <label>Problem Description</label>
                    <div class="field-value field-textarea" id="infoDescription">—</div>
                </div>
            </section>

            <section class="panel">
                <h3 class="section-label">Maintenance Update</h3>
                <div class="update-post" id="maintenanceUpdatePost">
                    <p class="update-text" style="color: #687985; font-style: italic; margin: 0;">No maintenance updates yet.</p>
                </div>
            </section>
        </div>

        <aside class="rd-side">
            <section class="panel">
                <h3 class="section-label">Maintenance Timeline</h3>
                <ul class="timeline" id="maintenanceTimeline">
                    <li class="timeline-item" style="color: #687985; font-style: italic; padding-left: 0;">
                        No request selected.
                    </li>
                </ul>
            </section>

            <section class="panel">
                <h3 class="section-label">Activity Log</h3>
                <ul class="activity-log" id="activityLog">
                    <li style="color: #687985; font-style: italic;">
                        No activity yet.
                    </li>
                </ul>
            </section>
        </aside>
    </div>

    </div>

</div>

<!-- UPDATE MAINTENANCE MODAL -->
<div class="static-modal" id="updateModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3>Update Maintenance</h3>
            <button type="button" class="modal-close" id="updateModalClose">✕</button>
        </div>

        <div class="modal-field">
            <label>Status</label>
            <div class="status-radios">
                <label class="status-radio">
                    <input type="radio" name="updateStatus" value="In Progress">
                    <span class="radio-card">
                        <span class="radio-dot radio-progress"></span>
                        <span class="radio-label">In Progress</span>
                    </span>
                </label>
                <label class="status-radio">
                    <input type="radio" name="updateStatus" value="Completed">
                    <span class="radio-card">
                        <span class="radio-dot radio-completed"></span>
                        <span class="radio-label">Completed</span>
                    </span>
                </label>
                <label class="status-radio">
                    <input type="radio" name="updateStatus" value="On Hold">
                    <span class="radio-card">
                        <span class="radio-dot radio-hold"></span>
                        <span class="radio-label">On Hold</span>
                    </span>
                </label>
            </div>
        </div>

        <div class="modal-field">
            <label for="updateNotes">Notes</label>
            <textarea id="updateNotes" placeholder="Add notes about the maintenance work..."></textarea>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="updateModalCancel">Cancel</button>
            <button type="button" class="modal-save" id="updateModalSave">Save Update</button>
        </div>
    </div>
</div>

<!-- SYSTEM MODAL (confirmation + success) -->
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
       REQUEST PAGE LOGIC
       ========================================== */
    document.addEventListener('DOMContentLoaded', function () {

        const requestData = {
            'MT-10101': {
                title: 'Broken Aircon',
                sub: 'Jose Mar · Barangay Gulod',
                date: 'Sept 11, 2026',
                facility: 'Barangay Hall',
                area: '3rd Floor',
                equipment: 'Aircon Unit 03',
                category: 'Facility',
                reportedBy: 'Maria Santos',
                description: 'Aircon unit is making unusual rattling noises and fails to produce cold air. Requires immediate inspection.',
                status: 'Assigned',
                activityLog: [
                    { title: 'Task Assigned', desc: 'Assigned by Admin · Sept 11, 8:45 AM' }
                ]
            },
            'MT-10102': {
                title: 'Aircon Filter Replacement',
                sub: 'Jose Mar · Barangay Gulod',
                date: 'Sept 10, 2026',
                facility: 'Barangay Hall',
                area: '2nd Floor',
                equipment: 'Aircon Unit 02',
                category: 'Facility',
                reportedBy: 'Juan Dela Cruz',
                description: 'Routine filter replacement and coolant level check needed.',
                status: 'In Progress',
                activityLog: [
                    { title: 'Task Assigned', desc: 'Assigned by Admin · Sept 10, 10:30 AM' }
                ]
            },
            'MT-10103': {
                title: 'Vent Cover Replacement',
                sub: 'Jose Mar · Barangay Gulod',
                date: 'Sept 8, 2026',
                facility: 'Barangay Hall',
                area: 'Ground Floor',
                equipment: 'Vent Cover',
                category: 'Facility',
                reportedBy: 'Carlo Reyes',
                description: 'Minor cosmetic damage on vent cover, no operational malfunction reported.',
                status: 'On Hold',
                activityLog: [
                    { title: 'Task Assigned', desc: 'Assigned by Admin · Sept 8, 3:00 PM' }
                ]
            },
            'MT-2026-001': {
                title: 'Broken Aircon',
                sub: 'Jose Mar · Barangay Gulod',
                date: 'Sept 10, 2026',
                facility: 'Barangay Hall',
                area: '2nd Floor',
                equipment: 'Aircon Unit 02',
                category: 'Electrical',
                reportedBy: 'Pedro Santos',
                description: 'Aircon unit is not cooling properly and making unusual noise.',
                status: 'In Progress',
                activityLog: [
                    { title: 'Task Assigned', desc: 'Pedro Santos accepted the task · Sept 10, 10:00 AM' }
                ]
            }
        };

        const params = new URLSearchParams(window.location.search);
        const requestId = params.get('id');
        const mode = params.get('mode');

        const updateBtn = document.getElementById('updateMaintenanceBtn');
        const statusTag = document.getElementById('requestStatusTag');

        if (!requestId || !requestData[requestId]) {
            updateBtn.style.display = 'none';
            return;
        }

        const data = requestData[requestId];

        document.getElementById('requestTitle').textContent = data.title;
        document.getElementById('requestSub').textContent = data.sub;
        document.getElementById('infoRequestId').textContent = requestId;
        document.getElementById('infoDate').textContent = data.date;
        document.getElementById('infoFacility').textContent = data.facility;
        document.getElementById('infoArea').textContent = data.area;
        document.getElementById('infoEquipment').textContent = data.equipment;
        document.getElementById('infoCategory').textContent = data.category;
        document.getElementById('infoReportedBy').textContent = data.reportedBy;
        document.getElementById('infoDescription').textContent = data.description;

        /* Status tag */
        statusTag.textContent = data.status;
        statusTag.style.display = 'inline-flex';
        statusTag.classList.remove('tag-mid', 'tag-high', 'tag-low');
        if (data.status === 'Completed') {
            statusTag.classList.add('tag-low');
        } else if (data.status === 'On Hold') {
            statusTag.classList.add('tag-high');
        } else {
            statusTag.classList.add('tag-mid');
        }

        if (mode !== 'view') {
            updateBtn.style.display = 'inline-flex';
        }

        /* ==========================================
           TIMELINE
           ========================================== */
        function renderTimeline(status) {
            const timeline = document.getElementById('maintenanceTimeline');
            timeline.innerHTML = '';

            const stages = [
                { key: 'submitted',   label: 'Request Submitted',                 time: 'Sept 10, 2026 · 8:15 AM',           state: 'done' },
                { key: 'assigned',    label: 'Assigned to Maintenance Personnel', time: 'Jose Mar · Sept 10, 2026 · 9:00 AM', state: 'done' },
                { key: 'in-progress', label: 'In Progress',                       time: 'Sept 10, 2026 · 11:30 AM',          state: 'done' },
            ];

            if (status === 'On Hold') {
                stages.push({ key: 'on-hold', label: 'On Hold', time: 'Waiting for action', state: 'active' });
            }

            if (status === 'Completed') {
                stages.push({ key: 'completed', label: 'Completed', time: 'Just now', state: 'done' });
            } else {
                stages.push({ key: 'completed', label: 'Completed', time: '', state: 'pending' });
            }

            stages.forEach(function (stage) {
                const li = document.createElement('li');
                const isDone = stage.state === 'done';
                const isActive = stage.state === 'active';
                li.className = 'timeline-item' + (isDone ? ' done' : '') + (isActive ? ' active' : '');

                let dotClass = 'dot dot-empty';
                if (isDone) dotClass = 'dot';
                if (isActive) dotClass = 'dot dot-active';

                const titleClass = (isDone || isActive) ? '' : 'muted';

                li.innerHTML = `
                    <span class="${dotClass}"></span>
                    <div>
                        <strong class="${titleClass}">${stage.label}</strong>
                        ${stage.time ? `<span>${stage.time}</span>` : ''}
                    </div>
                `;
                timeline.appendChild(li);
            });
        }

        renderTimeline(data.status);

        function renderActivityLog(logs) {
            const log = document.getElementById('activityLog');
            log.innerHTML = '';
            logs.forEach(function (entry) {
                const li = document.createElement('li');
                li.innerHTML = `<strong>${entry.title}</strong><span>${entry.desc}</span>`;
                log.appendChild(li);
            });
        }
        renderActivityLog(data.activityLog);

        /* ==========================================
           UPDATE MODAL
           ========================================== */
        const updateModal = document.getElementById('updateModal');
        const updateModalClose = document.getElementById('updateModalClose');
        const updateModalCancel = document.getElementById('updateModalCancel');
        const updateModalSave = document.getElementById('updateModalSave');

        function selectCurrentStatus() {
            const radios = document.querySelectorAll('input[name="updateStatus"]');
            let matched = false;
            radios.forEach(function (radio) {
                if (radio.value === data.status) {
                    radio.checked = true;
                    matched = true;
                } else {
                    radio.checked = false;
                }
            });
            if (!matched) {
                const fallback = document.querySelector('input[name="updateStatus"][value="In Progress"]');
                if (fallback) fallback.checked = true;
            }
        }

        function openModal() {
            selectCurrentStatus();
            updateModal.style.display = 'flex';
        }

        function closeModal() {
            updateModal.style.display = 'none';
            document.getElementById('updateNotes').value = '';
        }

        updateBtn.addEventListener('click', openModal);
        updateModalClose.addEventListener('click', closeModal);
        updateModalCancel.addEventListener('click', closeModal);

        updateModal.addEventListener('click', function (e) {
            if (e.target === updateModal) closeModal();
        });

        /* ==========================================
           SAVE UPDATE — with confirmation modal
           ========================================== */
        updateModalSave.addEventListener('click', function () {
            const statusInput = document.querySelector('input[name="updateStatus"]:checked');
            const status = statusInput ? statusInput.value : 'In Progress';
            const notes = document.getElementById('updateNotes').value.trim();

            /* -- No changes at all → skip -- */
            if (status === data.status && !notes) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'Nothing to update',
                    text: 'The status is unchanged and no notes were added.',
                    confirmText: 'OK'
                });
                return;
            }

            /* -- Confirmation modal -- */
            SystemModal.open({
                icon: 'question',
                title: 'Save this update?',
                text: `Status will be set to "${status}".${notes ? ' Notes will be added.' : ''}`,
                confirmText: 'Yes, save',
                cancelText: 'Cancel',
                confirmClass: 'system-btn-confirm',
                onConfirm: function () {
                    /* Apply the update */
                    data.status = status;

                    statusTag.textContent = status;
                    statusTag.classList.remove('tag-mid', 'tag-high', 'tag-low');
                    if (status === 'Completed') {
                        statusTag.classList.add('tag-low');
                    } else if (status === 'On Hold') {
                        statusTag.classList.add('tag-high');
                    } else {
                        statusTag.classList.add('tag-mid');
                    }

                    renderTimeline(status);

                    const now = new Date().toLocaleString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric',
                        hour: 'numeric', minute: '2-digit'
                    });
                    data.activityLog.push({
                        title: 'Status Updated to ' + status,
                        desc: (notes || 'No additional notes.') + ' · ' + now
                    });
                    renderActivityLog(data.activityLog);

                    closeModal();

                    /* Success confirmation */
                    SystemModal.open({
                        icon: 'success',
                        title: 'Maintenance updated',
                        text: `Status changed to "${status}".`,
                        confirmText: 'OK'
                    });
                }
            });
        });
    });
</script>
<?php renderFoot(); ?>
</html>