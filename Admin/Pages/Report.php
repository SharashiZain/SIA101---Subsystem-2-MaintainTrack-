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

/* ---- Sample report data (used for preview) ---- */
$previewData = [
    'Summary' => [
        'headers' => ['Request ID', 'Issue', 'Status', 'Date'],
        'rows' => [
            ['MT-00131', "PC won't turn on",       'In Progress', 'Sept 19, 2026'],
            ['MT-00132', 'Broken aircon',          'Assigned',    'Sept 19, 2026'],
            ['MT-00133', 'No internet connection', 'Pending',     'Sept 19, 2026'],
            ['MT-00134', "Software won't launch",  'Completed',   'Sept 18, 2026'],
            ['MT-00135', 'Flickering lights',      'Pending',     'Sept 18, 2026'],
        ],
    ],
    'Status' => [
        'headers' => ['Status', 'Count', 'Percentage'],
        'rows' => [
            ['Pending',     '2', '40%'],
            ['Assigned',    '1', '20%'],
            ['In Progress', '1', '20%'],
            ['Completed',   '1', '20%'],
        ],
    ],
    'Recurring Issues' => [
        'headers' => ['Equipment', 'Lab', 'Total Repairs'],
        'rows' => [
            ['PC-L1-01', 'Lab 1 (B1)', '4'],
            ['AC-L3-02', 'Lab 3 (B1)', '2'],
            ['Router-L2-01', 'Lab 2 (B1)', '1'],
        ],
    ],
    'Completed Activities' => [
        'headers' => ['Request ID', 'Issue', 'Completed On'],
        'rows' => [
            ['MT-00134', "Software won't launch", 'Sept 18, 2026'],
            ['MT-00118', 'Broken Light',          'Sept 8, 2026'],
            ['MT-00117', 'Printer offline',       'Sept 5, 2026'],
        ],
    ],
];

renderHead('Reports', 'Report.css');
?>
<div class="container">

    <?php renderPageHeader('Reports'); ?>

    <!-- REPORT TYPE SECTION -->
    <section class="report-type-section">
        <h2 class="section-title">Report type</h2>

        <div class="report-cards">
            <?php foreach ($reportTypes as $type): ?>
                <div class="report-card" data-report-title="<?= e($type['title']) ?>">
                    <h3><?= e($type['title']) ?></h3>
                    <p><?= e($type['description']) ?></p>
                    <button class="btn-select" type="button" data-report-title="<?= e($type['title']) ?>">Select</button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="main-content">

        <!-- PARAMETERS PANEL -->
        <aside class="parameters-panel card">
            <h2>Parameters</h2>

            <div class="form-group">
                <label for="paramPeriod">Period</label>
                <input type="text" id="paramPeriod" value="<?= e($parameters['Period']) ?>">
            </div>

            <div class="form-group">
                <label for="paramLab">Laboratory</label>
                <select id="paramLab">
                    <option value="All labs">All labs</option>
                    <option value="Lab 1 (B1)">Lab 1 (B1)</option>
                    <option value="Lab 2 (B1)">Lab 2 (B1)</option>
                    <option value="Lab 3 (B1)">Lab 3 (B1)</option>
                    <option value="Lab 4 (B1)">Lab 4 (B1)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="paramCategory">Category</label>
                <select id="paramCategory">
                    <option value="All categories">All categories</option>
                    <option value="Hardware">Hardware</option>
                    <option value="Software">Software</option>
                    <option value="Network">Network</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Facility">Facility</option>
                </select>
            </div>

            <button class="btn-primary full-width" id="generateReportBtn">Generate report</button>
        </aside>

        <!-- PREVIEW PANEL -->
        <main class="preview-panel card">
            <div class="preview-header">
                <h2>Preview</h2>
                <span class="preview-type" id="previewTypeLabel">No report selected</span>
            </div>

            <!-- SKELETON (default state) -->
            <div class="skeleton-container" id="reportSkeleton">
                <?php for ($row = 0; $row < 7; $row++): ?>
                    <div class="skeleton-row">
                        <?php for ($box = 0; $box < 3; $box++): ?>
                            <div class="sk-box"></div>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- PREVIEW TABLE (hidden by default) -->
            <div class="preview-table-container" id="previewTableContainer" style="display: none;">
                <table class="preview-table">
                    <thead id="previewTableHead"></thead>
                    <tbody id="previewTableBody"></tbody>
                </table>
            </div>

            <!-- EMPTY STATE (hidden by default) -->
            <div class="preview-empty" id="previewEmpty" style="display: none;">
                <p>Select a report type and click <strong>Generate report</strong> to preview.</p>
            </div>

            <div class="action-footer">
                <button class="btn-secondary" id="exportPdfBtn">Export PDF</button>
                <button class="btn-secondary" id="exportCsvBtn">Export CSV</button>
                <button class="btn-primary" id="printReportBtn">Print</button>
            </div>
        </main>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        /* ---- Preview data from PHP ---- */
        const previewData = <?= json_encode($previewData) ?>;

        /* ---- State ---- */
        let selectedReport = null;

        /* ---- Elements ---- */
        const reportCards = document.querySelectorAll('.report-card');
        const previewSkeleton = document.getElementById('reportSkeleton');
        const previewTableContainer = document.getElementById('previewTableContainer');
        const previewTableHead = document.getElementById('previewTableHead');
        const previewTableBody = document.getElementById('previewTableBody');
        const previewEmpty = document.getElementById('previewEmpty');
        const previewTypeLabel = document.getElementById('previewTypeLabel');
        const generateBtn = document.getElementById('generateReportBtn');
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        const exportCsvBtn = document.getElementById('exportCsvBtn');
        const printReportBtn = document.getElementById('printReportBtn');

        /* ==========================================
           SELECT REPORT TYPE
           ========================================== */
        function selectReportType(title) {
            selectedReport = title;
            reportCards.forEach(function (card) {
                card.classList.toggle('selected', card.dataset.reportTitle === title);
            });
            previewTypeLabel.textContent = title + ' report';
        }

        reportCards.forEach(function (card) {
            card.addEventListener('click', function () {
                selectReportType(this.dataset.reportTitle);
            });
        });

        document.querySelectorAll('.btn-select').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                selectReportType(this.dataset.reportTitle);
            });
        });

        /* ==========================================
           GENERATE REPORT
           ========================================== */
        generateBtn.addEventListener('click', function () {
            if (!selectedReport) {
                alert('Please select a report type first.');
                return;
            }

            const data = previewData[selectedReport];
            if (!data) {
                alert('No data available for this report.');
                return;
            }

            /* Show skeleton briefly for realism */
            previewSkeleton.style.display = 'flex';
            previewTableContainer.style.display = 'none';
            previewEmpty.style.display = 'none';

            setTimeout(function () {
                /* Build the table header */
                previewTableHead.innerHTML = '';
                const headerRow = document.createElement('tr');
                data.headers.forEach(function (h) {
                    const th = document.createElement('th');
                    th.textContent = h;
                    headerRow.appendChild(th);
                });
                previewTableHead.appendChild(headerRow);

                /* Build the table body */
                previewTableBody.innerHTML = '';
                data.rows.forEach(function (row) {
                    const tr = document.createElement('tr');
                    row.forEach(function (cell) {
                        const td = document.createElement('td');
                        td.textContent = cell;
                        tr.appendChild(td);
                    });
                    previewTableBody.appendChild(tr);
                });

                /* Show the table, hide the skeleton */
                previewSkeleton.style.display = 'none';
                previewTableContainer.style.display = 'block';
            }, 400);
        });

        /* ==========================================
           EXPORT PDF (Static demo)
           ========================================== */
        exportPdfBtn.addEventListener('click', function () {
            if (!selectedReport) {
                alert('Please generate a report first.');
                return;
            }
            alert('PDF export ready: ' + selectedReport + ' report will be downloaded.');
        });

        /* ==========================================
           EXPORT CSV (Static demo)
           ========================================== */
        exportCsvBtn.addEventListener('click', function () {
            if (!selectedReport) {
                alert('Please generate a report first.');
                return;
            }

            const data = previewData[selectedReport];
            let csv = data.headers.join(',') + '\n';
            data.rows.forEach(function (row) {
                csv += row.map(function (cell) {
                    return '"' + String(cell).replace(/"/g, '""') + '"';
                }).join(',') + '\n';
            });

            /* Trigger download */
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = selectedReport.replace(/\s+/g, '_') + '_report.csv';
            link.click();
            URL.revokeObjectURL(url);
        });

        /* ==========================================
           PRINT REPORT
           ========================================== */
        printReportBtn.addEventListener('click', function () {
            if (!selectedReport) {
                alert('Please generate a report first.');
                return;
            }
            window.print();
        });

        /* ==========================================
           SHOW EMPTY STATE INITIALLY
           ========================================== */
        previewEmpty.style.display = 'block';
    });
</script>
<?php renderFoot(); ?>