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
    ['title' => 'Request Submitted',                  'time' => 'Sept 10, 2026 · 8:15 AM',          'done' => true],
    ['title' => 'Assigned to Maintenance Personnel', 'time' => 'Jose Mar · Sept 10, 2026 · 9:00 AM', 'done' => true],
    ['title' => 'In Progress',                        'time' => 'Sept 10, 2026 · 11:30 AM',         'done' => true],
    ['title' => 'Completed',                          'time' => '',                                 'done' => false],
];

$activityLog = [
    ['title' => 'Task Assigned',                 'desc' => 'Pedro Santos accepted the task · Sept 10, 10:00 AM'],
    ['title' => 'Status Updated to In Progress', 'desc' => 'Initial inspection started · Sept 10, 11:30 AM'],
];

renderHead('Request Details', 'Request.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('Request Details', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <div class="rd-layout">
        <div class="rd-main">
            <section class="panel">
                <div class="rd-title-row">
                    <div>
                        <h2 class="rd-title">Broken Aircon</h2>
                        <span class="rd-sub">Jose Mar · Barangay Gulod</span>
                    </div>
                    <div class="rd-actions">
                        <span class="tag tag-mid">In Progress</span>
                        <button class="btn-primary" type="button">Update Maintenance</button>
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
                <ul class="timeline">
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
                <ul class="activity-log">
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
<?php renderFoot(); ?>
</html>