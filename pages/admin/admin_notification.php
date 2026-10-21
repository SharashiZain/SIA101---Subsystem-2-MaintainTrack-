<?php
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';

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
   Notifications for the admin
   - a request is assigned to a maintenance worker -> "was assigned to ..." (assignment)
   - a report is created by an admin               -> "New request ... created" (request)
   - a maintenance worker rejects a request        -> "was rejected by ..."  (status)

   Read state is not stored in the database yet, so an item counts as
   unread when it happened in the last 24 hours. Clicking an item still
   marks it read in the browser only, as before.
========================================================= */
$manila = new DateTimeZone('Asia/Manila');
$toTime = function (?string $value) use ($manila): ?DateTime {
    if (empty($value)) return null;
    $dt = new DateTime($value);
    $dt->setTimezone($manila);
    return $dt;
};

$notifications = [];
$events        = [];
$cutoff        = time() - 86400; // 24 hours

/* ---- Lookups ---- */
$usrRes = notifRest('/rest/v1/users?select=user_id,full_name,role');
$users  = [];
$adminIds = [];
if ($usrRes['ok'] && is_array($usrRes['data'])) {
    foreach ($usrRes['data'] as $u) {
        $users[$u['user_id']] = $u['full_name'];
        if (strtolower((string) ($u['role'] ?? '')) === 'admin') {
            $adminIds[] = $u['user_id'];
        }
    }
}

/* ---- 1) Assignments to maintenance workers ---- */
$assignRes = notifRest(
    '/rest/v1/maintenance_requests'
    . '?select=request_id,display_id,assigned_to,date_assigned'
    . '&date_assigned=not.is.null'
    . '&order=date_assigned.desc&limit=200'
);
if ($assignRes['ok'] && is_array($assignRes['data'])) {
    foreach ($assignRes['data'] as $r) {
        $at = $toTime($r['date_assigned'] ?? null);
        if (!$at) continue;

        $worker = $users[$r['assigned_to'] ?? ''] ?? 'a maintenance worker';

        $events[] = [
            'ts'       => $at->getTimestamp(),
            'color'    => 'blue',
            'text'     => $r['display_id'] . ' was assigned to ' . $worker . '.',
            'time'     => $at->format('M j, Y · g:i A'),
            'unread'   => $at->getTimestamp() >= $cutoff,
            'category' => 'assignment',
        ];
    }
}

/* ---- 2) Reports created by an admin ---- */
if (!empty($adminIds)) {
    $reportRes = notifRest(
        '/rest/v1/maintenance_requests'
        . '?select=request_id,display_id,concern,reported_by,created_at'
        . '&reported_by=in.(' . implode(',', $adminIds) . ')'
        . '&order=created_at.desc&limit=200'
    );
    if ($reportRes['ok'] && is_array($reportRes['data'])) {
        foreach ($reportRes['data'] as $r) {
            $at = $toTime($r['created_at'] ?? null);
            if (!$at) continue;

            $creator = $users[$r['reported_by'] ?? ''] ?? 'an admin';

            $events[] = [
                'ts'       => $at->getTimestamp(),
                'color'    => 'orange',
                'text'     => 'New request ' . $r['display_id'] . ' (' . $r['concern'] . ') created by ' . $creator . '.',
                'time'     => $at->format('M j, Y · g:i A'),
                'unread'   => $at->getTimestamp() >= $cutoff,
                'category' => 'request',
            ];
        }
    }
}

/* ---- 3) Rejections made by maintenance workers ---- */
$rejRes = notifRest(
    '/rest/v1/maintenance_rejections'
    . '?select=request_id,rejected_by,reason,created_at'
    . '&order=created_at.desc&limit=200'
);
if ($rejRes['ok'] && is_array($rejRes['data']) && !empty($rejRes['data'])) {
    // Look up the display IDs (MT-xxxxx) for the requests that were rejected
    $rejectedIds = array_values(array_unique(array_filter(array_column($rejRes['data'], 'request_id'))));
    $rejDisplayIds = [];
    if (!empty($rejectedIds)) {
        $rejReqRes = notifRest(
            '/rest/v1/maintenance_requests'
            . '?select=request_id,display_id'
            . '&request_id=in.(' . implode(',', $rejectedIds) . ')'
        );
        if ($rejReqRes['ok'] && is_array($rejReqRes['data'])) {
            foreach ($rejReqRes['data'] as $q) {
                $rejDisplayIds[$q['request_id']] = $q['display_id'];
            }
        }
    }

    foreach ($rejRes['data'] as $rej) {
        $displayId = $rejDisplayIds[$rej['request_id'] ?? ''] ?? null;
        if (!$displayId) continue;

        $at = $toTime($rej['created_at'] ?? null);
        if (!$at) continue;

        $worker = $users[$rej['rejected_by'] ?? ''] ?? 'a maintenance worker';
        $reason = trim((string) ($rej['reason'] ?? ''));

        $events[] = [
            'ts'       => $at->getTimestamp(),
            'color'    => 'red',
            'text'     => $displayId . ' was rejected by ' . $worker
                . ($reason !== '' ? ': ' . $reason . '.' : '.'),
            'time'     => $at->format('M j, Y · g:i A'),
            'unread'   => $at->getTimestamp() >= $cutoff,
            'category' => 'status',
        ];
    }
}

// Newest first, then strip the sort key so the view gets only what it expects
usort($events, fn($a, $b) => $b['ts'] <=> $a['ts']);
foreach ($events as $ev) {
    unset($ev['ts']);
    $notifications[] = $ev;
}

/* Number of unread items, used by the sound alert below */
$unreadCount = 0;
foreach ($notifications as $n) {
    if ($n['unread']) $unreadCount++;
}

renderHead('Notifications', 'Notification.css');
?>
<div class="container">

    <div class="one-card">
        <?php renderPageHeader('Notifications'); ?>
    <div class="actions-top">
        <div class="notif-filters">
            <button class="filter-btn active" data-filter="all" type="button">All</button>
            <button class="filter-btn" data-filter="unread" type="button">Unread</button>
            <button class="filter-btn" data-filter="assignment" type="button">Assignments</button>
            <button class="filter-btn" data-filter="status" type="button">Status Updates</button>
            <button class="filter-btn" data-filter="request" type="button">New Requests</button>
        </div>

        <button class="btn-mark-read" id="markAllReadBtn" type="button">Mark all as read</button>
    </div>

    <main class="notifications-card" id="notificationsCard">
        <?php foreach ($notifications as $index => $n): ?>
            <div class="notification-item<?= $n['unread'] ? ' unread' : '' ?>"
                 data-category="<?= e($n['category']) ?>"
                 data-unread="<?= $n['unread'] ? 'true' : 'false' ?>"
                 data-index="<?= $index ?>">
                <div class="icon-indicator bg-<?= e($n['color']) ?>">!</div>
                <div class="notif-content">
                    <h4><?= e($n['text']) ?></h4>
                    <p>Maintenance Reporting Portal</p>
                </div>
                <div class="notif-time"><?= e($n['time']) ?></div>
            </div>
        <?php endforeach; ?>

        <div id="notifNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
            <p style="font-size: 15px; font-weight: 600;">No notifications found for this filter.</p>
        </div>
    </main>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filterBtns = document.querySelectorAll('.filter-btn');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const notificationItems = document.querySelectorAll('.notification-item');
        const noResults = document.getElementById('notifNoResults');

        let currentFilter = 'all';

        function applyFilter() {
            let visibleCount = 0;

            notificationItems.forEach(function (item) {
                const category = item.dataset.category || '';
                const isUnread = item.dataset.unread === 'true';

                let visible = true;

                if (currentFilter === 'unread') {
                    visible = isUnread;
                } else if (currentFilter !== 'all') {
                    visible = category === currentFilter;
                }

                if (visible) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterBtns.forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilter();
            });
        });

        markAllReadBtn.addEventListener('click', function () {
            notificationItems.forEach(function (item) {
                item.classList.remove('unread');
                item.classList.add('read');
                item.setAttribute('data-unread', 'false');
            });
            applyFilter();
        });

        notificationItems.forEach(function (item) {
            item.addEventListener('click', function () {
                if (this.getAttribute('data-unread') === 'true') {
                    this.classList.remove('unread');
                    this.classList.add('read');
                    this.setAttribute('data-unread', 'false');
                    applyFilter();
                }
            });
        });

        applyFilter();
    });

    /* Sound alert: plays notif.mp3 when the number of unread notifications goes up. */
    (function () {
        const unreadNow  = <?= (int) $unreadCount ?>;
        const storageKey = 'adminNotifUnreadCount';
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
<?php renderFoot(); ?>