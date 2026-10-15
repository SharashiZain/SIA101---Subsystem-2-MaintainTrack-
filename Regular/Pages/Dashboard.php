<?php
require_once '../../Components/layout.php';
require_once '../../Components/data.php';

$requests = getDemoRequests();

$summary = [
    ['count' => count($requests), 'label' => 'Total', 'class' => ''],
    ['count' => count(array_filter($requests, fn($r) => ($r['statusClass'] ?? '') === 'pending')), 'label' => 'Pending', 'class' => 'orange'],
    ['count' => count(array_filter($requests, fn($r) => ($r['statusClass'] ?? '') === 'in-progress')), 'label' => 'In Progress', 'class' => 'purple'],
    ['count' => count(array_filter($requests, fn($r) => ($r['statusClass'] ?? '') === 'completed')), 'label' => 'Completed', 'class' => 'green'],
];

renderHead('My Dashboard', 'reg_user_dashboard.css', 'regular');
?>
<div class="page">

    

    <!-- RECENT REQUESTS -->
    <section class="panel">
        <!-- HERO CARD: page header + welcome banner combined -->
    <div class="hero-card">

    <?php renderPageHeader('My Dashboard', 'Maintenance Reporting Portal · Barangay Gulod', 'regular'); ?>

    <!-- WELCOME BANNER -->
    <section class="welcome-card">
        <div class="welcome-content">
            <h2>Hello, Juan</h2>
            <p>Have a maintenance concern? Report it and track its progress.</p>
        </div>
        <button type="button" class="report-button" onclick="window.location.href='Report.php'">
            <span class="report-button-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
            </span>
            Report an Issue
        </button>
    </section>

    </div>
        <div class="panel-head">
            <h2>My Recent Requests</h2>
            <div class="summary-line">
                <?php foreach ($summary as $s): ?>
                    <div class="summary-item">
                        <strong class="<?= e($s['class']) ?>"><?= (int)$s['count'] ?></strong>
                        <span><?= e($s['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="History.php" class="panel-link">View all →</a>
        </div>

        <div class="request-list">
            <?php foreach ($requests as $req): ?>
                <div class="request-card" onclick="window.location.href='Request.php?id=<?= e($req['id']) ?>'">
                    <div class="request-main">
                        <div class="request-id"><?= e($req['id']) ?></div>
                        <div class="request-title"><?= e($req['title']) ?></div>
                        <div class="request-details"><?= e($req['location']) ?> · <?= e($req['date']) ?></div>
                    </div>
                    <div class="request-side">
                        <span class="status <?= e($req['statusClass']) ?>"><?= e($req['status']) ?></span>
                        <span class="chevron" aria-hidden="true">›</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

</div>
<?php renderFoot(); ?>
</html>