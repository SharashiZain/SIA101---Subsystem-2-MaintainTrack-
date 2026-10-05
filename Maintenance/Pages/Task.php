<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$stats = [
    ['label' => 'My Task',     'value' => 12],
    ['label' => 'Pending',     'value' => 4],
    ['label' => 'In Progress', 'value' => 3],
    ['label' => 'Completed',   'value' => 5],
];

$tasks = [
    [
        'title'    => 'Broken Aircon',
        'priority' => 'High',
        'tagClass' => 'tag-high',
        'ref'      => 'MT-10101 · Barangay Hall · 3rd Floor',
        'desc'     => 'Aircon unit is making unusual rattling noises and fails to produce cold air. Requires immediate inspection.',
        'chip'     => 'Aircon',
        'date'     => 'Sept 11, 2026',
    ],
    [
        'title'    => 'Broken Aircon',
        'priority' => 'Medium',
        'tagClass' => 'tag-mid',
        'ref'      => 'MT-10102 · Barangay Hall · 2nd Floor',
        'desc'     => 'Routine filter replacement and coolant level check needed for continuous operation.',
        'chip'     => 'Aircon',
        'date'     => 'Sept 11, 2026',
    ],
    [
        'title'    => 'Broken Aircon',
        'priority' => 'Low',
        'tagClass' => 'tag-low',
        'ref'      => 'MT-10103 · Barangay Hall · Ground Floor',
        'desc'     => 'Minor cosmetic damage on vent cover, no operational malfunction reported.',
        'chip'     => 'Aircon',
        'date'     => 'Sept 11, 2026',
    ],
];

renderHead('My Tasks', 'Task.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('My Tasks', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <section class="stat-grid">
        <?php foreach ($stats as $item): ?>
            <div class="stat-card">
                <span class="stat-label"><?= e($item['label']) ?></span>
                <span class="stat-value"><?= e($item['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="filter-bar">
        <div class="search-input">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" placeholder="Search task...">
        </div>
        <button class="filter-btn icon-only" type="button" aria-label="Filter calendar">
            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="17" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 9h18M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
        <button class="filter-btn" type="button">All Status
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="filter-btn" type="button">All Facilities
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="reset-btn" type="button">Reset</button>
    </section>

    <section class="task-list">
        <?php foreach ($tasks as $task): ?>
            <article class="task-card">
                <div class="task-card-top">
                    <h3><?= e($task['title']) ?></h3>
                    <span class="tag <?= e($task['tagClass']) ?>"><?= e($task['priority']) ?></span>
                </div>
                <span class="task-ref"><?= e($task['ref']) ?></span>
                <p class="task-desc"><?= e($task['desc']) ?></p>
                <div class="task-card-bottom">
                    <div class="task-meta">
                        <span class="meta-chip"><?= e($task['chip']) ?></span>
                        <span class="meta-date"><?= e($task['date']) ?></span>
                    </div>
                    <button class="btn-primary" type="button" onclick="window.location.href='Request.php'">View Details</button>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <nav class="pagination" aria-label="Tasks pagination">
        <button class="page-num active" type="button">1</button>
        <button class="page-num" type="button">2</button>
        <button class="page-num" type="button">3</button>
        <button class="page-num" type="button">4</button>
        <button class="page-num" type="button">5</button>
        <button class="page-num" type="button">6</button>
    </nav>

</div>
<?php renderFoot(); ?>
</html>