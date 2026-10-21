<?php
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Components/notification_view.php';

/* Pages the notifications link to (change these if your files are named differently) */
$approvalsPage = 'secretary_approvals.php';
$historyPage   = 'secretary_history.php';

/* =========================================================
   Supabase helper (server-side only)
   Uses the secret key so row-level security does not hide rows from
   this page. The key stays in PHP and is never sent to the browser.
========================================================= */
function notifRest(string $path): array
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

/* =========================================================
   Notifications built from maintenance_requests
   - every submitted report      -> "New Submission Received"
   - every approve / reject      -> "Request Approved" / "Request Rejected"
   A submission counts as unread while it is still waiting for a decision.

   Where each notification opens when clicked:
   - submission still pending    -> Approvals page, request highlighted
   - submission already decided  -> History page, request highlighted
   - approved / rejected         -> History page, request highlighted
========================================================= */
$notifications = [];
$notifLinks = []; // [{ text, url }] used by the click handler below

$reqRes = notifRest(
    '/rest/v1/maintenance_requests'
    . '?select=display_id,concern,reported_by,approval_status,approval_date,rejection_reason,created_at'
    . '&order=created_at.desc&limit=200'
);
$usrRes = notifRest('/rest/v1/users?select=user_id,full_name');

if ($reqRes['ok'] && is_array($reqRes['data'])) {
    $users = [];
    if ($usrRes['ok'] && is_array($usrRes['data'])) {
        foreach ($usrRes['data'] as $u) $users[$u['user_id']] = $u['full_name'];
    }

    $manila = new DateTimeZone('Asia/Manila');
    $toTime = function (?string $value) use ($manila): ?DateTime {
        if (empty($value)) return null;
        $dt = new DateTime($value);
        $dt->setTimezone($manila);
        return $dt;
    };

    $events = [];

    foreach ($reqRes['data'] as $row) {
        $approval  = $row['approval_status'] ?: 'Pending';
        $submitter = $users[$row['reported_by'] ?? ''] ?? 'Unknown';
        $idParam   = rawurlencode($row['display_id']);

        $approvalsUrl = $approvalsPage . '?id=' . $idParam . '&highlight=1';
        $historyUrl   = $historyPage . '?id=' . $idParam . '&highlight=1';

        // 1) New submission
        $created = $toTime($row['created_at'] ?? null);
        if ($created) {
            $events[] = [
                'ts'       => $created->getTimestamp(),
                'tone'     => 'orange',
                'title'    => 'New Submission Received',
                'text'     => $row['display_id'] . ' ' . $row['concern'] . ' submitted by ' . $submitter . '.',
                'time'     => $created->format('M j, Y · g:i A'),
                'unread'   => $approval === 'Pending',
                'category' => 'submission',
                'url'      => $approval === 'Pending' ? $approvalsUrl : $historyUrl,
            ];
        }

        // 2) Decision (only when we know when it happened)
        $decided = $toTime($row['approval_date'] ?? null);
        if ($decided && in_array($approval, ['Approved', 'Rejected'], true)) {
            if ($approval === 'Approved') {
                $text = $row['display_id'] . ' has been approved and sent to Admin.';
            } else {
                $text = $row['display_id'] . ' was rejected'
                    . (!empty($row['rejection_reason']) ? ' — ' . $row['rejection_reason'] . '.' : '.');
            }

            $events[] = [
                'ts'       => $decided->getTimestamp(),
                'tone'     => $approval === 'Approved' ? 'green' : 'red',
                'title'    => 'Request ' . $approval,
                'text'     => $text,
                'time'     => $decided->format('M j, Y · g:i A'),
                'unread'   => false,
                'category' => 'decision',
                'url'      => $historyUrl,
            ];
        }
    }

    // Newest first, then split the link out so the shared view only gets what it expects
    usort($events, fn($a, $b) => $b['ts'] <=> $a['ts']);
    foreach ($events as $ev) {
        $notifLinks[] = ['text' => $ev['text'], 'url' => $ev['url']];
        unset($ev['ts'], $ev['url']);
        $notifications[] = $ev;
    }
}

/* Number of unread items, used by the sound alert below */
$unreadCount = 0;
foreach ($notifications as $n) {
    if ($n['unread']) $unreadCount++;
}

/* Same notification design as the Admin page (shared view + stylesheet). */
renderHead('Notifications', 'assets/css/NotificationShared.css', 'secretary');

renderNotificationPage('secretary', 'Secretary Portal · Barangay Gulod', $notifications, [
    ['key' => 'submission', 'label' => 'New Submissions'],
    ['key' => 'decision',   'label' => 'Decisions'],
]);
?>
<script>
    /* Clicking a notification opens the related request on the right page. */
    document.addEventListener('DOMContentLoaded', function () {
        const links = <?= json_encode($notifLinks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const normalize = function (s) { return (s || '').replace(/\s+/g, ' ').trim(); };
        const cards = document.querySelectorAll('.notif-card');

        cards.forEach(function (card, index) {
            const cardText = normalize(card.textContent);

            // Match by the notification text; fall back to the card position
            let match = links.find(function (l) { return cardText.includes(normalize(l.text)); });
            if (!match && cards.length === links.length) match = links[index];
            if (!match) return;

            card.style.cursor = 'pointer';
            // Capture phase so this runs even if the shared view handles the click too
            card.addEventListener('click', function () {
                window.location.href = match.url;
            }, true);
        });
    });

    /* Sound alert: plays notif.mp3 when the number of unread notifications goes up. */
    (function () {
        const unreadNow  = <?= (int) $unreadCount ?>;
        const storageKey = 'secretaryNotifUnreadCount';
        const sound      = new Audio('assets/notif.mp3');
        sound.preload    = 'auto';

        function playNotifSound() {
            sound.currentTime = 0;
            const playing = sound.play();
            // Browsers block autoplay until the user interacts with the page; ignore that error
            if (playing && playing.catch) playing.catch(function () {});
        }

        let previous = null;
        try { previous = parseInt(localStorage.getItem(storageKey), 10); } catch (e) {}

        if (previous !== null && !isNaN(previous) && unreadNow > previous) {
            playNotifSound();
        }

        try { localStorage.setItem(storageKey, String(unreadNow)); } catch (e) {}
    })();
</script>
<?php
renderFoot();