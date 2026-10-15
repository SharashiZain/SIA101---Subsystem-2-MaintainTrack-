<?php
require_once '../../Components/layout.php';

/* ---- Sample data ---- */
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

    

    <div class="one-card">
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

            <div class="skeleton-container" id="reportSkeleton">
                <?php for ($row = 0; $row < 5; $row++): ?>
                    <div class="skeleton-row">
                        <?php for ($box = 0; $box < 3; $box++): ?>
                            <div class="sk-box"></div>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="preview-table-container" id="previewTableContainer" style="display: none;">
                <table class="preview-table">
                    <thead id="previewTableHead"></thead>
                    <tbody id="previewTableBody"></tbody>
                </table>
            </div>

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
</div>

<!-- =========================================================
     SYSTEM MODAL (validation + success)
========================================================= -->
<div class="system-modal-overlay" id="systemModal" role="dialog" aria-modal="true" style="display: none;">
    <div class="system-modal">
        <div class="system-modal-icon" id="systemModalIcon"></div>
        <h3 class="system-modal-title" id="systemModalTitle">Title</h3>
        <p class="system-modal-text" id="systemModalText">Message</p>
        <div class="system-modal-actions" id="systemModalActions"></div>
    </div>
</div>

<script>
    /* ==========================================
       SYSTEM MODAL HELPER
       ========================================== */
    window.SystemModal = (function () {
        const overlay = document.getElementById('systemModal');
        const iconBox = document.getElementById('systemModalIcon');
        const titleEl = document.getElementById('systemModalTitle');
        const textEl = document.getElementById('systemModalText');
        const actionsEl = document.getElementById('systemModalActions');

        const icons = {
            question: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            warning:  '<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            success:  '<svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            danger:   '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
        };

        function open(options) {
            const { icon = 'question', title = '', text = '', confirmText = 'OK', cancelText = null, confirmClass = 'system-btn-confirm', onConfirm = () => {}, onCancel = () => {} } = options;

            iconBox.className = 'system-modal-icon icon-' + icon;
            iconBox.innerHTML = icons[icon] || icons.question;
            titleEl.textContent = title;
            textEl.textContent = text;
            actionsEl.innerHTML = '';

            if (cancelText) {
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'system-btn system-btn-cancel';
                cancelBtn.textContent = cancelText;
                cancelBtn.addEventListener('click', function () { close(); onCancel(); });
                actionsEl.appendChild(cancelBtn);
            }

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'system-btn ' + confirmClass;
            confirmBtn.textContent = confirmText;
            confirmBtn.addEventListener('click', function () { close(); onConfirm(); });
            actionsEl.appendChild(confirmBtn);

            overlay.style.display = 'flex';
        }

        function close() { overlay.style.display = 'none'; }

        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display === 'flex') close();
        });

        return { open, close };
    })();

    /* ==========================================
       REPORT PAGE LOGIC
       ========================================== */
    document.addEventListener('DOMContentLoaded', function () {

        const previewData = <?= json_encode($previewData) ?>;
        let selectedReport = null;

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
           GENERATE REPORT — styled modal on missing selection
           ========================================== */
        generateBtn.addEventListener('click', function () {
            if (!selectedReport) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No report selected',
                    text: 'Please select a report type first before generating.',
                    confirmText: 'OK'
                });
                return;
            }

            const data = previewData[selectedReport];
            if (!data) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No data available',
                    text: 'There is no data for this report type.',
                    confirmText: 'OK'
                });
                return;
            }

            /* Show skeleton briefly */
            previewSkeleton.style.display = 'flex';
            previewTableContainer.style.display = 'none';
            previewEmpty.style.display = 'none';

            setTimeout(function () {
                previewTableHead.innerHTML = '';
                const headerRow = document.createElement('tr');
                data.headers.forEach(function (h) {
                    const th = document.createElement('th');
                    th.textContent = h;
                    headerRow.appendChild(th);
                });
                previewTableHead.appendChild(headerRow);

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

                previewSkeleton.style.display = 'none';
                previewTableContainer.style.display = 'block';
            }, 400);
        });

        /* ==========================================
           EXPORT PDF — styled modal
           ========================================== */
        exportPdfBtn.addEventListener('click', function () {
            if (!selectedReport) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No report selected',
                    text: 'Please generate a report first before exporting.',
                    confirmText: 'OK'
                });
                return;
            }
            SystemModal.open({
                icon: 'success',
                title: 'PDF export ready',
                text: 'The ' + selectedReport + ' report will be downloaded.',
                confirmText: 'OK'
            });
        });

        /* ==========================================
           EXPORT CSV — real download
           ========================================== */
        exportCsvBtn.addEventListener('click', function () {
            if (!selectedReport) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No report selected',
                    text: 'Please generate a report first before exporting.',
                    confirmText: 'OK'
                });
                return;
            }

            const data = previewData[selectedReport];
            let csv = data.headers.join(',') + '\n';
            data.rows.forEach(function (row) {
                csv += row.map(function (cell) {
                    return '"' + String(cell).replace(/"/g, '""') + '"';
                }).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = selectedReport.replace(/\s+/g, '_') + '_report.csv';
            link.click();
            URL.revokeObjectURL(url);

            SystemModal.open({
                icon: 'success',
                title: 'CSV downloaded',
                text: selectedReport + '_report.csv has been saved.',
                confirmText: 'OK'
            });
        });

        /* ==========================================
           PRINT — styled modal on missing selection
           ========================================== */
        printReportBtn.addEventListener('click', function () {
            if (!selectedReport) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No report selected',
                    text: 'Please generate a report first before printing.',
                    confirmText: 'OK'
                });
                return;
            }
            window.print();
        });

        /* Initial state */
        previewEmpty.style.display = 'block';
    });
</script>
<?php renderFoot(); ?>
</html>