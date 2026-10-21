<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Components/notification_view.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================================================
   Supabase helper (server-side only)
   The key stays in PHP and is never sent to the browser.
========================================================= */
function mnRest(string $endpoint): array
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
        return ['success' => false, 'data' => null];
    }

    $ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body   = curl_exec($ch);
    $err    = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $err !== '') {
        return ['success' => false, 'data' => null];
    }

    return [
        'success' => $status >= 200 && $status < 300,
        'data'    => json_decode($body, true),
    ];
}

/* Returns only real rows (arrays). An error response from Supabase becomes an empty list,
   so the loops below never index a string. */
function mnRows(string $endpoint): array
{
    $r = mnRest($endpoint);
    if (!$r['success'] || !is_array($r['data'])) return [];
    return array_values(array_filter($r['data'], 'is_array'));
}

function mnIsUuid(string $value): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
}

/* =========================================================
   Build notifications for the logged-in maintenance worker.

   Every notice is checked against the request's CURRENT state:
   - "Assigned"  : shown only while the request is still assigned to this worker
                   and its status is still Assigned.
   - Status items: the worker's own progress (In Progress, Completed, Fail to Fix).
                   Rejected is not shown.
   - "New Approved Request": shown only while the request is approved, has no
                   worker, and is not Completed, Rejected, or Cancelled.

   Unread means the event happened in the last 24 hours.
   Clicking an item still marks it read in the browser only.
========================================================= */
$manila  = new DateTimeZone('Asia/Manila');
$cutoff  = time() - 86400;
$events  = [];
$userKey = 'guest';

/* ---- Resolve the logged-in worker ---- */
$me = null;
$sessionId       = trim((string) ($_SESSION['user_id'] ?? ''));
$sessionUsername = trim((string) ($_SESSION['username'] ?? $_SESSION['user'] ?? ''));

if (mnIsUuid($sessionId)) {
    $rows = mnRows('users?user_id=eq.' . rawurlencode($sessionId) . '&select=user_id,role&limit=1');
    $me   = $rows[0] ?? null;
}
if (!$me && $sessionUsername !== '') {
    $lookupUsername = $sessionUsername === 'user' ? 'maintenance' : $sessionUsername;
    $rows = mnRows('users?username=eq.' . rawurlencode($lookupUsername) . '&select=user_id,role&limit=1');
    $me   = $rows[0] ?? null;
}

if ($me && strtolower((string) ($me['role'] ?? '')) === 'maintenance') {
    $myId    = (string) $me['user_id'];
    $userKey = $myId;

    $makeEvent = function (string $id, string $title, string $text, string $tone, string $category, ?string $when) use ($manila, $cutoff): ?array {
        if (empty($when)) return null;
        try {
            $dt = new DateTime($when);
        } catch (Throwable $e) {
            return null;
        }
        $dt->setTimezone($manila);
        $ts = $dt->getTimestamp();
        return [
            'id'       => md5($id),
            'ts'       => $ts,
            'tone'     => $tone,
            'title'    => $title,
            'text'     => $text,
            'time'     => $dt->format('M j, Y · g:i A'),
            'unread'   => $ts >= $cutoff,
            'category' => $category,
        ];
    };

    $labelOf = function (array $req): string {
        $displayId = (string) ($req['display_id'] ?? 'A request');
        $concern   = trim((string) ($req['concern'] ?? ''));
        return $concern !== '' ? $displayId . ' – ' . $concern : $displayId;
    };

    /* ---- 1) This worker's requests, as they are right now ---- */
    $myRequests = mnRows(
        'maintenance_requests?assigned_to=eq.' . rawurlencode($myId)
        . '&select=request_id,display_id,concern,status,date_assigned,date_started,date_completed,updated_at'
        . '&order=updated_at.desc.nullslast&limit=200'
    );

    $liveById = [];
    foreach ($myRequests as $req) {
        if (!empty($req['request_id'])) $liveById[(string) $req['request_id']] = $req;
    }

    /* ---- 2) New task notices: every request currently assigned to this worker that is still "Assigned".
       The time is when the Admin assigned it (date_assigned). A matching row in the notifications table
       is used for its wording when one exists, but it is not required. ---- */
    $notifRows = mnRows(
        'notifications?recipient_user_id=eq.' . rawurlencode($myId)
        . '&select=notification_id,related_request_id,title,message,created_at'
        . '&order=created_at.desc.nullslast&limit=200'
    );

    $assignedNow = [];
    foreach ($myRequests as $req) {
        $rid = (string) ($req['request_id'] ?? '');
        if ($rid === '') continue;
        if (strtolower(trim((string) ($req['status'] ?? ''))) !== 'assigned') continue; // worker already moved it on
        $assignedNow[$rid] = $req;
    }
    $assignNoticeById = [];
    foreach ($notifRows as $n) {
        $rid = (string) ($n['related_request_id'] ?? '');
        if ($rid === '' || !isset($assignedNow[$rid]) || isset($assignNoticeById[$rid])) continue;
        if (stripos((string) ($n['title'] ?? ''), 'assign') === false) continue;
        $assignNoticeById[$rid] = $n;
    }
    foreach ($assignedNow as $rid => $req) {
        $notice = $assignNoticeById[$rid] ?? null;
        $when   = $notice['created_at'] ?? ($req['date_assigned'] ?? ($req['updated_at'] ?? null));
        $ev = $makeEvent(
            'assign-' . $rid,
            'New Task Assigned',
            trim((string) ($notice['message'] ?? '')) ?: ($labelOf($req) . ' was assigned to you. Please check the task details.'),
            'blue',
            'assignment',
            $when
        );
        if ($ev) $events[] = $ev;
    }

    /* ---- 3) Status items: the worker's own progress on each live request ---- */
    foreach ($myRequests as $req) {
        $rid    = (string) ($req['request_id'] ?? '');
        $status = strtolower(trim((string) ($req['status'] ?? '')));
        $label  = $labelOf($req);
        $when   = $req['updated_at'] ?? null;
        $stamp  = $rid . '|' . $status . '|' . (string) $when;

        $ev = null;
        if ($status === 'in progress') {
            $ev = $makeEvent('status-' . $stamp, 'Maintenance Request Updated',
                $label . ' is now In Progress.', 'purple', 'status', $req['date_started'] ?? $when);
        } elseif ($status === 'completed') {
            $ev = $makeEvent('status-' . $stamp, 'Maintenance Request Completed',
                $label . ' has been completed.', 'orange', 'status', $req['date_completed'] ?? $when);
        } elseif ($status === 'fail to fix') {
            $ev = $makeEvent('status-' . $stamp, 'Maintenance Request Updated',
                $label . ' could not be fixed yet. Check the updates on the request.', 'red', 'status', $when);
        }
        if ($ev) $events[] = $ev;
    }

    /* ---- 4) Approved requests still waiting for a worker (shown to every worker) ---- */
    $poolRows = mnRows(
        'maintenance_requests?assigned_to=is.null&approval_status=eq.Approved'
        . '&status=not.in.(Completed,Rejected,Cancelled)'
        . '&select=request_id,display_id,concern,approval_date,created_at'
        . '&order=approval_date.desc.nullslast&limit=100'
    );
    foreach ($poolRows as $req) {
        if (empty($req['request_id'])) continue;
        $ev = $makeEvent(
            'pool-' . (string) $req['request_id'],
            'New Approved Request',
            $labelOf($req) . ' has been approved and is waiting for assignment.',
            'green',
            'request',
            $req['approval_date'] ?? ($req['created_at'] ?? null)
        );
        if ($ev) $events[] = $ev;
    }
}

/* Newest first, capped to keep the page light */
usort($events, fn($a, $b) => $b['ts'] <=> $a['ts']);
$events = array_slice($events, 0, 100);

$notifications = [];
$ids           = [];
foreach ($events as $ev) {
    $ids[] = $ev['id'];
    $notifications[] = [
        'tone'     => $ev['tone'],
        'title'    => $ev['title'],
        'text'     => $ev['text'],
        'time'     => $ev['time'],
        'unread'   => $ev['unread'],
        'category' => $ev['category'],
    ];
}

/* Lightweight feed used by the background check below */
if (isset($_GET['feed'])) {
    header('Content-Type: application/json');
    echo json_encode(['ids' => $ids]);
    exit;
}

renderHead('Notifications', 'assets/css/NotificationShared.css', 'maintenance');

renderNotificationPage('maintenance', 'Maintenance Personnel Portal • Barangay Gulod', $notifications, [
    ['key' => 'assignment', 'label' => 'New Tasks'],
    ['key' => 'status',     'label' => 'Status Updates'],
    ['key' => 'request',    'label' => 'New Requests'],
]);
?>
<script>
    /* Sound alert and live check.
    - Plays notif.mp3 when a page load finds events that were not there last time.
    - Checks for new events every 30 seconds and reloads the list when one arrives. */
    (function () {
        const storageKey = 'maintNotifSeenIds:<?= e($userKey) ?>';
        const currentIds = <?= json_encode($ids, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const sound      = new Audio('assets/notif.mp3');
        sound.preload    = 'auto';

        function playNotifSound() {
            sound.currentTime = 0;
            const playing = sound.play();
            // Browsers block autoplay until the user interacts with the page; ignore that error
            if (playing && playing.catch) playing.catch(function () {});
        }

        let seen = null;
        try { seen = JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (e) {}

        if (Array.isArray(seen) && currentIds.some(function (id) { return !seen.includes(id); })) {
            playNotifSound();
        }

        try { localStorage.setItem(storageKey, JSON.stringify(currentIds)); } catch (e) {}

        setInterval(function () {
            fetch(window.location.pathname + '?feed=1', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const fresh = (data.ids || []).some(function (id) { return !currentIds.includes(id); });
                    if (fresh) window.location.reload();
                })
                .catch(function () {});
        }, 30000);
    })();
</script>
<?php renderFoot(); ?>