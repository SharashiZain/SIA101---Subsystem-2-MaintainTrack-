<?php
require_once '../../Components/layout.php';

/* ---- Data ---- */
$tasks = [
    ['id' => 'MT-10101', 'title' => 'Broken Aircon',              'location' => 'Barangay Hall · 3rd Floor', 'priority' => 'High',   'tagClass' => 'tag-high', 'status' => 'Assigned',    'when' => 'Assigned 2h ago',    'estimate' => 'Est. 1–2 hrs'],
    ['id' => 'MT-10102', 'title' => 'Aircon Filter Replacement',  'location' => 'Barangay Hall · 2nd Floor', 'priority' => 'Medium', 'tagClass' => 'tag-mid',  'status' => 'In Progress', 'when' => 'Started 30m ago',    'estimate' => 'Est. 30 min'],
    ['id' => 'MT-10103', 'title' => 'Vent Cover Replacement',     'location' => 'Barangay Hall · Ground',    'priority' => 'Low',    'tagClass' => 'tag-low',  'status' => 'On Hold',     'when' => 'Waiting for parts',  'estimate' => '3 days on hold'],
];

/* Stat chips (counted from the task list) */
$countByStatus = function (array $statuses) use ($tasks) {
    return count(array_filter($tasks, fn($t) => in_array($t['status'], $statuses, true)));
};
$summary = [
    ['count' => count($tasks),                           'label' => 'Total',       'class' => ''],
    ['count' => $countByStatus(['Pending', 'Assigned']), 'label' => 'Pending',     'class' => 'orange'],
    ['count' => $countByStatus(['In Progress']),         'label' => 'In Progress', 'class' => 'purple'],
    ['count' => $countByStatus(['Completed']),           'label' => 'Completed',   'class' => 'green'],
];

renderHead('My Dashboard', 'Dashboard.css', 'maintenance');
?>
<div class="page">

    <!-- ONE CONTAINER: banner + all page content -->
    <div class="one-card">

    <?php renderPageHeader('Today\'s Tasks', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <!-- TASK QUEUE (hero) -->
    <section class="panel">
        <div class="panel-head">
            <h2>Assigned to You</h2>
            <div class="summary-line">
                <?php foreach ($summary as $st): ?>
                    <div class="summary-item">
                        <strong class="<?= e($st['class']) ?>"><?= (int)$st['count'] ?></strong>
                        <span><?= e($st['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <span class="count-badge"><?= count($tasks) ?> active</span>
        </div>

        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <p>No tasks assigned to you right now.</p>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item priority-<?= e(strtolower($task['priority'])) ?>"
                         onclick="window.location.href='Request.php?id=<?= e($task['id']) ?>'">
                        <div class="task-left">
                            <h3><?= e($task['title']) ?></h3>
                            <div class="task-meta">
                                <?= e($task['id']) ?> · <?= e($task['location']) ?>
                            </div>
                            <div class="task-timing">
                                <span><?= e($task['when']) ?></span>
                                <span class="dot">·</span>
                                <span><?= e($task['estimate']) ?></span>
                            </div>
                        </div>
                        <div class="task-right">
                            <span class="tag <?= e($task['tagClass']) ?>"><?= e($task['priority']) ?></span>
                            <?= statusBadge($task['status']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- SMALL METRIC STRIP -->
    <section class="metric-strip">
        <div class="metric-item">
            <span class="metric-label">Avg. Response</span>
            <span class="metric-value">2h 18m</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Completed Today</span>
            <span class="metric-value">5</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Completed This Week</span>
            <span class="metric-value">18</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">On Hold</span>
            <span class="metric-value warn">1</span>
        </div>
    </section>

    </div>

</div>
<?php renderFoot(); ?>