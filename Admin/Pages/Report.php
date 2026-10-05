<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$reportTypes = [
    ['title' => 'Summary',              'description' => 'Overview of requests, statuses, and activity'],
    ['title' => 'Status',               'description' => 'Request counts by current status'],
    ['title' => 'Recurring Issues',     'description' => 'Equipment with repeat maintenance concerns'],
    ['title' => 'Completed Activities', 'description' => 'Completed requests and logged actions'],
];

$parameters = [
    'Period'     => 'September 2026',
    'Laboratory' => 'All labs',
    'Category'   => 'All categories',
];

renderHead('Reports', 'Report.css');
?>
    <div class="container">

        <?php renderPageHeader('Reports'); ?>

        <section class="report-type-section">
            <h2 class="section-title">Report type</h2>

            <div class="report-cards">
                <?php foreach ($reportTypes as $type): ?>
                    <div class="report-card">
                        <h3><?= e($type['title']) ?></h3>
                        <p><?= e($type['description']) ?></p>
                        <button class="btn-select">Select</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="main-content">

            <aside class="parameters-panel card">
                <h2>Parameters</h2>

                <?php foreach ($parameters as $label => $value): ?>
                    <div class="form-group">
                        <label><?= e($label) ?></label>
                        <input type="text" value="<?= e($value) ?>" readonly>
                    </div>
                <?php endforeach; ?>

                <button class="btn-primary full-width">Generate report</button>
            </aside>

            <main class="preview-panel card">
                <h2>Preview</h2>

                <div class="skeleton-container">
                    <?php for ($row = 0; $row < 7; $row++): ?>
                        <div class="skeleton-row">
                            <?php for ($box = 0; $box < 3; $box++): ?>
                                <div class="sk-box"></div>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="action-footer">
                    <button class="btn-secondary">Export PDF</button>
                    <button class="btn-secondary">Export CSV</button>
                    <button class="btn-primary">Print</button>
                </div>
            </main>

        </div>
    </div>
<?php renderFoot(); ?>
