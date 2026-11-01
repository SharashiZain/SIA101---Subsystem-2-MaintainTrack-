<?php
/*
 * notification_poll.php
 * Returns the logged-in user's unread notification info as JSON, for NotificationSound.js.
 * Called as includes/notification_poll.php?role=admin (works for all five roles).
 *
 * CHECK THESE TWO LINES AGAINST YOUR PROJECT (I could not see your session.php):
 *   1. $userId: the session key that holds the logged-in user's users.user_id
 *   2. SUPABASE_URL / SUPABASE_SECRET_KEY: the constant names in Config/supabase.php
 */
require_once __DIR__ . '/session.php';           // picks the role session from ?role=
require_once __DIR__ . '/../Config/supabase.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

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
    $url = SUPABASE_URL . '/rest/v1/notifications'
         . '?recipient_user_id=eq.' . rawurlencode($userId)
         . '&is_read=eq.false'
         . '&select=notification_id,created_at'
         . '&order=created_at.desc&limit=1';
}

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

$rows   = json_decode($body, true) ?: [];
$unread = 0;
if (preg_match('#/(\d+)$#', $contentRange, $m)) {
    $unread = (int) $m[1];
}

if ($scope === 'approvals') {
    echo json_encode(['pending' => $unread]);
    exit;
}

echo json_encode([
    'unread'            => $unread,
    'latest_created_at' => $rows[0]['created_at'] ?? null,
]);