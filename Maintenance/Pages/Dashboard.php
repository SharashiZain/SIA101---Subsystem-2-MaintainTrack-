<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$stats = [
    [
        'label' => 'My Task',
        'value' => 12,
        'icon'  => '<rect x="5" y="4" width="14" height="17" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 3h6v3H9z" stroke="currentColor" stroke-width="1.6"/><path d="M8 11h8M8 15h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
    ],
    [
        'label' => 'Pending',
        'value' => 4,
        'icon'  => '<circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
    ],
    [
        'label' => 'In Progress',
        'value' => 3,
        'icon'  => '<path d="M6 3h12M6 21h12M7 3c0 5 5 6 5 9s-5 4-5 9M17 3c0 5-5 6-5 9s5 4 5 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
    ],
    [
        'label' => 'Completed',
        'value' => 5,
        'icon'  => '<path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    ],
];

$priorities = [
    ['id' => 'MT-10001', 'title' => 'Broken Aircon', 'location' => 'Admin Office', 'priority' => 'High',   'tagClass' => 'tag-high'],
    ['id' => 'MT-10002', 'title' => 'Broken Aircon', 'location' => 'Admin Office', 'priority' => 'Low',    'tagClass' => 'tag-low'],
    ['id' => 'MT-10003', 'title' => 'Broken Aircon', 'location' => 'Admin Office', 'priority' => 'Medium', 'tagClass' => 'tag-mid'],
];

$progressList = [
    ['label' => 'In Progress', 'percent' => 50, 'fillClass' => 'fill-blue'],
    ['label' => 'Pending',     'percent' => 50, 'fillClass' => 'fill-yellow'],
    ['label' => 'Completed',   'percent' => 50, 'fillClass' => 'fill-green'],
];

$recentUpdates = [
    ['title' => 'Photo Uploaded',    'time' => 'Sept 11, 2026 · 11:59PM', 'meta' => 'MT10001'],
    ['title' => 'New Task Assigned', 'time' => 'Yesterday · 12:01AM',     'meta' => 'MT10001'],
    ['title' => 'Photo Uploaded',    'time' => 'Sept 10, 2026 · 11:59PM', 'meta' => 'MT10001'],
];

renderHead('My Dashboard', 'Dashboard.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('My Dashboard', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <section class="greeting-card">
        <h2>Good Morning, Jose!</h2>
        <p>Here's your maintenance workload and the latest task updates for Barangay Gulod.</p>
        <button class="btn-primary" type="button" onclick="window.location.href='Task.php'">View My Task</button>
    </section>

    <section class="stat-grid">
        <?php foreach ($stats as $item): ?>
            <div class="stat-card">
                <div>
                    <span class="stat-label"><?= e($item['label']) ?></span>
                    <span class="stat-value"><?= e($item['value']) ?></span>
                </div>
                <span class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none"><?= $item['icon'] ?></svg>
                </span>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="panel-grid">
        <div class="panel">
            <h3>Task Priorities</h3>
            <ul class="priority-list">
                <?php foreach ($priorities as $task): ?>
                    <li>
                        <div>
                            <span class="task-id"><?= e($task['id']) ?> <?= e($task['title']) ?></span>
                            <span class="task-loc"><?= e($task['location']) ?></span>
                        </div>
                        <span class="tag <?= e($task['tagClass']) ?>"><?= e($task['priority']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="panel">
            <h3>Task Progress</h3>
            <?php foreach ($progressList as $prog): ?>
                <div class="progress-row">
                    <div class="progress-label">
                        <span><?= e($prog['label']) ?></span>
                        <span><?= e($prog['percent']) ?>%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill <?= e($prog['fillClass']) ?>" style="width: <?= (int)$prog['percent'] ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="panel">
            <h3>Recent Updates</h3>
            <ul class="update-list">
                <?php foreach ($recentUpdates as $update): ?>
                    <li>
                        <div class="update-row">
                            <span class="update-title"><?= e($update['title']) ?></span>
                            <span class="update-time"><?= e($update['time']) ?></span>
                        </div>
                        <span class="update-meta"><?= e($update['meta']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

</div>
<?php renderFoot(); ?>
</html>