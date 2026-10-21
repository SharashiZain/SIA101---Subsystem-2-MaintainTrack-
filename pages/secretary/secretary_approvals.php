<?php
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../includes/PriorityScore.php';

/* =========================================================
   Supabase helper (server-side only)
   Uses the secret key so row-level security does not hide rows from
   this admin page. The key stays in PHP and is never sent to the browser.
   (Your supabaseRequest() in Config/supabase.php always sends the
   publishable key, so this page uses its own small helper.)
========================================================= */
function approvalsRest(string $path, string $method = 'GET', ?array $body = null, array $extraHeaders = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL extension is not enabled.'];
    }

    $headers = array_merge([
        'apikey: ' . SUPABASE_SECRET_KEY,
        'Accept: application/json',
    ], $extraHeaders);

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ];

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;

    $ch = curl_init(rtrim(SUPABASE_URL, '/') . $path);
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $curlErr ?: 'Unable to connect to Supabase.'];
    }

    $decoded = json_decode($response, true);
    $ok = $status >= 200 && $status < 300;

    return [
        'ok'     => $ok,
        'status' => $status,
        'data'   => $decoded,
        'error'  => $ok ? null : (is_array($decoded) ? ($decoded['message'] ?? $response) : $response),
    ];
}

/* =========================================================
   Save an approve / reject decision (called by the modal via fetch)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'decide') {
    header('Content-Type: application/json');

    $in        = json_decode(file_get_contents('php://input'), true) ?: [];
    $displayId = trim($in['id'] ?? '');
    $decision  = $in['decision'] ?? '';
    $notes     = trim($in['notes'] ?? '');

    if (!preg_match('/^MT-\d+$/', $displayId) || !in_array($decision, ['approve', 'reject'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
        exit;
    }

    // Record who decided, if the logged-in user id is available in the session
    $sessionUser = $_SESSION['user_id'] ?? null;
    $approvedBy  = (is_string($sessionUser) && preg_match('/^[0-9a-f-]{36}$/i', $sessionUser)) ? $sessionUser : null;

    // Only update rows that are still waiting for a decision (older rows may have an empty approval_status, which also counts as Pending)
    $result = approvalsRest(
        '/rest/v1/maintenance_requests?display_id=eq.' . rawurlencode($displayId) . '&or=(approval_status.eq.Pending,approval_status.is.null)',
        'PATCH',
        [
            'approval_status'  => $decision === 'approve' ? 'Approved' : 'Rejected',
            'approval_date'    => gmdate('c'),
            'approved_by'      => $approvedBy,
            'rejection_reason' => ($decision === 'reject' && $notes !== '') ? $notes : null,
        ],
        ['Prefer: return=representation']
    );

    if (!$result['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database error. Please try again.']);
        exit;
    }

    if (empty($result['data'])) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'This request was already decided or no longer exists.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

/* =========================================================
   Data (loaded from Supabase)
========================================================= */
$requests = [];
$loadError = '';

$reqRes = approvalsRest(
    '/rest/v1/maintenance_requests?select=request_id,display_id,concern,description,category,priority,approval_status,rejection_reason,date_reported,specific_area,equipment_id,facility_id,reported_by,created_at&order=created_at.desc'
);
$facRes = approvalsRest('/rest/v1/facilities?select=facility_id,name');
$eqRes  = approvalsRest('/rest/v1/equipment?select=equipment_id,name');
$usrRes = approvalsRest('/rest/v1/users?select=user_id,full_name');

if (!$reqRes['ok'] || !is_array($reqRes['data'])) {
    $loadError = 'Could not load requests from the database.';
} else {
    $facilities = [];
    $equipment  = [];
    $users      = [];

    if ($facRes['ok'] && is_array($facRes['data'])) foreach ($facRes['data'] as $f) $facilities[$f['facility_id']] = $f['name'];
    if ($eqRes['ok']  && is_array($eqRes['data']))  foreach ($eqRes['data']  as $q) $equipment[$q['equipment_id']] = $q['name'];
    if ($usrRes['ok'] && is_array($usrRes['data'])) foreach ($usrRes['data'] as $u) $users[$u['user_id']] = $u['full_name'];

    $manila = new DateTimeZone('Asia/Manila');

    foreach ($reqRes['data'] as $row) {
        $approval  = $row['approval_status'] ?: 'Pending';
        $statusKey = strtolower($approval); // pending | approved | rejected

        $facility = $facilities[$row['facility_id'] ?? ''] ?? 'Unknown facility';
        if (!empty($row['specific_area'])) {
            $facility .= ' – ' . $row['specific_area'];
        }

        $date = '';
        if (!empty($row['date_reported'])) {
            $dt = new DateTime($row['date_reported']);
            $dt->setTimezone($manila);
            $date = $dt->format('M j, Y');
        }

        $requests[] = [
            'id'               => $row['display_id'],
            'title'            => $row['concern'],
            'facility'         => $facility,
            'equipment'        => $equipment[$row['equipment_id'] ?? ''] ?? 'N/A',
            'submitter'        => $users[$row['reported_by'] ?? ''] ?? 'Unknown',
            'date'             => $date,
            'category'         => $row['category'] ?: 'General',
            'urgency'          => $row['priority'] ?: 'Medium',
            'status'           => $statusKey === 'pending' ? 'Pending Approval' : $approval,
            'statusKey'        => $statusKey,
            'description'      => $row['description'] ?: '',
            // extra fields (not used by the cards, handy for later)
            'request_id'       => $row['request_id'],
            'equipment_id'     => $row['equipment_id'],
            'rejection_reason' => $row['rejection_reason'],
        ];
    }
}

/* =========================================================
   Priority queue
   Requests waiting for approval are ranked by priority score (highest first).
   Requests that already have a decision are listed after them, newest first.
========================================================= */
$allRequests = $requests; // recurrence is counted across every request, decided or not
$pendingRequests = array_values(array_filter($requests, fn($r) => $r['statusKey'] === 'pending'));
$decidedRequests = array_values(array_filter($requests, fn($r) => $r['statusKey'] !== 'pending'));
usort($decidedRequests, fn($a, $b) => priorityRequestTime($b) <=> priorityRequestTime($a));

$queue = [];
foreach (buildPriorityQueue($pendingRequests) as $item) $queue[] = $item;
$requests = array_merge($queue, $decidedRequests);

$highlightId = $_GET['id'] ?? '';
$shouldHighlight = isset($_GET['highlight']) && $_GET['highlight'] == '1';

renderHead('Approvals', 'Approvals.css', 'secretary');
?>
<style>
    /* Priority score badge (can be moved into Approvals.css) */
    .priority-rank { font-size: 11px; font-weight: 800; color: #687985; letter-spacing: .04em; }
    .priority-score {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 999px;
        background: #eef3f8; border: 1px solid #cfdce8; color: #12355f;
        font-size: 11px; font-weight: 800; letter-spacing: .03em; cursor: help;
    }
</style>
<div class="page">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('Approvals', 'Review requests and concerns before assignment', 'secretary'); ?>

    <div class="toolbar">
        <input type="text" placeholder="Search by ID, title, submitter..." id="approvalSearch">

        <select id="approvalStatusFilter">
            <option value="">All Statuses</option>
            <option value="pending" selected>Pending Review</option>
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

    <?php if ($loadError): ?>
        <div style="padding: 14px 18px; margin-bottom: 14px; border-radius: 10px; background: #fee2e2; color: #991b1b; font-size: 13px; font-weight: 600;">
            <?= e($loadError) ?>
        </div>
    <?php endif; ?>

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
                        <?php if (isset($req['_score'])): ?>
                            <span class="priority-score" title="<?= e(priorityScoreTooltip($req)) ?>">Score <?= number_format($req['_score'], 2) ?></span>
                            <span class="priority-rank">#<?= (int)$req['_rank'] ?> in queue</span>
                        <?php endif; ?>
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

    <div id="approvalNoResults" style="display: <?= (empty($requests) && !$loadError) ? 'block' : 'none' ?>; text-align: center; padding: 40px; color: #687985;">
        <p style="font-size: 15px; font-weight: 600;"><?= empty($requests) ? 'No requests have been submitted yet.' : 'No submissions match your filters.' ?></p>
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

            noResults.style.display = (cards.length > 0 && visible === 0) ? 'block' : 'none';
        }

        search.addEventListener('input', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        urgencyFilter.addEventListener('change', applyFilters);
        reset.addEventListener('click', function () {
            search.value = '';
            statusFilter.value = 'pending'; // back to the default view
            urgencyFilter.value = '';
            applyFilters();
        });

        /* Starting view: Pending Review by default.
           - ?status=pending|approved|rejected (dashboard stat chips) overrides the default.
           - A highlighted link to an already-decided request switches to All Statuses
             so the card is visible and can be scrolled to. */
        const statusParam = params.get('status');
        const highlightTarget = (shouldHighlight && highlightId)
            ? document.querySelector(`.approval-card[data-id="${highlightId}"]`)
            : null;

        if (statusParam && ['pending', 'approved', 'rejected'].includes(statusParam)) {
            statusFilter.value = statusParam;
        } else if (highlightTarget && highlightTarget.dataset.status !== 'pending') {
            statusFilter.value = '';
        }
        applyFilters();

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

        decisionModalConfirm.addEventListener('click', async function () {
            if (!currentCard) return;

            // Keep local copies because closeDecisionModal() clears the shared ones
            const card = currentCard;
            const id = currentId;
            const action = currentAction;
            const notes = decisionNotes.value.trim();

            decisionModalConfirm.disabled = true;

            // Save the decision to the database first
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('action', 'decide');

                const res = await fetch(url.toString(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, decision: action, notes: notes })
                });
                const data = await res.json().catch(function () { return {}; });

                if (!res.ok || !data.ok) {
                    throw new Error(data.error || 'Could not save the decision.');
                }
            } catch (err) {
                decisionModalConfirm.disabled = false;
                closeDecisionModal();
                SystemModal.open({
                    icon: 'warning',
                    title: 'Could not save decision',
                    text: err.message,
                    confirmText: 'OK'
                });
                return;
            }

            decisionModalConfirm.disabled = false;

            const side = card.querySelector('.approval-card-side');
            const newStatus = action === 'approve' ? 'Approved' : 'Rejected';
            const newStatusKey = action === 'approve' ? 'approved' : 'rejected';

            card.dataset.status = newStatusKey;
            side.innerHTML = `<span class="status-pill status-${newStatusKey}">${newStatus}</span>`;

            // A decided request leaves the queue, so remove its score and rank badges.
            card.querySelectorAll('.priority-score, .priority-rank').forEach(function (el) { el.remove(); });

            closeDecisionModal();
            applyFilters();

            SystemModal.open({
                icon: action === 'approve' ? 'success' : 'danger',
                title: action === 'approve' ? 'Request Approved' : 'Request Rejected',
                text: `Request ${id} has been ${newStatus.toLowerCase()}.${notes ? ' Notes: ' + notes : ''}`,
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