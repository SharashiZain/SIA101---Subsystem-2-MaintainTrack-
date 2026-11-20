<?php
/*
 * notification_poll.php
 * Returns the logged-in user's unread notifications as JSON, for NotificationSound.js.
 * Called as includes/notification_poll.php?role=admin (works for all five roles).
 *
 * CHECK THESE TWO LINES AGAINST YOUR PROJECT (I could not see your session.php):
 *   1. $userId: the session key that holds the logged-in user's users.user_id
 *   2. SUPABASE_URL / SUPABASE_SECRET_KEY: the constant names in includes/supabase_client.php
 */
require_once __DIR__ . '/session.php';           // picks the role session from ?role=
require_once __DIR__ . '/supabase_client.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$expectedRole = mtNormalizeRole($_GET['role'] ?? '');
if ($expectedRole === '' || mtRoleContext() !== $expectedRole
    || mtNormalizeRole($_SESSION['role'] ?? '') !== $expectedRole) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;          // <-- adjust if your key has another name
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}
session_write_close(); // don't hold the session lock while waiting on Supabase

/* ---- Approvals dot: ?role=secretary&scope=approvals ----
 * Returns how many requests are still waiting for the secretary's decision.
 * Same rule the Approvals page uses: approval_status is 'Pending' or empty (null). */
$scope = $_GET['scope'] ?? '';
if ($scope === 'approvals' && ($_SESSION['role'] ?? '') !== 'secretary') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if ($scope === 'approvals') {
    $url = SUPABASE_URL . '/rest/v1/maintenance_requests'
         . '?or=(approval_status.eq.Pending,approval_status.is.null)'
         . '&select=request_id'
         . '&limit=1';
} else {
    $since = trim((string) ($_GET['since'] ?? ''));
    if ($since !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $since)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_since']);
            exit;
        }
        try {
            new DateTimeImmutable($since);
        } catch (Exception $exception) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_since']);
            exit;
        }
    }

    $url = SUPABASE_URL . '/rest/v1/notifications'
         . '?recipient_user_id=eq.' . rawurlencode($userId)
         . '&is_read=eq.false'
         . '&select=notification_id,title,message,related_request_id,created_at'
         . ($since !== '' ? '&created_at=gt.' . rawurlencode($since) : '')
         . '&order=created_at.asc&limit=1000';
}

if ($scope === 'approvals') {
    $contentRange = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Prefer: count=exact',
        ],
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$contentRange) {
            if (stripos($line, 'content-range:') === 0) {
                $contentRange = trim(substr($line, 14));   // e.g. "0-0/5"
            }
            return strlen($line);
        },
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        http_response_code(502);
        echo json_encode(['error' => 'supabase_error']);
        exit;
    }

    $unread = 0;
    if (preg_match('#/(\d+)$#', $contentRange, $m)) {
        $unread = (int) $m[1];
    }
    echo json_encode(['pending' => $unread]);
    exit;
}

if (in_array($expectedRole, ['regular', 'maintenance'], true)) {
    $rpcUrl = rtrim((string) SUPABASE_URL, '/') . '/rest/v1/rpc/sync_overdue_borrow_notifications';
    $rpc = curl_init($rpcUrl);
    curl_setopt_array($rpc, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['p_borrower_user_id' => $userId]),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $rpcBody = curl_exec($rpc);
    $rpcStatus = (int) curl_getinfo($rpc, CURLINFO_HTTP_CODE);
    $rpcError = curl_error($rpc);
    curl_close($rpc);
    if ($rpcBody === false || $rpcError !== '' || $rpcStatus < 200 || $rpcStatus >= 300) {
        http_response_code(502);
        echo json_encode(['error' => 'overdue_notification_sync_failed']);
        exit;
    }
}

$rows = [];
$offset = 0;
$pageSize = 1000;
$unread = 0;
do {
    $contentRange = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Prefer: count=exact',
            'Range: ' . $offset . '-' . ($offset + $pageSize - 1),
        ],
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$contentRange) {
            if (stripos($line, 'content-range:') === 0) {
                $contentRange = trim(substr($line, 14));
            }
            return strlen($line);
        },
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        http_response_code(502);
        echo json_encode(['error' => 'supabase_error']);
        exit;
    }

    $page = json_decode($body, true);
    if (!is_array($page)) {
        http_response_code(502);
        echo json_encode(['error' => 'invalid_supabase_response']);
        exit;
    }

    $rows = array_merge($rows, $page);
    $received = count($page);
    $offset += $received;
    if (preg_match('#/(\d+)$#', $contentRange, $m)) {
        $unread = (int) $m[1];
    }
} while ($received > 0 && $offset < $unread);

if ($unread < count($rows)) {
    $unread = count($rows);
}

$notifications = array_map(static function (array $row): array {
    return [
        'id' => (string) ($row['notification_id'] ?? ''),
        'title' => (string) ($row['title'] ?? 'New notification'),
        'message' => (string) ($row['message'] ?? ''),
        'related_request_id' => (string) ($row['related_request_id'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}, $rows);
$latest = $notifications ? $notifications[count($notifications) - 1] : null;

echo json_encode([
    'unread'              => $unread,
    'latest_created_at'   => $latest['created_at'] ?? null,
    'latest_notification' => $latest,
    'notifications'       => $notifications,
]);