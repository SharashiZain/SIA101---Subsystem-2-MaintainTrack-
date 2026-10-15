<?php
require_once __DIR__ . '/../../Components/layout.php';

/* =========================================================
   Data
========================================================= */
$requests = [
    [
        'id' => 'MT-00131', 'title' => 'PC won\'t turn on', 'facility' => 'Lab 1', 'equipment' => 'Computer Unit #1',
        'submitter' => 'Juan Dela Cruz', 'date' => 'Oct 14, 2026', 'category' => 'Hardware', 'urgency' => 'High',
        'status' => 'Pending Approval', 'statusKey' => 'pending',
        'description' => 'The PC will not power on even when plugged in. No lights, no fan, no display.',
    ],
    [
        'id' => 'MT-00132', 'title' => 'Broken aircon', 'facility' => 'Lab 1', 'equipment' => 'Aircon Unit 02',
        'submitter' => 'Maria Santos', 'date' => 'Oct 14, 2026', 'category' => 'Facility', 'urgency' => 'Medium',
        'status' => 'Pending Approval', 'statusKey' => 'pending',
        'description' => 'The aircon unit is not cooling properly. Room temperature stays at 32°C even when set to 18°C.',
    ],
    [
        'id' => 'MT-00133', 'title' => 'No internet connection', 'facility' => 'Lab 2', 'equipment' => 'Switch #2',
        'submitter' => 'Carlo Reyes', 'date' => 'Oct 13, 2026', 'category' => 'Network', 'urgency' => 'High',
        'status' => 'Approved', 'statusKey' => 'approved',
        'description' => 'Complete network outage in Lab 2. All 30 workstations are offline.',
    ],
    [
        'id' => 'MT-00134', 'title' => 'Software won\'t launch', 'facility' => 'Lab 1', 'equipment' => 'Computer Unit #7',
        'submitter' => 'Pedro Santos', 'date' => 'Oct 13, 2026', 'category' => 'Software', 'urgency' => 'Low',
        'status' => 'Approved', 'statusKey' => 'approved',
        'description' => 'A specific software application crashes on launch. Other apps work fine.',
    ],
    [
        'id' => 'MT-00135', 'title' => 'Flickering lights', 'facility' => 'Covered Court', 'equipment' => 'Light Fixture A1',
        'submitter' => 'Ana Reyes', 'date' => 'Oct 12, 2026', 'category' => 'Electrical', 'urgency' => 'Low',
        'status' => 'Rejected', 'statusKey' => 'rejected',
        'description' => 'Lights flicker occasionally during class.',
    ],
];

$highlightId = $_GET['id'] ?? '';
$shouldHighlight = isset($_GET['highlight']) && $_GET['highlight'] == '1';

renderHead('Approvals', 'Approvals.css', 'secretary');
?>
<div class="page">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('Approvals', 'Review requests and concerns before assignment', 'secretary'); ?>

    <div class="toolbar">
        <input type="text" placeholder="Search by ID, title, submitter..." id="approvalSearch">

        <select id="approvalStatusFilter">
            <option value="">All Statuses</option>
            <option value="pending">Pending Review</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>

        <select id="approvalUrgencyFilter">
            <option value="">All Urgency</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
        </select>

        <button id="approvalReset">Reset</button>
    </div>

    <section class="approval-list" id="approvalList">
        <?php foreach ($requests as $req): ?>
            <article class="approval-card"
                     data-status="<?= e($req['statusKey']) ?>"
                     data-urgency="<?= strtolower($req['urgency']) ?>"
                     data-id="<?= e($req['id']) ?>">
                <div class="approval-card-main">
                    <div class="approval-header">
                        <span class="approval-id"><?= e($req['id']) ?></span>
                        <span class="urgency urgency-<?= strtolower($req['urgency']) ?>"><?= e($req['urgency']) ?></span>
                    </div>
                    <h3 class="approval-title"><?= e($req['title']) ?></h3>
                    <div class="approval-meta">
                        <?= e($req['facility']) ?> · <?= e($req['equipment']) ?> · <?= e($req['category']) ?>
                    </div>
                    <div class="approval-submitter">
                        Submitted by <strong><?= e($req['submitter']) ?></strong> · <?= e($req['date']) ?>
                    </div>
                    <p class="approval-description"><?= e($req['description']) ?></p>
                </div>
                <div class="approval-card-side">
                    <?php if ($req['statusKey'] === 'pending'): ?>
                        <button class="btn-approve" data-action="approve" data-id="<?= e($req['id']) ?>">Approve</button>
                        <button class="btn-reject" data-action="reject" data-id="<?= e($req['id']) ?>">Reject</button>
                    <?php else: ?>
                        <span class="status-pill status-<?= e($req['statusKey']) ?>"><?= e($req['status']) ?></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <div id="approvalNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
        <p style="font-size: 15px; font-weight: 600;">No submissions match your filters.</p>
    </div>

    </div><!-- /.one-card -->
</div>

<!-- DECISION MODAL -->
<div class="static-modal" id="decisionModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3 id="decisionModalTitle">Approve Request</h3>
            <button type="button" class="modal-close" id="decisionModalClose">✕</button>
        </div>

        <p class="modal-subtitle">Request <strong id="decisionModalId">—</strong></p>
        <p class="modal-role" id="decisionModalTitle2">—</p>

        <div class="modal-field">
            <label for="decisionNotes">Notes (optional)</label>
            <textarea id="decisionNotes" placeholder="Add any notes for this decision..."></textarea>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="decisionModalCancel">Cancel</button>
            <button type="button" class="modal-save" id="decisionModalConfirm">Confirm</button>
        </div>
    </div>
</div>

<!-- SYSTEM MODAL -->
<div class="system-modal-overlay" id="systemModal" role="dialog" aria-modal="true" style="display: none;">
    <div class="system-modal">
        <div class="system-modal-icon" id="systemModalIcon"></div>
        <h3 class="system-modal-title" id="systemModalTitle">Title</h3>
        <p class="system-modal-text" id="systemModalText">Message</p>
        <div class="system-modal-actions" id="systemModalActions"></div>
    </div>
</div>

<script>
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

    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('approvalSearch');
        const statusFilter = document.getElementById('approvalStatusFilter');
        const urgencyFilter = document.getElementById('approvalUrgencyFilter');
        const reset = document.getElementById('approvalReset');
        const noResults = document.getElementById('approvalNoResults');
        const cards = document.querySelectorAll('.approval-card');

        /* =========================================================
           HIGHLIGHT THE TARGET REQUEST
        ========================================================= */
        const params = new URLSearchParams(window.location.search);
        const highlightId = params.get('id');
        const shouldHighlight = params.get('highlight') === '1';

        if (shouldHighlight && highlightId) {
            const target = document.querySelector(`.approval-card[data-id="${highlightId}"]`);

            if (target) {
                setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    void target.offsetWidth;
                    target.classList.add('highlighted');

                    setTimeout(function () {
                        target.classList.remove('highlighted');
                    }, 2600);
                }, 300);
            }
        }

        function applyFilters() {
            const q = search.value.toLowerCase();
            const s = statusFilter.value;
            const u = urgencyFilter.value;
            let visible = 0;

            cards.forEach(function (card) {
                const text = card.textContent.toLowerCase();
                const cardStatus = card.dataset.status || '';
                const cardUrgency = card.dataset.urgency || '';
                const show = (!q || text.includes(q)) && (!s || cardStatus === s) && (!u || cardUrgency === u);
                card.style.display = show ? 'grid' : 'none';
                if (show) visible++;
            });

            noResults.style.display = visible === 0 ? 'block' : 'none';
        }

        search.addEventListener('input', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        urgencyFilter.addEventListener('change', applyFilters);
        reset.addEventListener('click', function () {
            search.value = '';
            statusFilter.value = '';
            urgencyFilter.value = '';
            applyFilters();
        });

        const decisionModal = document.getElementById('decisionModal');
        const decisionModalTitle = document.getElementById('decisionModalTitle');
        const decisionModalId = document.getElementById('decisionModalId');
        const decisionModalTitle2 = document.getElementById('decisionModalTitle2');
        const decisionNotes = document.getElementById('decisionNotes');
        const decisionModalConfirm = document.getElementById('decisionModalConfirm');
        let currentAction = 'approve';
        let currentCard = null;
        let currentId = null;

        function openDecisionModal(action, card, id, title) {
            currentAction = action;
            currentCard = card;
            currentId = id;

            if (action === 'approve') {
                decisionModalTitle.textContent = 'Approve Request';
                decisionModalConfirm.textContent = 'Yes, Approve';
                decisionModalConfirm.className = 'modal-save';
            } else {
                decisionModalTitle.textContent = 'Reject Request';
                decisionModalConfirm.textContent = 'Yes, Reject';
                decisionModalConfirm.className = 'modal-save modal-save-danger';
            }

            decisionModalId.textContent = id;
            decisionModalTitle2.textContent = title;
            decisionNotes.value = '';
            decisionModal.style.display = 'flex';
        }

        function closeDecisionModal() {
            decisionModal.style.display = 'none';
            currentCard = null;
            currentId = null;
        }

        document.querySelectorAll('.btn-approve').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const card = this.closest('.approval-card');
                const id = this.dataset.id;
                const title = card.querySelector('.approval-title').textContent;
                openDecisionModal('approve', card, id, title);
            });
        });

        document.querySelectorAll('.btn-reject').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const card = this.closest('.approval-card');
                const id = this.dataset.id;
                const title = card.querySelector('.approval-title').textContent;
                openDecisionModal('reject', card, id, title);
            });
        });

        document.getElementById('decisionModalClose').addEventListener('click', closeDecisionModal);
        document.getElementById('decisionModalCancel').addEventListener('click', closeDecisionModal);

        decisionModalConfirm.addEventListener('click', function () {
            if (!currentCard) return;
            const notes = decisionNotes.value.trim();
            const side = currentCard.querySelector('.approval-card-side');
            const newStatus = currentAction === 'approve' ? 'Approved' : 'Rejected';
            const newStatusKey = currentAction === 'approve' ? 'approved' : 'rejected';

            currentCard.dataset.status = newStatusKey;
            side.innerHTML = `<span class="status-pill status-${newStatusKey}">${newStatus}</span>`;

            closeDecisionModal();
            applyFilters();

            SystemModal.open({
                icon: currentAction === 'approve' ? 'success' : 'danger',
                title: currentAction === 'approve' ? 'Request Approved' : 'Request Rejected',
                text: `Request ${currentId} has been ${newStatus.toLowerCase()}.${notes ? ' Notes: ' + notes : ''}`,
                confirmText: 'OK'
            });
        });

        decisionModal.addEventListener('click', function (e) {
            if (e.target === decisionModal) closeDecisionModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDecisionModal();
        });
    });
</script>
<?php renderFoot(); ?>
</html>