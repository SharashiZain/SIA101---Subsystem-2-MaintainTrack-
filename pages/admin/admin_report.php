<?php
require_once __DIR__ . '/../../Components/layout.php';

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

        <div class="report-cards" id="reportCards">
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
                <div class="period-picker" id="periodPicker">
                    <input type="text" id="paramPeriod" value="<?= e($parameters['Period']) ?>" readonly autocomplete="off" aria-haspopup="dialog" aria-expanded="false" aria-controls="periodPopup">
                    <button type="button" class="period-toggle" id="periodToggle" aria-label="Open calendar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </button>

                    <div class="period-popup" id="periodPopup" role="dialog" aria-label="Choose period" hidden>
                        <div class="period-popup-head">
                            <button type="button" class="period-nav" id="periodPrevYear" aria-label="Previous year">‹</button>
                            <span class="period-year" id="periodYearLabel">2026</span>
                            <button type="button" class="period-nav" id="periodNextYear" aria-label="Next year">›</button>
                        </div>
                        <div class="period-months" id="periodMonths"></div>
                        <div class="period-popup-foot">
                            <button type="button" class="period-today" id="periodThisMonth">This month</button>
                        </div>
                    </div>
                </div>
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
                <div class="preview-header-actions">
                    <span class="preview-type" id="previewTypeLabel">No report selected</span>
                    <button type="button" class="guide-toggle" id="guideToggle" aria-pressed="false" aria-controls="previewEmpty previewHint">
                        <span class="guide-toggle-icon" aria-hidden="true">?</span>
                        <span id="guideToggleText">Show guide</span>
                    </button>
                </div>
            </div>

            <!-- Tells the user what to do next. Changes as they go. -->
            <div class="preview-hint info" id="previewHint" role="status" aria-live="polite">
                <span class="preview-hint-icon" id="previewHintIcon">i</span>
                <span id="previewHintText"></span>
            </div>

            <p class="preview-meta" id="previewMeta" style="display: none;"></p>

            <div class="skeleton-container" id="reportSkeleton" style="display: none;">
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

            <!-- Step-by-step guide, shown until a report has been generated -->
            <div class="preview-empty" id="previewEmpty" style="display: none;">
                <div class="preview-empty-simple" id="simpleEmpty">
                    <p>Select a report type and click <strong>Generate report</strong> to preview.</p>
                    <button type="button" class="guide-link" id="guideShowLink">New here? Show the guide</button>
                </div>
                <div class="guide" id="guideBlock" style="display: none;">
                    <h3 class="guide-title">How to create a report</h3>
                    <p class="guide-sub">Follow the steps below. The highlighted step is what to do next.</p>
                    <ol class="guide-steps" id="guideSteps">
                        <li class="guide-step active" data-step="1" data-target="reportCards">
                            <span class="guide-num">1</span>
                            <div class="guide-body">
                                <strong>Choose a report type</strong>
                                <p>Click <b>Select</b> on one of the cards above: Summary, Status, Recurring Issues, or Completed Activities.</p>
                                <button type="button" class="guide-show" data-target="reportCards">Show me where</button>
                            </div>
                        </li>
                        <li class="guide-step pending" data-step="2" data-target="periodPicker">
                            <span class="guide-num">2</span>
                            <div class="guide-body">
                                <strong>Set the parameters <span class="guide-tag">Optional</span></strong>
                                <p>On the left, pick the Period, Laboratory, and Category. You can skip this to use the defaults.</p>
                                <button type="button" class="guide-show" data-target="periodPicker">Show me where</button>
                            </div>
                        </li>
                        <li class="guide-step pending" data-step="3" data-target="generateReportBtn">
                            <span class="guide-num">3</span>
                            <div class="guide-body">
                                <strong>Click Generate report</strong>
                                <p>Your report appears here as a table. After that you can export it or print it.</p>
                                <button type="button" class="guide-show" data-target="generateReportBtn">Show me where</button>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <p class="action-hint" id="actionHint">Export and Print unlock after you generate a report.</p>

            <div class="action-footer">
                <button class="btn-secondary" id="exportPdfBtn" disabled title="Generate a report first">Export PDF</button>
                <button class="btn-secondary" id="exportCsvBtn" disabled title="Generate a report first">Export CSV</button>
                <button class="btn-primary" id="printReportBtn" disabled title="Generate a report first">Print</button>
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

<style>
/* =========================================
   PERIOD CALENDAR (month picker)
========================================= */
.one-card .parameters-panel.card { overflow: visible; }

.period-picker { position: relative; }

.period-picker input {
    width: 100%;
    box-sizing: border-box;
    padding-right: 46px;
    cursor: pointer;
}

.period-toggle {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #1769aa;
    cursor: pointer;
    transition: background .18s ease;
}
.period-toggle:hover { background: #e4effa; }
.period-toggle:focus-visible { outline: 2px solid #1769aa; outline-offset: 1px; }
.period-toggle svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.period-popup {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    z-index: 50;
    width: 100%;
    min-width: 270px;
    box-sizing: border-box;
    padding: 14px;
    background: #ffffff;
    border: 1px solid #d9e1e8;
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(15, 35, 55, .18);
    animation: periodPop .16s ease-out;
}
.period-popup[hidden] { display: none; }

@keyframes periodPop {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

.period-popup-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.period-year {
    font-size: 15px;
    font-weight: 800;
    color: #153c66;
}

.period-nav {
    width: 32px;
    height: 32px;
    border: 1px solid #d9e1e8;
    border-radius: 8px;
    background: #ffffff;
    color: #334155;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    transition: background .18s ease, border-color .18s ease, color .18s ease;
}
.period-nav:hover { background: #eef5fc; border-color: #9dbfe0; color: #1769aa; }

.period-months {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.period-month {
    height: 38px;
    border: 1px solid transparent;
    border-radius: 10px;
    background: #f3f7fc;
    color: #334155;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background .18s ease, color .18s ease, border-color .18s ease;
}
.period-month:hover { background: #e4effa; border-color: #9dbfe0; color: #1769aa; }
.period-month.current { border-color: #9dbfe0; }
.period-month.selected {
    background: linear-gradient(135deg, #1769aa, #1d4f91);
    color: #ffffff;
    box-shadow: 0 6px 14px rgba(23, 105, 170, .3);
}

.period-popup-foot {
    display: flex;
    justify-content: center;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid #e6eef4;
}

.period-today {
    border: 0;
    background: transparent;
    color: #1769aa;
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 8px;
}
.period-today:hover { background: #e4effa; }

/* =========================================
   PREVIEW GUIDE (teaches the user what to do)
========================================= */

/* Hint bar: always says what to do next */
.preview-hint {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 14px;
    padding: 11px 14px;
    border: 1px solid transparent;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.45;
    transition: background .2s ease, border-color .2s ease, color .2s ease;
}
.preview-hint-icon {
    flex: 0 0 22px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
}
.preview-hint.info    { background: #eaf3fc; border-color: #c9def2; color: #174777; }
.preview-hint.info    .preview-hint-icon { background: #1769aa; }
.preview-hint.success { background: #e6f6ec; border-color: #b6e0c5; color: #16663a; }
.preview-hint.success .preview-hint-icon { background: #16834a; }
.preview-hint.warn    { background: #fff4dc; border-color: #f1d9a0; color: #8a5a00; }
.preview-hint.warn    .preview-hint-icon { background: #c75b00; }

.preview-meta {
    margin: 0 0 12px;
    font-size: 12px;
    font-weight: 700;
    color: #5b7186;
}

/* Step-by-step guide */
.guide {
    max-width: 540px;
    margin: 4px auto 0;
    text-align: left;
}
.guide-title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: #153c66;
}
.guide-sub {
    margin: 4px 0 0;
    font-size: 12px;
    color: #71839a;
}
.guide-steps {
    list-style: none;
    margin: 16px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.guide-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px 14px;
    border: 1px solid #e1eaf2;
    border-radius: 12px;
    background: #ffffff;
    transition: opacity .2s ease, border-color .2s ease, background .2s ease, box-shadow .2s ease;
}
.guide-step.pending  { opacity: .55; }
.guide-step.active   { border-color: #1769aa; background: #f3f8fe; box-shadow: 0 0 0 3px rgba(23, 105, 170, .12); }
.guide-step.done     { border-color: #b6e0c5; background: #f4fbf6; }
.guide-step.optional { border-style: dashed; border-color: #9dbfe0; }

.guide-num {
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e4ebf2;
    color: #5b7186;
    font-size: 13px;
    font-weight: 800;
}
.guide-step.active   .guide-num { background: #1769aa; color: #ffffff; }
.guide-step.done     .guide-num { background: #16834a; color: #ffffff; }
.guide-step.optional .guide-num { background: #ffffff; border: 1px dashed #9dbfe0; color: #1769aa; }

.guide-body { flex: 1; min-width: 0; }
.guide-body strong { font-size: 13px; color: #1d3f5d; }
.guide-body p {
    margin: 3px 0 0;
    font-size: 12px;
    line-height: 1.55;
    color: #5b7186;
}
.guide-tag {
    margin-left: 6px;
    padding: 2px 8px;
    border-radius: 20px;
    background: #eef3f8;
    color: #5b7186;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    vertical-align: middle;
}
.guide-show {
    display: none;
    margin-top: 8px;
    padding: 0;
    border: 0;
    background: transparent;
    color: #1769aa;
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}
.guide-step.active .guide-show,
.guide-step.optional .guide-show { display: inline-block; }
.guide-show:hover { text-decoration: underline; }

/* Old preview stays visible but faded when the settings changed */
.preview-table-container.is-stale { opacity: .45; transition: opacity .2s ease; }

/* Export / Print locked until a fresh report exists */
.action-hint {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 600;
    color: #71839a;
    text-align: right;
}
.action-hint[hidden] { display: none; }
.action-footer button:disabled {
    opacity: .5;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

/* Guide on/off button */
.preview-header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.guide-toggle {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 32px;
    padding: 0 12px 0 8px;
    border: 1px solid #b7d0e0;
    border-radius: 10px;
    background: #ffffff;
    color: #1769aa;
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: background .18s ease, border-color .18s ease, color .18s ease;
}
.guide-toggle:hover { background: #eef5fc; border-color: #1769aa; }
.guide-toggle:focus-visible { outline: 2px solid #1769aa; outline-offset: 2px; }
.guide-toggle-icon {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e4effa;
    color: #1769aa;
    font-size: 11px;
    font-weight: 800;
}
.guide-toggle[aria-pressed="true"] { background: #1769aa; border-color: #1769aa; color: #ffffff; }
.guide-toggle[aria-pressed="true"]:hover { background: #105487; }
.guide-toggle[aria-pressed="true"] .guide-toggle-icon { background: rgba(255, 255, 255, .25); color: #ffffff; }

.guide-link {
    margin-top: 10px;
    padding: 0;
    border: 0;
    background: transparent;
    color: #1769aa;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}
.guide-link:hover { text-decoration: underline; }

/* Attention cues */
@keyframes guidePulse {
    0%   { box-shadow: 0 0 0 0 rgba(23, 105, 170, .5); }
    100% { box-shadow: 0 0 0 16px rgba(23, 105, 170, 0); }
}
@keyframes guideAttn {
    0%, 100% { box-shadow: 0 0 0 0 rgba(23, 105, 170, .45); }
    50%      { box-shadow: 0 0 0 8px rgba(23, 105, 170, 0); }
}
.guide-pulse { animation: guidePulse 1.1s ease-out 2; }
.report-cards.guide-pulse,
.period-picker.guide-pulse { border-radius: 16px; }
.btn-primary.guide-attn { animation: guideAttn 1.8s ease-in-out infinite; }

@media (prefers-reduced-motion: reduce) {
    .guide-pulse,
    .btn-primary.guide-attn { animation: none; }
}
</style>

<!-- PDF libraries (jsPDF + AutoTable). Must load before the page script below. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

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
        const previewHint = document.getElementById('previewHint');
        const previewHintIcon = document.getElementById('previewHintIcon');
        const previewHintText = document.getElementById('previewHintText');
        const previewMeta = document.getElementById('previewMeta');
        const actionHint = document.getElementById('actionHint');
        const guideSteps = document.getElementById('guideSteps');
        const guideBlock = document.getElementById('guideBlock');
        const simpleEmpty = document.getElementById('simpleEmpty');
        const guideToggle = document.getElementById('guideToggle');
        const guideToggleText = document.getElementById('guideToggleText');
        const guideShowLink = document.getElementById('guideShowLink');
        const generateBtn = document.getElementById('generateReportBtn');
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        const exportCsvBtn = document.getElementById('exportCsvBtn');
        const printReportBtn = document.getElementById('printReportBtn');
        const periodInput = document.getElementById('paramPeriod');
        const labSelect = document.getElementById('paramLab');
        const categorySelect = document.getElementById('paramCategory');

        /* ==========================================
           PERIOD CALENDAR (month picker)
           ========================================== */
        (function () {
            const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const picker = document.getElementById('periodPicker');
            const input = document.getElementById('paramPeriod');
            const toggle = document.getElementById('periodToggle');
            const popup = document.getElementById('periodPopup');
            const grid = document.getElementById('periodMonths');
            const yearLabel = document.getElementById('periodYearLabel');
            const today = new Date();

            // Start from the value already in the field (e.g. "September 2026"), else the current month.
            let selYear = today.getFullYear();
            let selMonth = today.getMonth();
            const parsed = /^([A-Za-z]+)\s+(\d{4})$/.exec(input.value.trim());
            if (parsed && MONTHS.indexOf(parsed[1]) !== -1) {
                selMonth = MONTHS.indexOf(parsed[1]);
                selYear = parseInt(parsed[2], 10);
            }
            let viewYear = selYear;

            function render() {
                yearLabel.textContent = viewYear;
                grid.innerHTML = '';
                MONTHS.forEach(function (name, index) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'period-month';
                    if (viewYear === selYear && index === selMonth) btn.classList.add('selected');
                    if (viewYear === today.getFullYear() && index === today.getMonth()) btn.classList.add('current');
                    btn.textContent = name.slice(0, 3);
                    btn.title = name + ' ' + viewYear;
                    btn.addEventListener('click', function () { choose(viewYear, index); });
                    grid.appendChild(btn);
                });
            }

            function choose(year, month) {
                selYear = year;
                selMonth = month;
                input.value = MONTHS[month] + ' ' + year;
                // Lets the preview guide know the period changed.
                input.dispatchEvent(new Event('change', { bubbles: true }));
                closePopup();
            }

            function openPopup() {
                viewYear = selYear;
                render();
                popup.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }

            function closePopup() {
                popup.hidden = true;
                input.setAttribute('aria-expanded', 'false');
            }

            function togglePopup() { popup.hidden ? openPopup() : closePopup(); }

            input.addEventListener('click', togglePopup);
            toggle.addEventListener('click', togglePopup);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') { e.preventDefault(); openPopup(); }
            });
            document.getElementById('periodPrevYear').addEventListener('click', function () { viewYear--; render(); });
            document.getElementById('periodNextYear').addEventListener('click', function () { viewYear++; render(); });
            document.getElementById('periodThisMonth').addEventListener('click', function () {
                choose(today.getFullYear(), today.getMonth());
            });
            document.addEventListener('click', function (e) {
                if (!popup.hidden && !picker.contains(e.target)) closePopup();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !popup.hidden) { closePopup(); input.focus(); }
            });
        })();

        /* ==========================================
           PREVIEW GUIDE
           The preview always tells the user the next step.
           States: nothing chosen -> report chosen -> generated -> settings changed (needs refresh)
           ========================================== */
        let generatedReport = null;   // report type currently shown in the table
        let generatedParams = '';     // parameters used for the table
        let paramsTouched = false;    // user changed Period / Laboratory / Category
        let isLoading = false;        // skeleton is showing
        let guideOn = false;          // the tutorial is optional: off until the user turns it on
        try { guideOn = localStorage.getItem('reportGuideOn') === '1'; } catch (err) {}

        const hintIcons = { info: 'i', success: '✓', warn: '!' };

        function paramsKey() {
            return [periodInput.value, labSelect.value, categorySelect.value].join('|');
        }
        function paramsLabel() {
            return [periodInput.value, labSelect.value, categorySelect.value].join(' · ');
        }
        function setHint(kind, text) {
            previewHint.className = 'preview-hint ' + kind;
            previewHint.style.display = (guideOn || kind === 'warn') ? '' : 'none';
            previewHintIcon.textContent = hintIcons[kind];
            previewHintText.textContent = text;
        }
        function setStep(n, state) {
            const li = guideSteps.querySelector('[data-step="' + n + '"]');
            li.className = 'guide-step ' + state;
            li.querySelector('.guide-num').textContent = state === 'done' ? '✓' : String(n);
        }
        function pulseTarget(el) {
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.remove('guide-pulse');
            void el.offsetWidth; // restart the animation
            el.classList.add('guide-pulse');
            setTimeout(function () { el.classList.remove('guide-pulse'); }, 2600);
        }

        function syncGuideToggle() {
            guideToggle.setAttribute('aria-pressed', guideOn ? 'true' : 'false');
            guideToggleText.textContent = guideOn ? 'Hide guide' : 'Show guide';
        }

        function updateGuide() {
            syncGuideToggle();
            if (isLoading) return;

            const hasSelection = !!selectedReport;
            const isGenerated = !!generatedReport;
            const isStale = isGenerated && (generatedReport !== selectedReport || generatedParams !== paramsKey());
            const canExport = isGenerated && !isStale;

            // What to do next
            if (isStale) {
                setHint('warn', 'You changed the report type or parameters. Click Generate report to refresh the preview.');
            } else if (isGenerated) {
                setHint('success', 'Your ' + generatedReport + ' report is ready. Use Export PDF, Export CSV, or Print below.');
            } else if (hasSelection) {
                setHint('info', '"' + selectedReport + '" is selected. Adjust the parameters if you need to (optional), then click Generate report.');
            } else {
                setHint('info', 'Start by choosing a report type from the cards above. Then click Generate report.');
            }

            // Guide steps (shown until the first report is generated)
            setStep(1, hasSelection ? 'done' : 'active');
            setStep(2, !hasSelection ? 'pending' : (paramsTouched ? 'done' : 'optional'));
            setStep(3, hasSelection ? 'active' : 'pending');

            // What is on screen
            previewEmpty.style.display = isGenerated ? 'none' : 'block';
            guideBlock.style.display = guideOn ? 'block' : 'none';
            simpleEmpty.style.display = guideOn ? 'none' : 'block';
            previewTableContainer.style.display = isGenerated ? 'block' : 'none';
            previewTableContainer.classList.toggle('is-stale', isStale);
            previewMeta.style.display = isGenerated ? 'block' : 'none';
            if (isGenerated) previewMeta.textContent = 'Showing: ' + generatedReport + ' report · ' + generatedParams.split('|').join(' · ');

            // Buttons: Export and Print only work on a fresh report
            [exportPdfBtn, exportCsvBtn, printReportBtn].forEach(function (btn) {
                btn.disabled = !canExport;
                btn.title = canExport ? '' : (isStale ? 'Generate the report again first' : 'Generate a report first');
            });
            actionHint.hidden = canExport;
            actionHint.textContent = isStale
                ? 'Generate the report again to unlock Export and Print.'
                : 'Export and Print unlock after you generate a report.';

            // Gentle pulse on the Generate button when it is the next thing to click
            generateBtn.classList.toggle('guide-attn', guideOn && hasSelection && (!isGenerated || isStale));
        }

        // Show / hide the guide (the choice is remembered in this browser)
        function setGuide(on) {
            guideOn = on;
            try { localStorage.setItem('reportGuideOn', on ? '1' : '0'); } catch (err) {}
            updateGuide();
        }
        guideToggle.addEventListener('click', function () { setGuide(!guideOn); });
        guideShowLink.addEventListener('click', function () { setGuide(true); });

        // "Show me where" links inside the guide
        guideSteps.querySelectorAll('.guide-show').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pulseTarget(document.getElementById(this.dataset.target));
            });
        });

        // Changing any parameter tells the guide
        [periodInput, labSelect, categorySelect].forEach(function (el) {
            el.addEventListener('change', function () {
                paramsTouched = true;
                updateGuide();
            });
        });

        /* ==========================================
           SELECT REPORT TYPE
           ========================================== */
        function selectReportType(title) {
            selectedReport = title;
            reportCards.forEach(function (card) {
                const isChosen = card.dataset.reportTitle === title;
                card.classList.toggle('selected', isChosen);
                const btn = card.querySelector('.btn-select');
                if (btn) btn.textContent = isChosen ? 'Selected ✓' : 'Select';
            });
            previewTypeLabel.textContent = title + ' report';
            updateGuide();
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
                    confirmText: 'OK',
                    onConfirm: function () { pulseTarget(document.getElementById('reportCards')); }
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
            isLoading = true;
            setHint('info', 'Generating your ' + selectedReport + ' report...');
            previewSkeleton.style.display = 'flex';
            previewTableContainer.style.display = 'none';
            previewEmpty.style.display = 'none';
            previewMeta.style.display = 'none';
            [exportPdfBtn, exportCsvBtn, printReportBtn].forEach(function (btn) { btn.disabled = true; });
            generateBtn.classList.remove('guide-attn');

            const reportToBuild = selectedReport;
            const paramsAtStart = paramsKey();

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
                generatedReport = reportToBuild;
                generatedParams = paramsAtStart;
                isLoading = false;
                updateGuide();
            }, 400);
        });

        /* ==========================================
           EXPORT PDF — builds a real PDF file and downloads it
           ========================================== */
        exportPdfBtn.addEventListener('click', function () {
            if (!generatedReport) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No report generated',
                    text: 'Please generate a report first before exporting.',
                    confirmText: 'OK'
                });
                return;
            }

            const jsPDFLib = window.jspdf;
            if (!jsPDFLib || !jsPDFLib.jsPDF) {
                SystemModal.open({
                    icon: 'danger',
                    title: 'PDF export unavailable',
                    text: 'The PDF library could not be loaded. Check your internet connection and reload the page, or use Print and choose "Save as PDF".',
                    confirmText: 'OK'
                });
                return;
            }

            try {
                const data = previewData[generatedReport];
                const doc = new jsPDFLib.jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });

                doc.setFontSize(16);
                doc.setTextColor(21, 60, 102);
                doc.text(generatedReport + ' Report', 40, 50);

                doc.setFontSize(10);
                doc.setTextColor(90, 105, 120);
                doc.text('Maintenance Reporting Portal', 40, 68);
                doc.text('Parameters: ' + generatedParams.split('|').join(' · '), 40, 82);
                doc.text('Generated: ' + new Date().toLocaleString(), 40, 96);

                doc.autoTable({
                    startY: 116,
                    head: [data.headers],
                    body: data.rows,
                    theme: 'striped',
                    headStyles: { fillColor: [23, 105, 170], textColor: 255 },
                    styles: { fontSize: 10, cellPadding: 6 },
                    margin: { left: 40, right: 40 }
                });

                doc.save(generatedReport.replace(/\s+/g, '_') + '_report.pdf');

                SystemModal.open({
                    icon: 'success',
                    title: 'PDF downloaded',
                    text: generatedReport.replace(/\s+/g, '_') + '_report.pdf has been saved.',
                    confirmText: 'OK'
                });
            } catch (err) {
                SystemModal.open({
                    icon: 'danger',
                    title: 'PDF export failed',
                    text: 'Something went wrong while creating the PDF: ' + (err && err.message ? err.message : 'unknown error'),
                    confirmText: 'OK'
                });
            }
        });

        /* ==========================================
           EXPORT CSV — real download
           ========================================== */
        exportCsvBtn.addEventListener('click', function () {
            if (!generatedReport) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No report generated',
                    text: 'Please generate a report first before exporting.',
                    confirmText: 'OK'
                });
                return;
            }

            const data = previewData[generatedReport];
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
            link.download = generatedReport.replace(/\s+/g, '_') + '_report.csv';
            link.click();
            URL.revokeObjectURL(url);

            SystemModal.open({
                icon: 'success',
                title: 'CSV downloaded',
                text: generatedReport.replace(/\s+/g, '_') + '_report.csv has been saved.',
                confirmText: 'OK'
            });
        });

        /* ==========================================
           PRINT — styled modal on missing report
           ========================================== */
        printReportBtn.addEventListener('click', function () {
            if (!generatedReport) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'No report generated',
                    text: 'Please generate a report first before printing.',
                    confirmText: 'OK'
                });
                return;
            }
            window.print();
        });

        /* Initial state */
        updateGuide();
    });
</script>
<?php renderFoot(); ?>
</html>