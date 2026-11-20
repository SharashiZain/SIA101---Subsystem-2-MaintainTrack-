<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/supabase_client.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$role = mtNormalizeRole($_GET['role'] ?? '');
$context = mtRoleContext();
$sessionRole = mtNormalizeRole($_SESSION['role'] ?? '');
$userId = trim((string) ($_SESSION['user_id'] ?? ''));
if ($role === '' || $context !== $role || $sessionRole !== $role
    || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
$rawIds = is_array($input) ? ($input['ids'] ?? null) : null;
if (!is_array($rawIds) || count($rawIds) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_notification_ids']);
    exit;
}

$ids = [];
foreach ($rawIds as $value) {
    if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
        continue;
    }
    $ids[$value] = true;
}
if (!$ids) {
    echo json_encode(['updated' => 0]);
    exit;
}
if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['error' => 'notification_service_unavailable']);
    exit;
}

$idList = implode(',', array_keys($ids));
$url = rtrim((string) SUPABASE_URL, '/') . '/rest/v1/notifications?recipient_user_id=eq.'
    . rawurlencode($userId) . '&notification_id=in.(' . $idList . ')&is_read=eq.false';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => json_encode(['is_read' => true]),
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SECRET_KEY,
        'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
        'Accept: application/json',
        'Content-Type: application/json',
        'Prefer: return=representation',
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError !== '' || $status < 200 || $status >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'notification_update_failed']);
    exit;
}

$rows = json_decode($response, true);
echo json_encode(['updated' => is_array($rows) ? count($rows) : 0]);
