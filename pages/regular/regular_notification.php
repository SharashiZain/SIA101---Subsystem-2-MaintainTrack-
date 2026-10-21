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
function regNotifRest(string $endpoint): array
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

/* =========================================================
   Build notifications for the logged-in resident
   - request submitted             -> "Maintenance Request Submitted"
   - assigned to a technician      -> "Maintenance Request Assigned"
   - in progress / fail to fix     -> "Maintenance Request Updated"
   - completed                     -> "Maintenance Request Completed"
                                      (from the timeline, and from the
                                      request itself when no timeline row exists)
   - rejections (maintenance and admin) are NOT shown to the resident

   Unread means the event happened in the last 24 hours.
   Clicking an item still marks it read in the browser only.
========================================================= */
$manila  = new DateTimeZone('Asia/Manila');
$cutoff  = time() - 86400;
$events  = [];
$userKey = 'guest';

$currentUsername = trim((string) ($_SESSION['username'] ?? $_SESSION['user'] ?? ''));

if ($currentUsername !== '') {
    // Same demo-login mapping used on My History
    $lookupUsername = $currentUsername === 'user' ? 'psantos' : $currentUsername;
    $userRes = regNotifRest('users?username=eq.' . rawurlencode($lookupUsername) . '&select=user_id,role&limit=1');

    if ($userRes['success'] && !empty($userRes['data'][0]) && ($userRes['data'][0]['role'] ?? '') === 'regular') {
        $me      = $userRes['data'][0];
        $userKey = (string) $me['user_id'];

        // Staff names for display
        $staffMap = [];
        $usersRes = regNotifRest('users?select=user_id,full_name,username');
        foreach (($usersRes['data'] ?? []) as $x) {
            $staffMap[$x['user_id']] = $x['full_name'] ?? ($x['username'] ?? '');
        }

        // This resident's own requests
        $reqRes = regNotifRest(
            'maintenance_requests?reported_by=eq.' . rawurlencode((string) $me['user_id'])
            . '&select=request_id,display_id,concern,status,assigned_to,date_completed,updated_at,approval_status,approval_date,rejection_reason,created_at'
            . '&order=created_at.desc&limit=200'
        );
        $requests = is_array($reqRes['data'] ?? null) ? $reqRes['data'] : [];
        $reqById  = [];
        foreach ($requests as $r) {
            $reqById[$r['request_id']] = $r;
        }

        $makeEvent = function (string $title, string $text, string $tone, ?string $when) use ($manila, $cutoff): ?array {
            if (empty($when)) return null;
            try {
                $dt = new DateTime($when);
            } catch (Throwable $e) {
                return null;
            }
            $dt->setTimezone($manila);
            $ts = $dt->getTimestamp();
            return [
                'id'     => md5($title . '|' . $text . '|' . $ts),
                'ts'     => $ts,
                'tone'   => $tone,
                'title'  => $title,
                'text'   => $text,
                'time'   => $dt->format('M j, Y · g:i A'),
                'unread' => $ts >= $cutoff,
            ];
        };

        /* ---- 1) Timeline of status changes on this resident's requests ---- */
        $completedFromTimeline = []; // request_ids that already have a completed timeline row

        if ($reqById) {
            $ids = implode(',', array_keys($reqById));
            $tlRes = regNotifRest(
                'request_timeline?request_id=in.(' . $ids . ')'
                . '&select=request_id,status,note,occurred_at&order=occurred_at.desc&limit=500'
            );

            foreach (($tlRes['data'] ?? []) as $row) {
                $req = $reqById[$row['request_id']] ?? null;
                if (!$req) continue;

                $displayId = (string) ($req['display_id'] ?? 'Your request');
                $concern   = trim((string) ($req['concern'] ?? ''));
                $label     = $concern !== '' ? $displayId . ' – ' . $concern : $displayId;
                $status    = trim((string) ($row['status'] ?? ''));
                $worker    = $staffMap[$req['assigned_to'] ?? ''] ?? 'our maintenance staff';
                $when      = $row['occurred_at'] ?? null;

                $ev = null;
                switch (strtolower($status)) {
                    case 'pending':
                        $ev = $makeEvent('Maintenance Request Submitted',
                            'Your maintenance request ' . $label . ' has been successfully submitted.',
                            'green', $when);
                        break;
                    case 'assigned':
                        $ev = $makeEvent('Maintenance Request Assigned',
                            'Your request ' . $label . ' has been assigned to ' . $worker . '.',
                            'blue', $when);
                        break;
                    case 'in progress':
                        $ev = $makeEvent('Maintenance Request Updated',
                            'Your maintenance request ' . $label . ' is now In Progress.',
                            'purple', $when);
                        break;
                    case 'completed':
                        $completedFromTimeline[(string) $row['request_id']] = true;
                        $ev = $makeEvent('Maintenance Request Completed',
                            'Your maintenance request ' . $label . ' has been completed.',
                            'orange', $when);
                        break;
                    case 'fail to fix':
                        $ev = $makeEvent('Maintenance Request Updated',
                            $label . ' could not be fixed yet. Please check the updates on the request.',
                            'red', $when);
                        break;
                    case 'rejected':
                        // Rejections are intentionally not shown to the resident.
                        break;
                    default:
                        if ($status !== '') {
                            $ev = $makeEvent('Maintenance Request Updated',
                                $label . ' was updated to ' . $status . '.',
                                'purple', $when);
                        }
                }
                if ($ev) $events[] = $ev;
            }
        }

        /* ---- 2) Completed requests with no completed timeline row ----
           Uses the request's own status and completion time, so a completed
           request still notifies the resident even if no timeline row was written. */
        foreach ($requests as $req) {
            if (strtolower(trim((string) ($req['status'] ?? ''))) !== 'completed') continue;
            if (isset($completedFromTimeline[(string) $req['request_id']])) continue;

            $displayId = (string) ($req['display_id'] ?? 'Your request');
            $concern   = trim((string) ($req['concern'] ?? ''));
            $label     = $concern !== '' ? $displayId . ' – ' . $concern : $displayId;
            $when      = $req['date_completed'] ?? ($req['updated_at'] ?? null);

            $ev = $makeEvent('Maintenance Request Completed',
                'Your maintenance request ' . $label . ' has been completed.',
                'orange', $when);
            if ($ev) $events[] = $ev;
        }

        /* ---- 3) Admin approvals on this resident's requests ----
           Only approvals are shown; admin rejections are not. */
        foreach ($requests as $req) {
            $displayId = (string) ($req['display_id'] ?? 'Your request');
            $approval  = trim((string) ($req['approval_status'] ?? ''));

            if ($approval === 'Approved') {
                $ev = $makeEvent('Request Approved',
                    $displayId . ' has been approved and sent to the maintenance team.',
                    'green', $req['approval_date'] ?? null);
                if ($ev) $events[] = $ev;
            }
        }
    }
}

/* Newest first, capped to keep the page light */
usort($events, fn($a, $b) => $b['ts'] <=> $a['ts']);
$events = array_slice($events, 0, 100);

$notifications = [];
$ids           = [];
$unreadCount   = 0;
foreach ($events as $ev) {
    $ids[] = $ev['id'];
    if ($ev['unread']) $unreadCount++;
    $notifications[] = [
        'tone'     => $ev['tone'],
        'title'    => $ev['title'],
        'text'     => $ev['text'],
        'time'     => $ev['time'],
        'unread'   => $ev['unread'],
        'category' => 'maintenance',
    ];
}

/* Lightweight feed used by the background check below */
if (isset($_GET['feed'])) {
    header('Content-Type: application/json');
    echo json_encode(['ids' => $ids, 'unread' => $unreadCount]);
    exit;
}

renderHead('Notifications', 'assets/css/NotificationShared.css', 'regular');

renderNotificationPage('regular', 'Resident Portal • Barangay Gulod', $notifications, [
    ['key' => 'maintenance', 'label' => 'Maintenance Updates'],
]);
?>
<script>
    /* Sound alert and live check.
       - Plays notif.mp3 when a page load finds events that were not there last time.
       - Checks for new events every 30 seconds and reloads the list when one arrives. */
    (function () {
        const storageKey = 'regularNotifSeenIds:<?= e($userKey) ?>';
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
<?php renderFoot();