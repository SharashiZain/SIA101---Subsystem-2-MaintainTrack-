<?php
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';

/* =========================================================
   Supabase helper (server-side only)
   Uses the secret key so row-level security does not hide rows from
   this admin page. The key stays in PHP and is never sent to the browser.
========================================================= */
function historyRest(string $path): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'data' => null];
    }

    $ch = curl_init(rtrim(SUPABASE_URL, '/') . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'data' => null];
    }

    return [
        'ok'   => $status >= 200 && $status < 300,
        'data' => json_decode($response, true),
    ];
}

/* PATCH helper used when a past decision is changed */
function historyPatch(string $path, array $body): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL extension is not enabled.'];
    }

    $ch = curl_init(rtrim(SUPABASE_URL, '/') . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Accept: application/json',
            'Content-Type: application/json',
            'Prefer: return=representation',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Unable to connect to Supabase.'];
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
   Change a past decision (called by the "Change to ..." button via fetch)
   Approved -> Rejected, or Rejected -> Approved. Only rows that are
   currently decided and not already in the new state are updated.
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'change') {
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

    // Record who changed the decision, if the logged-in user id is available in the session
    $sessionUser = $_SESSION['user_id'] ?? null;
    $changedBy   = (is_string($sessionUser) && preg_match('/^[0-9a-f-]{36}$/i', $sessionUser)) ? $sessionUser : null;

    $newStatus = $decision === 'approve' ? 'Approved' : 'Rejected';

    $result = historyPatch(
        '/rest/v1/maintenance_requests'
        . '?display_id=eq.' . rawurlencode($displayId)
        . '&approval_status=in.(Approved,Rejected)'
        . '&approval_status=neq.' . $newStatus,
        [
            'approval_status'  => $newStatus,
            'approval_date'    => gmdate('c'),
            'approved_by'      => $changedBy,
            'rejection_reason' => ($decision === 'reject' && $notes !== '') ? $notes : null,
        ]
    );

    if (!$result['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database error. Please try again.']);
        exit;
    }

    if (empty($result['data'])) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'This request is already ' . strtolower($newStatus) . ' or no longer exists.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

/* =========================================================
   Data (every request the secretary has approved or rejected)
========================================================= */
$history = [];
$loadError = '';

$reqRes = historyRest(
    '/rest/v1/maintenance_requests'
    . '?select=display_id,concern,reported_by,date_reported,approval_status,approval_date,rejection_reason,created_at'
    . '&approval_status=in.(Approved,Rejected)'
    . '&order=approval_date.desc.nullslast,created_at.desc'
);
$usrRes = historyRest('/rest/v1/users?select=user_id,full_name');

if (!$reqRes['ok'] || !is_array($reqRes['data'])) {
    $loadError = 'Could not load history from the database.';
} else {
    $users = [];
    if ($usrRes['ok'] && is_array($usrRes['data'])) {
        foreach ($usrRes['data'] as $u) $users[$u['user_id']] = $u['full_name'];
    }

    $manila = new DateTimeZone('Asia/Manila');
    $formatDate = function (?string $value) use ($manila): string {
        if (empty($value)) return '';
        $dt = new DateTime($value);
        $dt->setTimezone($manila);
        return $dt->format('M j, Y');
    };

    foreach ($reqRes['data'] as $row) {
        $decision = $row['approval_status']; // Approved | Rejected

        $history[] = [
            'id'          => $row['display_id'],
            'title'       => $row['concern'],
            'submitter'   => $users[$row['reported_by'] ?? ''] ?? 'Unknown',
            'date'        => $formatDate($row['date_reported'] ?? null),
            'decidedOn'   => $formatDate($row['approval_date'] ?? null),
            'reason'      => $row['rejection_reason'] ?? '',
            'decision'    => $decision,
            'decisionKey' => strtolower($decision),
        ];
    }
}

renderHead('History', 'History.css', 'secretary');
?>
<style>
    /* "Change to ..." button and confirmation dialogs */
    .history-side .btn-change {
        display: inline-flex; align-items: center; margin-left: 8px;
        padding: 7px 12px; border: 1px solid #b7d0e0; border-radius: 8px;
        background: #ffffff; color: #1769aa; font-size: 12px; font-weight: 700;
        cursor: pointer; transition: background .15s, border-color .15s;
    }
    .history-side .btn-change:hover { background: #eef5fa; border-color: #1769aa; }
    .history-side .btn-change:focus-visible { outline: 2px solid #1769aa; outline-offset: 2px; }

    .change-modal {
        position: fixed; inset: 0; z-index: 10000; display: flex;
        align-items: center; justify-content: center; padding: 20px;
        background: rgba(15, 23, 42, .58);
    }
    .change-modal[hidden] { display: none !important; }
    .change-card {
        width: min(440px, 100%); background: #ffffff; border-radius: 16px;
        padding: 26px 26px 22px; box-shadow: 0 22px 60px rgba(0, 0, 0, .22);
    }
    .change-card h3 { margin: 0 0 10px; font-size: 19px; color: #1f2937; }
    .change-text { margin: 0; font-size: 14px; line-height: 1.6; color: #64748b; overflow-wrap: anywhere; }
    .change-field { margin-top: 16px; }
    .change-field[hidden] { display: none !important; }
    .change-field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; color: #111c25; }
    .change-field textarea {
        width: 100%; box-sizing: border-box; min-height: 80px; resize: vertical;
        border: 1px solid #d9e5ec; border-radius: 8px; padding: 10px 12px;
        font-family: inherit; font-size: 13px;
    }
    .change-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    .change-actions button {
        min-width: 110px; padding: 10px 18px; border: 0; border-radius: 8px;
        font-size: 13px; font-weight: 700; cursor: pointer;
    }
    .change-cancel { background: #e5e7eb; color: #374151; }
    .change-save { background: #1769aa; color: #ffffff; }
    .change-save-danger { background: #a83333; }
    .change-save:disabled { opacity: .6; cursor: default; }
</style>
<div class="page">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('History', 'Previously reviewed submissions', 'secretary'); ?>

    <section class="panel">
        <div class="panel-head">
            <h2>Reviewed Submissions</h2>
        </div>

        <?php if ($loadError): ?>
            <div style="padding: 14px 18px; margin-bottom: 14px; border-radius: 10px; background: #fee2e2; color: #991b1b; font-size: 13px; font-weight: 600;">
                <?= e($loadError) ?>
            </div>
        <?php endif; ?>

        <div class="history-list">
            <?php foreach ($history as $item): ?>
                <div class="history-card" data-decision="<?= e($item['decisionKey']) ?>" data-id="<?= e($item['id']) ?>">
                    <div class="history-main">
                        <div class="history-id"><?= e($item['id']) ?></div>
                        <div class="history-title"><?= e($item['title']) ?></div>
                        <div class="history-meta">
                            Submitted by <?= e($item['submitter']) ?> · <?= e($item['date']) ?>
                            <?php if ($item['decidedOn'] !== ''): ?>
                                · <?= e($item['decision']) ?> on <?= e($item['decidedOn']) ?>
                            <?php endif; ?>
                            <?php if ($item['decisionKey'] === 'rejected' && $item['reason'] !== ''): ?>
                                · Reason: <?= e($item['reason']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="history-side">
                        <span class="status-pill status-<?= e($item['decisionKey']) ?>"><?= e($item['decision']) ?></span>
                        <?php $newDecision = $item['decisionKey'] === 'approved' ? 'reject' : 'approve'; ?>
                        <button type="button"
                                class="btn-change"
                                data-id="<?= e($item['id']) ?>"
                                data-title="<?= e($item['title']) ?>"
                                data-current="<?= e($item['decision']) ?>"
                                data-decision="<?= e($newDecision) ?>">
                            Change to <?= $newDecision === 'approve' ? 'Approved' : 'Rejected' ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($history) && !$loadError): ?>
                <div style="text-align: center; padding: 30px; color: #687985; font-size: 14px; font-weight: 600;">
                    No approved or rejected requests yet.
                </div>
            <?php endif; ?>
        </div>
    </section>

    </div><!-- /.one-card -->
</div>

<!-- CHANGE DECISION: step 1 (choose reason) -->
<div class="change-modal" id="changeModal" hidden role="dialog" aria-modal="true" aria-labelledby="changeTitle">
    <div class="change-card">
        <h3 id="changeTitle">Change decision</h3>
        <p class="change-text" id="changeText"></p>
        <div class="change-field" id="changeNotesWrap">
            <label for="changeNotes">Reason (optional)</label>
            <textarea id="changeNotes" placeholder="Add a reason for this decision..."></textarea>
        </div>
        <div class="change-actions">
            <button type="button" class="change-cancel" id="changeCancel">Cancel</button>
            <button type="button" class="change-save" id="changeContinue">Continue</button>
        </div>
    </div>
</div>

<!-- CHANGE DECISION: step 2 (double check) -->
<div class="change-modal" id="changeConfirmModal" hidden role="dialog" aria-modal="true" aria-labelledby="changeConfirmTitle">
    <div class="change-card">
        <h3 id="changeConfirmTitle">Are you sure?</h3>
        <p class="change-text" id="changeConfirmText"></p>
        <div class="change-actions">
            <button type="button" class="change-cancel" id="changeConfirmBack">Go Back</button>
            <button type="button" class="change-save change-save-danger" id="changeConfirmYes">Yes, Change It</button>
        </div>
    </div>
</div>

<script>
    /* When opened from a notification (?id=MT-00133&highlight=1), scroll to that request and flash it. */
    document.addEventListener('DOMContentLoaded', function () {
        const params = new URLSearchParams(window.location.search);
        const id = params.get('id');
        if (params.get('highlight') === '1' && id) {
            const target = document.querySelector('.history-card[data-id="' + CSS.escape(id) + '"]');
            if (target) {
                setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.animate([
                        { boxShadow: '0 0 0 0 rgba(23, 105, 170, 0.55)', backgroundColor: '#dbeafe' },
                        { boxShadow: '0 0 0 10px rgba(23, 105, 170, 0.25)', backgroundColor: '#e8f1fb', offset: 0.4 },
                        { boxShadow: '0 0 0 0 rgba(23, 105, 170, 0)', backgroundColor: '#ffffff' }
                    ], { duration: 2500, easing: 'ease-out' });
                }, 300);
            }
        }

        /* =========================================================
           CHANGE A PAST DECISION (Approved <-> Rejected)
        ========================================================= */
        const changeModal = document.getElementById('changeModal');
        const changeTitle = document.getElementById('changeTitle');
        const changeText = document.getElementById('changeText');
        const changeNotesWrap = document.getElementById('changeNotesWrap');
        const changeNotes = document.getElementById('changeNotes');
        const changeContinue = document.getElementById('changeContinue');
        const changeConfirmModal = document.getElementById('changeConfirmModal');
        const changeConfirmText = document.getElementById('changeConfirmText');
        const changeConfirmYes = document.getElementById('changeConfirmYes');

        let pendingId = null;
        let pendingDecision = null; // 'approve' | 'reject'
        let pendingTitle = '';

        function closeChangeModals() {
            changeModal.hidden = true;
            changeConfirmModal.hidden = true;
        }

        document.querySelectorAll('.btn-change').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                pendingId = this.dataset.id;
                pendingDecision = this.dataset.decision;
                pendingTitle = this.dataset.title;

                const newLabel = pendingDecision === 'approve' ? 'Approved' : 'Rejected';
                const currentLabel = this.dataset.current;

                changeTitle.textContent = 'Change decision to ' + newLabel;
                changeText.textContent = 'Request ' + pendingId + ' (' + pendingTitle + ') is currently ' + currentLabel + '. Change it to ' + newLabel + '?';
                changeNotes.value = '';
                changeNotesWrap.hidden = pendingDecision !== 'reject'; // reasons only apply to rejections
                changeModal.hidden = false;
                changeContinue.focus();
            });
        });

        document.getElementById('changeCancel').addEventListener('click', closeChangeModals);
        document.getElementById('changeConfirmBack').addEventListener('click', function () {
            changeConfirmModal.hidden = true;
        });

        // Step 1 -> step 2: double check before anything is saved
        changeContinue.addEventListener('click', function () {
            const newLabel = pendingDecision === 'approve' ? 'Approved' : 'Rejected';
            changeConfirmText.textContent = 'Request ' + pendingId + ' will be changed to ' + newLabel + '. Do you really want to change this decision?';
            changeConfirmModal.hidden = false;
            changeConfirmYes.focus();
        });

        // Step 2: save to the database
        changeConfirmYes.addEventListener('click', async function () {
            const id = pendingId;
            const decision = pendingDecision;
            const notes = changeNotes.value.trim();

            changeConfirmYes.disabled = true;
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('action', 'change');

                const res = await fetch(url.toString(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, decision: decision, notes: notes })
                });
                const data = await res.json().catch(function () { return {}; });

                if (!res.ok || !data.ok) {
                    throw new Error(data.error || 'Could not change the decision.');
                }

                // Reload so the list, the status pill and the sort order are up to date
                window.location.reload();
            } catch (err) {
                alert(err.message);
            } finally {
                changeConfirmYes.disabled = false;
                closeChangeModals();
            }
        });

        // Close the dialogs when clicking the dark background or pressing Escape
        [changeModal, changeConfirmModal].forEach(function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeChangeModals();
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeChangeModals();
        });
    });
</script>
<?php renderFoot(); ?>
</html>