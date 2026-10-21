<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function historyRequest(string $method, string $endpoint): array {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
        return ['success'=>false,'status'=>0,'data'=>null,'error'=>'Supabase configuration or PHP cURL is missing.'];
    }
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Accept: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $curlError !== '') return ['success'=>false,'status'=>$status,'data'=>null,'error'=>$curlError ?: 'Request failed.'];
    $decoded = json_decode($body, true);
    return ['success'=>$status >= 200 && $status < 300,'status'=>$status,'data'=>$decoded,'error'=>($status < 200 || $status >= 300) ? (is_array($decoded) ? ($decoded['message'] ?? $decoded['details'] ?? $decoded['hint'] ?? $body) : $body) : null];
}
function historyEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function historyDate($value): string {
    if (!$value) return '—';
    try { return (new DateTime((string)$value, new DateTimeZone('Asia/Manila')))->format('M j, Y'); }
    catch (Throwable $e) { return (string)$value; }
}
function historyStatusClass($status): string {
    $status = strtolower(trim((string)$status));
    return match ($status) {
        'in progress', 'in-progress' => 'in-progress',
        'completed', 'closed' => 'completed',
        'assigned' => 'in-progress',
        default => 'pending'
    };
}

$pageError = '';
$requests = [];
$currentUsername = trim((string)($_SESSION['username'] ?? $_SESSION['user'] ?? ''));
if ($currentUsername === '') {
    $pageError = 'Your session has expired. Please log in again.';
} else {
    // Some demo logins use the short username "user" for the regular account.
    $lookupUsername = $currentUsername === 'user' ? 'psantos' : $currentUsername;
    $userResult = historyRequest('GET', 'users?username=eq.' . rawurlencode($lookupUsername) . '&select=user_id,username,full_name,role,status&limit=1');
    if (!$userResult['success'] || empty($userResult['data'][0])) {
        $pageError = 'Unable to identify your account. Please sign in again.';
    } else {
        $currentUser = $userResult['data'][0];
        if (($currentUser['role'] ?? '') !== 'regular') {
            $pageError = 'This history page is available to Regular Users only.';
        } else {
            $_SESSION['user_id'] = $currentUser['user_id'];
            $_SESSION['username'] = $currentUser['username'];
            $_SESSION['full_name'] = $currentUser['full_name'] ?? '';

            $requestsResult = historyRequest('GET', 'maintenance_requests?reported_by=eq.' . rawurlencode((string)$currentUser['user_id']) . '&select=request_id,display_id,concern,description,status,facility_id,floor_id,room_id,equipment_id,assigned_to,reported_by,date_reported,created_at&order=created_at.desc.nullslast');
            if (!$requestsResult['success']) {
                $pageError = 'Unable to load your request history. ' . (string)($requestsResult['error'] ?? 'Please check the maintenance_requests table.');
            } else {
                $requests = is_array($requestsResult['data']) ? $requestsResult['data'] : [];
                $facilities = historyRequest('GET', 'facilities?select=facility_id,name');
                $floors = historyRequest('GET', 'facility_floors?select=floor_id,name');
                $rooms = historyRequest('GET', 'facility_rooms?select=room_id,name');
                $equipment = historyRequest('GET', 'equipment?select=equipment_id,display_id,name,type');
                $users = historyRequest('GET', 'users?select=user_id,full_name,username');

                $facilityMap = []; foreach (($facilities['data'] ?? []) as $x) $facilityMap[$x['facility_id']] = $x['name'] ?? '';
                $floorMap = []; foreach (($floors['data'] ?? []) as $x) $floorMap[$x['floor_id']] = $x['name'] ?? '';
                $roomMap = []; foreach (($rooms['data'] ?? []) as $x) $roomMap[$x['room_id']] = $x['name'] ?? '';
                $equipmentMap = []; foreach (($equipment['data'] ?? []) as $x) $equipmentMap[$x['equipment_id']] = $x['name'] ?? ($x['display_id'] ?? '—');
                $staffMap = []; foreach (($users['data'] ?? []) as $x) $staffMap[$x['user_id']] = $x['full_name'] ?? ($x['username'] ?? '');

                foreach ($requests as &$req) {
                    $parts = [];
                    foreach ([
                        $facilityMap[$req['facility_id'] ?? ''] ?? '',
                        $floorMap[$req['floor_id'] ?? ''] ?? '',
                        $roomMap[$req['room_id'] ?? ''] ?? ''
                    ] as $part) if (trim((string)$part) !== '') $parts[] = trim((string)$part);
                    if (!$parts && !empty($req['equipment_id'])) $parts[] = $equipmentMap[$req['equipment_id']] ?? '';
                    $req['_location'] = $parts ? implode(' · ', array_unique($parts)) : 'Location not specified';
                    $req['_equipment'] = $equipmentMap[$req['equipment_id'] ?? ''] ?? '';
                    $assignedId = $req['assigned_to'] ?? '';
                    $req['_technician'] = $assignedId !== '' ? ($staffMap[$assignedId] ?? 'Pending Assignment') : 'Pending Assignment';
                    $req['_title'] = trim((string)($req['concern'] ?? '')) ?: 'Maintenance Request';
                    $req['_status'] = trim((string)($req['status'] ?? 'Pending')) ?: 'Pending';
                    $req['_status_class'] = historyStatusClass($req['_status']);
                    $req['_date'] = historyDate($req['date_reported'] ?? $req['created_at'] ?? null);
                    $req['_id'] = (string)($req['display_id'] ?? $req['request_id'] ?? '—');
                }
                unset($req);
            }
        }
    }
}

renderHead('History', 'History.css', 'regular');
?>
<div class="page">
    <section class="history-card">
        <?php renderPageHeader('My History', 'Maintenance Reporting Portal · Barangay Gulod', 'regular'); ?>

        <?php if ($pageError !== ''): ?>
            <div class="history-error" role="alert" style="margin:18px 0;padding:14px 16px;border-radius:10px;background:#fff0f0;border:1px solid #f0c3c3;color:#9d3030;font-size:13px;">
                <?= historyEscape($pageError) ?>
            </div>
        <?php endif; ?>

        <div class="history-controls">
            <div class="search-box">
                <input type="text" placeholder="Search requests..." id="historySearch" aria-label="Search requests">
                <span class="search-icon">⌕</span>
            </div>
            <select class="filter-select" id="historyFilter" aria-label="Filter by status">
                <option value="">All Statuses</option>
                <option value="in-progress">In Progress</option>
                <option value="pending">Pending</option>
                <option value="completed">Completed</option>
            </select>
            <button class="reset-button" type="button" id="historyReset">Reset</button>
            <button class="request-button" type="button" onclick="window.location.href='regular_report.php'">Report an Issue</button>
        </div>

        <div class="request-list" id="requestList">
            <?php foreach ($requests as $req): ?>
                <div class="request-card"
                     data-status="<?= historyEscape($req['_status_class']) ?>"
                     onclick="window.location.href='regular_request.php?id=<?= rawurlencode((string)($req['request_id'] ?? $req['_id'])) ?>'"
                     role="link" tabindex="0"
                     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                    <div class="request-main">
                        <div class="request-id"><?= historyEscape($req['_id']) ?></div>
                        <div class="request-title"><?= historyEscape($req['_title']) ?></div>
                        <div class="request-details"><?= historyEscape($req['_location']) ?> · <?= historyEscape($req['_date']) ?></div>
                        <?php if (!empty($req['_equipment'])): ?>
                            <div class="request-details">Equipment: <?= historyEscape($req['_equipment']) ?></div>
                        <?php endif; ?>
                        <div class="request-tech">Technician: <em><?= historyEscape($req['_technician']) ?></em></div>
                    </div>
                    <div class="request-side">
                        <span class="status <?= historyEscape($req['_status_class']) ?>"><?= historyEscape($req['_status']) ?></span>
                        <span class="chevron" aria-hidden="true">›</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="noResults" style="display:<?= (!$pageError && count($requests) === 0) ? 'block' : 'none' ?>;text-align:center;padding:40px;color:#687985;">
            <p style="font-size:15px;font-weight:600;"><?= count($requests) === 0 ? 'You have not submitted any maintenance requests yet.' : 'No requests found matching your search.' ?></p>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('historySearch');
    const filter = document.getElementById('historyFilter');
    const reset = document.getElementById('historyReset');
    const noResults = document.getElementById('noResults');
    const cards = Array.from(document.querySelectorAll('.request-card'));

    function applyFilters() {
        const q = search.value.toLowerCase().trim();
        const s = filter.value;
        let visible = 0;
        cards.forEach(function (card) {
            const text = card.textContent.toLowerCase();
            const status = card.dataset.status || '';
            const show = (!q || text.includes(q)) && (!s || status === s);
            card.style.display = show ? 'flex' : 'none';
            if (show) visible++;
        });
        noResults.style.display = visible === 0 ? 'block' : 'none';
        const message = noResults.querySelector('p');
        if (message && cards.length > 0) message.textContent = 'No requests found matching your search.';
    }
    search.addEventListener('input', applyFilters);
    filter.addEventListener('change', applyFilters);
    reset.addEventListener('click', function () { search.value = ''; filter.value = ''; applyFilters(); });
});
</script>
<?php renderFoot(); ?>
</html>
