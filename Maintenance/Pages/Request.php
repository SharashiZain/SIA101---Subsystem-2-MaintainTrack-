<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$requestInfo = [
    'Request ID'         => 'MT-2026-001',
    'Date Reported'      => 'Sept 10, 2026',
    'Facility'           => 'Barangay Hall',
    'Specific Area'      => '2nd Floor',
    'Affected Equipment' => 'Aircon Unit 02',
    'Category'           => 'Electrical',
    'Reported By'        => 'Pedro Santos',
];

$timeline = [
    ['title' => 'Request Submitted',                 'time' => 'Sept 10, 2026 · 8:15 AM',           'done' => true],
    ['title' => 'Assigned to Maintenance Personnel', 'time' => 'Jose Mar · Sept 10, 2026 · 9:00 AM', 'done' => true],
    ['title' => 'In Progress',                       'time' => 'Sept 10, 2026 · 11:30 AM',          'done' => true],
    ['title' => 'Completed',                         'time' => '',                                  'done' => false],
];

$activityLog = [
    ['title' => 'Task Assigned',                 'desc' => 'Pedro Santos accepted the task · Sept 10, 10:00 AM'],
    ['title' => 'Status Updated to In Progress', 'desc' => 'Initial inspection started · Sept 10, 11:30 AM'],
];

renderHead('Request Details', 'Request.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('Request Details', 'Maintenance Personnel Portal • Barangay Gulod', 'maintenance'); ?>

    <div class="rd-layout">
        <div class="rd-main">
            <section class="panel">
                <div class="rd-title-row">
                    <div>
                        <h2 class="rd-title">Broken Aircon</h2>
                        <span class="rd-sub">Jose Mar · Barangay Gulod</span>
                    </div>
                    <div class="rd-actions">
                        <span class="tag tag-mid" id="requestStatusTag">In Progress</span>
                        <button class="btn-primary" type="button" id="updateMaintenanceBtn">Update Maintenance</button>
                    </div>
                </div>

                <h3 class="section-label">Request Information</h3>
                <div class="form-grid">
                    <?php foreach ($requestInfo as $label => $val): ?>
                        <div class="field">
                            <label><?= e($label) ?></label>
                            <div class="field-value"><?= e($val) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="field field-full">
                    <label>Problem Description</label>
                    <div class="field-value field-textarea">Aircon unit is not cooling properly and making unusual noise. Needs inspection and possible repair.</div>
                </div>
            </section>

            <section class="panel">
                <h3 class="section-label">Maintenance Update</h3>
                <div class="update-post">
                    <div class="update-post-head">
                        <span class="update-author">Pedro Santos</span>
                        <span class="update-date">Sept 10, 2026 · 3:45PM</span>
                    </div>
                    <p class="update-text">Initial diagnostics completed. Filter cleaned and coolant levels checked. Awaiting replacement parts for blower fan assembly.</p>
                    <div class="update-photo">
                        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="10" r="1.6" stroke="currentColor" stroke-width="1.4"/><path d="M4 17l5-5 3 3 4-4 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>
            </section>
        </div>

        <aside class="rd-side">
            <section class="panel">
                <h3 class="section-label">Maintenance Timeline</h3>
                <ul class="timeline" id="maintenanceTimeline">
                    <?php foreach ($timeline as $step): ?>
                        <li class="timeline-item <?= $step['done'] ? 'done' : '' ?>">
                            <span class="dot <?= $step['done'] ? '' : 'dot-empty' ?>"></span>
                            <div>
                                <strong class="<?= $step['done'] ? '' : 'muted' ?>"><?= e($step['title']) ?></strong>
                                <?php if (!empty($step['time'])): ?>
                                    <span><?= e($step['time']) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="panel">
                <h3 class="section-label">Activity Log</h3>
                <ul class="activity-log" id="activityLog">
                    <?php foreach ($activityLog as $log): ?>
                        <li>
                            <strong><?= e($log['title']) ?></strong>
                            <span><?= e($log['desc']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
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
            <label for="updateStatus">Status</label>
            <select id="updateStatus">
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="On Hold">On Hold</option>
            </select>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const updateBtn = document.getElementById('updateMaintenanceBtn');
        const updateModal = document.getElementById('updateModal');
        const updateModalClose = document.getElementById('updateModalClose');
        const updateModalCancel = document.getElementById('updateModalCancel');
        const updateModalSave = document.getElementById('updateModalSave');
        const statusTag = document.getElementById('requestStatusTag');

        function openModal() {
            updateModal.style.display = 'flex';
        }

        function closeModal() {
            updateModal.style.display = 'none';
            document.getElementById('updateNotes').value = '';
        }

        updateBtn.addEventListener('click', openModal);
        updateModalClose.addEventListener('click', closeModal);
        updateModalCancel.addEventListener('click', closeModal);

        updateModalSave.addEventListener('click', function () {
            const status = document.getElementById('updateStatus').value;
            const notes = document.getElementById('updateNotes').value.trim();

            /* Update status tag */
            statusTag.textContent = status;
            statusTag.classList.remove('tag-mid', 'tag-high', 'tag-low');
            if (status === 'Completed') {
                statusTag.classList.add('tag-low');
            } else if (status === 'On Hold') {
                statusTag.classList.add('tag-high');
            } else {
                statusTag.classList.add('tag-mid');
            }

            /* Add to timeline */
            if (status) {
                const timeline = document.getElementById('maintenanceTimeline');
                const li = document.createElement('li');
                li.className = 'timeline-item done';
                li.innerHTML = `
                    <span class="dot"></span>
                    <div>
                        <strong>${status}</strong>
                        <span>${new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })}</span>
                    </div>
                `;
                timeline.appendChild(li);
            }

            /* Add to activity log */
            if (notes || status) {
                const log = document.getElementById('activityLog');
                const li = document.createElement('li');
                li.innerHTML = `
                    <strong>Status Updated to ${status}</strong>
                    <span>${notes || 'No additional notes.'}</span>
                `;
                log.appendChild(li);
            }

            alert('Maintenance updated successfully!');
            closeModal();
        });

        updateModal.addEventListener('click', function (e) {
            if (e.target === updateModal) closeModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    });
</script>
<?php renderFoot(); ?>
</html>