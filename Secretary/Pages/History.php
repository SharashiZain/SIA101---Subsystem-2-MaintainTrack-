<?php
require_once __DIR__ . '/../../Components/layout.php';

$history = [
    ['id' => 'MT-00133', 'title' => 'No internet connection', 'submitter' => 'Carlo Reyes',  'date' => 'Oct 13, 2026', 'decision' => 'Approved', 'decisionKey' => 'approved'],
    ['id' => 'MT-00134', 'title' => 'Software won\'t launch', 'submitter' => 'Pedro Santos', 'date' => 'Oct 13, 2026', 'decision' => 'Approved', 'decisionKey' => 'approved'],
    ['id' => 'MT-00135', 'title' => 'Flickering lights',      'submitter' => 'Ana Reyes',    'date' => 'Oct 12, 2026', 'decision' => 'Rejected', 'decisionKey' => 'rejected'],
];

renderHead('History', 'History.css', 'secretary');
?>
<div class="page">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('History', 'Previously reviewed submissions', 'secretary'); ?>

    <section class="panel">
        <div class="panel-head">
            <h2>Reviewed Submissions</h2>
        </div>

        <div class="history-list">
            <?php foreach ($history as $item): ?>
                <div class="history-card" data-decision="<?= e($item['decisionKey']) ?>">
                    <div class="history-main">
                        <div class="history-id"><?= e($item['id']) ?></div>
                        <div class="history-title"><?= e($item['title']) ?></div>
                        <div class="history-meta">
                            Submitted by <?= e($item['submitter']) ?> · <?= e($item['date']) ?>
                        </div>
                    </div>
                    <div class="history-side">
                        <span class="status-pill status-<?= e($item['decisionKey']) ?>"><?= e($item['decision']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    </div><!-- /.one-card -->
</div>
<?php renderFoot(); ?>
</html>