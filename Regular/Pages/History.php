<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$stats = [
    [
        'title'       => 'My Request',
        'count'       => 10,
        'description' => 'All submitted requests',
        'icon'        => '<path d="M8 4h8a2 2 0 0 1 2 2v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V6a2 2 0 0 1 2-2z"></path><path d="M9 9h6M9 13h6M9 17h3"></path>',
        'class'       => '',
    ],
    [
        'title'       => 'Complete',
        'count'       => 3,
        'description' => 'All Completed requests',
        'icon'        => '<circle cx="12" cy="12" r="9"></circle><path d="M8 12.5l3 3 5-6"></path>',
        'class'       => 'complete',
    ],
    [
        'title'       => 'In Progress',
        'count'       => 5,
        'description' => 'All In Progress requests',
        'icon'        => '<path d="M20 12a8 8 0 1 1-2.3-5.7"></path><path d="M20 4v4h-4"></path>',
        'class'       => 'progress',
    ],
    [
        'title'       => 'Pending',
        'count'       => 2,
        'description' => 'All Pending requests',
        'icon'        => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>',
        'class'       => 'pending',
    ],
];

$historyRequests = [
    [
        'id'         => 'MT-00124',
        'title'      => 'BROKEN AIRCON',
        'location'   => 'Admin Office - 2nd Floor',
        'tag'        => 'Aircon',
        'date'       => 'Sept 10, 2026',
        'technician' => 'Pedro Santos',
    ],
    [
        'id'         => 'MT-00122',
        'title'      => 'PRINTER NOT WORKING',
        'location'   => 'Barangay Hall - Records Office',
        'tag'        => 'Printer',
        'date'       => 'Sept 9, 2026',
        'technician' => 'Pending Assignment',
    ],
    [
        'id'         => 'MT-00118',
        'title'      => 'BROKEN LIGHT',
        'location'   => 'Covered Court',
        'tag'        => 'Electrical',
        'date'       => 'Sept 7, 2026',
        'technician' => 'Carlo Reyes',
    ],
];

renderHead('Maintenance History', 'History.css', 'regular');
?>
<div class="page">

    <?php renderPageHeader('Maintenance History', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <section class="history-card">

        <div class="stats-grid">
            <?php foreach ($stats as $item): ?>
                <div class="stat-card">
                    <div class="stat-title"><?= e($item['title']) ?></div>
                    <div class="stat-number <?= e($item['class']) ?>"><?= (int)$item['count'] ?></div>
                    <div class="stat-description"><?= e($item['description']) ?></div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                            <?= $item['icon'] ?>
                        </svg>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="history-controls">
            <div class="search-box">
                <input type="text" placeholder="Search requests...">
                <span class="search-icon">⌕</span>
            </div>

            <select class="filter-select">
                <option value="">All Statuses</option>
                <option value="complete">Complete</option>
                <option value="progress">In Progress</option>
                <option value="pending">Pending</option>
            </select>

            <button class="reset-button" type="button">Reset</button>

            <button class="request-button" type="button" onclick="window.location.href='Report.php'">
                Submit Maintenance Request
            </button>
        </div>

        <div class="request-list">
            <?php foreach ($historyRequests as $req): ?>
                <div class="request-card" onclick="window.location.href='Request.php'">
                    <div class="request-title">
                        <?= e($req['id']) ?> - <?= e($req['title']) ?>
                    </div>

                    <div class="request-details">
                        <?= e($req['location']) ?>
                    </div>

                    <div class="request-tags">
                        <span class="tag">
                            <span class="tag-icon"></span>
                            <?= e($req['tag']) ?>
                        </span>

                        <span class="tag">
                            <?= e($req['date']) ?>
                        </span>

                        <span class="tag">
                            Technician:
                            <strong><?= e($req['technician']) ?></strong>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

</div>
<?php renderFoot(); ?>
</html>