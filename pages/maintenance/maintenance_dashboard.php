<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---- Helpers (same data path as maintenance_task.php) ---- */
function dashApi(string $endpoint): array {
    $secret = defined('SUPABASE_SECRET_KEY') ? (string) SUPABASE_SECRET_KEY : '';
    $base   = defined('SUPABASE_URL') ? (string) SUPABASE_URL : '';
    if ($secret === '' || $base === '') {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'Supabase configuration is missing.'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL extension is not enabled.'];
    }
    $ch = curl_init(rtrim($base, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $secret,
            'Authorization: Bearer ' . $secret,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw       = curl_exec($ch);
    $curlError = curl_error($ch);
    $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'status' => $status, 'data' => null, 'error' => $curlError ?: 'Could not connect to Supabase.'];
    }
    $decoded = json_decode($raw, true);
    $success = $status >= 200 && $status < 300;
    $error   = null;
    if (!$success) {
        $error = is_array($decoded)
            ? (string) ($decoded['message'] ?? $decoded['details'] ?? $decoded['hint'] ?? $raw)
            : $raw;
    }
    return ['success' => $success, 'status' => $status, 'data' => $decoded, 'error' => $error];
}

function dashStatusKey($value): string {
    return match (strtolower(trim((string) $value))) {
        'in progress', 'in-progress', 'ongoing' => 'progress',
        'completed', 'closed', 'resolved'       => 'completed',
        'rejected'                              => 'rejected',
        default                                  => 'pending', // Assigned, Pending, On Hold
    };
}

function dashPriorityRank(string $priority): int {
    return match (strtolower(trim($priority))) {
        'high', 'urgent', 'critical' => 1,
        'medium', 'normal'           => 2,
        default                      => 3,
    };
}

function dashTagClass(string $priority): string {
    return match (strtolower(trim($priority))) {
        'high', 'urgent', 'critical' => 'tag-high',
        'medium', 'normal'           => 'tag-mid',
        default                      => 'tag-low',
    };
}

function dashTimeAgo(?string $datetime): string {
    if (!$datetime) return '';
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return '';
    $diff = time() - $timestamp;
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

/* ---- Data ---- */
$pageError = '';
$tasks = [];
$facilityMap = $floorMap = $roomMap = [];

$currentUsername = trim((string) ($_SESSION['username'] ?? $_SESSION['user'] ?? ''));
$currentUserId   = trim((string) ($_SESSION['user_id'] ?? ''));

if ($currentUsername === '' && $currentUserId === '') {
    $pageError = 'Your session has expired. Please log in again.';
} else {
    if ($currentUserId === '') {
        $lookupUsername = $currentUsername === 'user' ? 'maintenance' : $currentUsername;
        $userResult = dashApi('users?username=eq.' . rawurlencode($lookupUsername) . '&select=user_id,username,full_name,role,status&limit=1');
        if (!$userResult['success'] || empty($userResult['data'][0])) {
            $pageError = 'Unable to identify your account. Please sign in again.';
        } else {
            $currentUser = $userResult['data'][0];
            $currentUserId = (string) ($currentUser['user_id'] ?? '');
            $_SESSION['user_id']   = $currentUserId;
            $_SESSION['username']  = (string) ($currentUser['username'] ?? $currentUsername);
            $_SESSION['full_name'] = (string) ($currentUser['full_name'] ?? '');
            $_SESSION['role']      = (string) ($currentUser['role'] ?? ($_SESSION['role'] ?? ''));
        }
    }

    if ($pageError === '' && strtolower((string) ($_SESSION['role'] ?? '')) !== 'maintenance') {
        $pageError = 'This page is available to Maintenance Personnel only.';
    }

    if ($pageError === '') {
        $requestResult = dashApi(
            'maintenance_requests?assigned_to=eq.' . rawurlencode($currentUserId) .
            '&select=request_id,display_id,concern,priority,status,facility_id,floor_id,room_id,specific_area,date_assigned,date_started,created_at' .
            '&order=created_at.desc.nullslast'
        );

        if (!$requestResult['success']) {
            $pageError = 'Unable to load your tasks. ' . (string) ($requestResult['error'] ?? '');
        } else {
            $rows = is_array($requestResult['data']) ? $requestResult['data'] : [];

            $facilities = dashApi('facilities?select=facility_id,name');
            $floors     = dashApi('facility_floors?select=floor_id,name');
            $rooms      = dashApi('facility_rooms?select=room_id,name');

            foreach (($facilities['data'] ?? []) as $x) $facilityMap[(string) ($x['facility_id'] ?? '')] = (string) ($x['name'] ?? '');
            foreach (($floors['data'] ?? []) as $x)     $floorMap[(string) ($x['floor_id'] ?? '')]       = (string) ($x['name'] ?? '');
            foreach (($rooms['data'] ?? []) as $x)      $roomMap[(string) ($x['room_id'] ?? '')]         = (string) ($x['name'] ?? '');

            foreach ($rows as $row) {
                $statusKey = dashStatusKey($row['status'] ?? '');

                $parts = [];
                foreach ([
                    $facilityMap[(string) ($row['facility_id'] ?? '')] ?? '',
                    $floorMap[(string) ($row['floor_id'] ?? '')] ?? '',
                    $roomMap[(string) ($row['room_id'] ?? '')] ?? '',
                    trim((string) ($row['specific_area'] ?? '')),
                ] as $part) {
                    if ($part !== '' && !in_array($part, $parts, true)) $parts[] = $part;
                }
                $location = $parts ? implode(' · ', $parts) : 'Location not specified';

                // Show when work started if underway, otherwise when it was assigned
                if ($statusKey === 'progress' && !empty($row['date_started'])) {
                    $when = 'Started ' . dashTimeAgo($row['date_started']);
                } elseif (!empty($row['date_assigned'])) {
                    $when = 'Assigned ' . dashTimeAgo($row['date_assigned']);
                } else {
                    $when = '';
                }

                $priority = trim((string) ($row['priority'] ?? '')) ?: 'Low';

                $tasks[] = [
                    'request_id' => (string) ($row['request_id'] ?? ''),
                    'id'         => trim((string) ($row['display_id'] ?? '')) ?: (string) ($row['request_id'] ?? ''),
                    'title'      => trim((string) ($row['concern'] ?? '')) ?: 'Maintenance Request',
                    'location'   => $location,
                    'priority'   => $priority,
                    'tagClass'   => dashTagClass($priority),
                    'status'     => (string) ($row['status'] ?? ''),
                    'status_key' => $statusKey,
                    'when'       => $when,
                    'estimate'   => '—', // no estimate column in the database yet
                    'done'       => $statusKey === 'completed' ? 1 : 0,
                    'rank'       => dashPriorityRank($priority),
                ];
            }

            // Open tasks first, then by priority
            usort($tasks, fn($a, $b) => [$a['done'], $a['rank']] <=> [$b['done'], $b['rank']]);
        }
    }
}

/* Stat chips (same counting as maintenance_task.php) */
$countByKey = fn(string $key) => count(array_filter($tasks, fn($t) => $t['status_key'] === $key));
$summary = [
    ['count' => count($tasks),            'label' => 'Total',       'class' => ''],
    ['count' => $countByKey('pending'),   'label' => 'Pending',     'class' => 'orange'],
    ['count' => $countByKey('progress'),  'label' => 'In Progress', 'class' => 'purple'],
    ['count' => $countByKey('completed'), 'label' => 'Completed',   'class' => 'green'],
    ['count' => $countByKey('rejected'),  'label' => 'Rejected',    'class' => 'red'],
];
$activeCount = $summary[1]['count'] + $summary[2]['count'];

renderHead('My Dashboard', 'Dashboard.css', 'maintenance');
?>
<div class="page">

    <!-- ONE CONTAINER: banner + all page content -->
    <div class="one-card">

    <?php renderPageHeader('Today\'s Tasks', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <!-- TASK QUEUE (hero) -->
    <section class="panel">
        <div class="panel-head">
            <h2>Assigned to You</h2>
            <div class="summary-line">
                <?php foreach ($summary as $st): ?>
                    <div class="summary-item">
                        <strong class="<?= e($st['class']) ?>"><?= (int)$st['count'] ?></strong>
                        <span><?= e($st['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <span class="count-badge"><?= (int)$activeCount ?> active</span>
        </div>

        <?php if ($pageError !== ''): ?>
            <div class="empty-state" role="alert">
                <p><?= e($pageError) ?></p>
            </div>
        <?php elseif (empty($tasks)): ?>
            <div class="empty-state">
                <p>No tasks assigned to you right now.</p>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item priority-<?= e(strtolower($task['priority'])) ?>"
                         onclick="window.location.href='maintenance_request.php?id=<?= rawurlencode($task['request_id']) ?>'">
                        <div class="task-left">
                            <h3><?= e($task['title']) ?></h3>
                            <div class="task-meta">
                                <?= e($task['id']) ?> · <?= e($task['location']) ?>
                            </div>
                            <div class="task-timing">
                                <span><?= e($task['when']) ?></span>
                                <span class="dot">·</span>
                                <span><?= e($task['estimate']) ?></span>
                            </div>
                        </div>
                        <div class="task-right">
                            <span class="tag <?= e($task['tagClass']) ?>"><?= e($task['priority']) ?></span>
                            <?= statusBadge($task['status']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- SMALL METRIC STRIP -->
    <section class="metric-strip">
        <div class="metric-item">
            <span class="metric-label">Avg. Response</span>
            <span class="metric-value">2h 18m</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Completed Today</span>
            <span class="metric-value">5</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Completed This Week</span>
            <span class="metric-value">18</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">On Hold</span>
            <span class="metric-value warn">1</span>
        </div>
    </section>

    </div>

</div>
<?php renderFoot(); ?>