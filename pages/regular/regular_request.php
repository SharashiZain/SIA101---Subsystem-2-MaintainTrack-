<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Components/layout.php';

require_once __DIR__ . '/../../Config/supabase.php';

if (session_status() === PHP_SESSION_NONE) session_start();



function regularRequestApi(string $endpoint): array {

    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {

        return ['success'=>false,'status'=>0,'data'=>null,'error'=>'Supabase configuration or PHP cURL is missing.'];

    }

    $ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/'));

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPGET => true,

        CURLOPT_HTTPHEADER => ['apikey: '.SUPABASE_SECRET_KEY, 'Authorization: Bearer '.SUPABASE_SECRET_KEY, 'Accept: application/json'],

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 30

    ]);

    $body = curl_exec($ch); $curlError = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

    if ($body === false || $curlError !== '') return ['success'=>false,'status'=>$status,'data'=>null,'error'=>$curlError ?: 'Request failed.'];

    $decoded = json_decode($body, true);

    return ['success'=>$status >= 200 && $status < 300,'status'=>$status,'data'=>$decoded,'error'=>($status < 200 || $status >= 300) ? (is_array($decoded) ? ($decoded['message'] ?? $decoded['details'] ?? $decoded['hint'] ?? $body) : $body) : null];

}

function regularRequestEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function regularRequestDate($value, bool $withTime = false): string {

    if (!$value) return '—';

    try { return (new DateTime((string)$value, new DateTimeZone('Asia/Manila')))->format($withTime ? 'M j · g:i A' : 'M j, Y'); }

    catch (Throwable $e) { return (string)$value; }

}

function regularRequestStatus($status): string {

    $status = strtolower(trim((string)$status));

    return match ($status) {

        'in progress', 'in-progress' => 'In Progress',

        'on hold', 'on-hold', 'hold' => 'On Hold',

        'assigned' => 'Assigned',

        'completed', 'closed', 'resolved' => 'Completed',

        default => 'Pending'

    };

}



$pageError = '';

$data = null;

$requestId = trim((string)($_GET['id'] ?? ''));

$currentUsername = trim((string)($_SESSION['username'] ?? $_SESSION['user'] ?? ''));

$facilityMap = $floorMap = $roomMap = $equipmentMap = $staffMap = [];

if ($currentUsername === '') {

    $pageError = 'Your session has expired. Please log in again.';

} elseif ($requestId === '') {

    $pageError = 'No request was selected. Please return to My History and select a request.';

} else {

    $lookupUsername = $currentUsername === 'user' ? 'psantos' : $currentUsername;

    $userResult = regularRequestApi('users?username=eq.'.rawurlencode($lookupUsername).'&select=user_id,username,full_name,role,status&limit=1');

    if (!$userResult['success'] || empty($userResult['data'][0])) {

        $pageError = 'Unable to identify your account. Please sign in again.';

    } elseif (($userResult['data'][0]['role'] ?? '') !== 'regular') {

        $pageError = 'This request detail page is available to Regular Users only.';

    } else {

        $currentUser = $userResult['data'][0];

        $_SESSION['user_id'] = $currentUser['user_id'];

        $_SESSION['username'] = $currentUser['username'];

        $_SESSION['full_name'] = $currentUser['full_name'] ?? '';



        // Query by UUID first; also support the display ID used by older history links.

        $select = 'request_id,display_id,concern,description,category,equipment_category,priority,status,facility_id,floor_id,room_id,equipment_id,reported_by,assigned_to,image_path,date_reported,created_at';

        $filterColumn = preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27,}$/i', $requestId) ? 'request_id' : 'display_id';

        $requestResult = regularRequestApi('maintenance_requests?reported_by=eq.'.rawurlencode((string)$currentUser['user_id']).'&'.$filterColumn.'=eq.'.rawurlencode($requestId).'&select='.rawurlencode($select).'&limit=1');

        if (!$requestResult['success']) {

            $pageError = 'Unable to load this request. Please check your connection and the maintenance_requests table.';

        } elseif (empty($requestResult['data'][0])) {

            // Do not fall back to another request: users must only see their own selected request.

            $pageError = 'Request not found, or you do not have permission to view it. Return to My History and select one of your requests.';

        } else {

            $data = $requestResult['data'][0];

            $lookups = [

                'facilities' => 'facilities?select=facility_id,name',

                'floors' => 'facility_floors?select=floor_id,name',

                'rooms' => 'facility_rooms?select=room_id,name',

                'equipment' => 'equipment?select=equipment_id,display_id,name,type',

                'users' => 'users?select=user_id,full_name,username'

            ];

            foreach ($lookups as $key => $endpoint) {

                $r = regularRequestApi($endpoint);

                foreach (($r['data'] ?? []) as $row) {

                    if ($key === 'facilities') $facilityMap[$row['facility_id']] = $row['name'] ?? '';

                    elseif ($key === 'floors') $floorMap[$row['floor_id']] = $row['name'] ?? '';

                    elseif ($key === 'rooms') $roomMap[$row['room_id']] = $row['name'] ?? '';

                    elseif ($key === 'equipment') $equipmentMap[$row['equipment_id']] = $row['name'] ?? ($row['display_id'] ?? ($row['type'] ?? ''));

                    elseif ($key === 'users') $staffMap[$row['user_id']] = $row['full_name'] ?? ($row['username'] ?? '');

                }

            }

        }

    }

}



$status = $data ? regularRequestStatus($data['status'] ?? 'Pending') : 'Pending';

$steps = [

    ['key'=>'submitted','label'=>'Submitted','time'=>$data ? regularRequestDate($data['date_reported'] ?? $data['created_at'] ?? null, true) : '—'],

    ['key'=>'assigned','label'=>'Assigned','time'=>'—'],

    ['key'=>'in-progress','label'=>'In Progress','time'=>'—'],

    ['key'=>'completed','label'=>'Completed','time'=>'—']

];

$doneCount = 1; $activeIndex = 1; $onHold = false;

if ($status === 'Assigned') { $doneCount = 2; $activeIndex = 2; }

elseif ($status === 'In Progress') { $doneCount = 3; $activeIndex = 2; }

elseif ($status === 'On Hold') { $doneCount = 3; $activeIndex = 2; $onHold = true; }

elseif ($status === 'Completed') { $doneCount = 4; $activeIndex = 4; }



$title = trim((string)($data['concern'] ?? '')) ?: 'Maintenance Request';

$facility = $facilityMap[$data['facility_id'] ?? ''] ?? '—';

$equipmentName = $equipmentMap[$data['equipment_id'] ?? ''] ?? '—';

$areaParts = [];

foreach ([$floorMap[$data['floor_id'] ?? ''] ?? '', $roomMap[$data['room_id'] ?? ''] ?? ''] as $part) if (trim((string)$part) !== '') $areaParts[] = trim((string)$part);

$area = $areaParts ? implode(' · ', array_unique($areaParts)) : '—';

$technician = !empty($data['assigned_to']) ? ($staffMap[$data['assigned_to']] ?? 'Assigned technician') : 'Pending Assignment';

$reportedDate = $data ? regularRequestDate($data['date_reported'] ?? $data['created_at'] ?? null) : '—';

$displayId = $data ? (string)($data['display_id'] ?? $data['request_id'] ?? $requestId) : $requestId;

$imageUrl = '';

if ($data && !empty($data['image_path']) && defined('SUPABASE_URL')) {

    $imagePath = trim((string)$data['image_path']);

    // Some older rows may store a full URL rather than a bucket-relative path.

    if (preg_match('~^https?://~i', $imagePath)) {

        $imageUrl = $imagePath;

    } else {

        $imagePath = ltrim($imagePath, '/');

        // The report-images bucket may be private, so create a short-lived signed URL

        // using the server-side Supabase secret instead of relying on a public URL.

        if (defined('SUPABASE_SECRET_KEY') && function_exists('curl_init')) {

            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $imagePath)));

            $signUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/sign/report-images/' . $encodedPath;

            $ch = curl_init($signUrl);

            curl_setopt_array($ch, [

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_POST => true,

                CURLOPT_POSTFIELDS => json_encode(['expiresIn' => 3600]),

                CURLOPT_HTTPHEADER => [

                    'apikey: ' . SUPABASE_SECRET_KEY,

                    'Authorization: Bearer ' . SUPABASE_SECRET_KEY,

                    'Content-Type: application/json',

                    'Accept: application/json'

                ],

                CURLOPT_CONNECTTIMEOUT => 10,

                CURLOPT_TIMEOUT => 20

            ]);

            $signBody = curl_exec($ch);

            $signStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            $signData = is_string($signBody) ? json_decode($signBody, true) : null;

            if ($signStatus >= 200 && $signStatus < 300 && is_array($signData) && !empty($signData['signedURL'])) {

                $signedPath = (string)$signData['signedURL'];

                $imageUrl = preg_match('~^https?://~i', $signedPath)

                    ? $signedPath

                    : rtrim(SUPABASE_URL, '/') . '/storage/v1' . '/' . ltrim($signedPath, '/');

            } else {

                // Fallback supports public buckets; private buckets use the signed URL above.

                $imageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/report-images/' . $encodedPath;

            }

        } else {

            $imageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/report-images/' . implode('/', array_map('rawurlencode', explode('/', $imagePath)));

        }

    }

}

$maintenanceUpdates = [];
if ($data && !empty($data['request_id'])) {
    $updatesResult = regularRequestApi(
        'maintenance_updates?request_id=eq.' . rawurlencode((string)$data['request_id']) .
        '&select=request_id,author_user_id,title,status,update_text,photo_path,created_at&order=created_at.desc'
    );
    if ($updatesResult['success'] && is_array($updatesResult['data'])) {
        foreach ($updatesResult['data'] as $update) {
            $update['author_name'] = $staffMap[$update['author_user_id'] ?? ''] ?? 'Maintenance Personnel';
            $update['display_status'] = trim((string)($update['status'] ?? '')) ?: 'Update';
            $update['display_text'] = (string)($update['update_text'] ?? '');
            $update['photo_url'] = '';
            $photoPath = trim((string)($update['photo_path'] ?? ''));
            if ($photoPath !== '') {
                if (preg_match('~^https?://~i', $photoPath)) {
                    $update['photo_url'] = $photoPath;
                } elseif (defined('SUPABASE_URL') && defined('SUPABASE_SECRET_KEY') && function_exists('curl_init')) {
                    $cleanPath = ltrim($photoPath, '/');
                    // Accept paths that already include the bucket name, or plain object paths.
                    if (str_starts_with($cleanPath, 'report-images/')) $cleanPath = substr($cleanPath, strlen('report-images/'));
                    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $cleanPath)));
                    $signUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/sign/report-images/' . $encodedPath;
                    $ch = curl_init($signUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode(['expiresIn' => 3600]),
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_SECRET_KEY,
                            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
                            'Content-Type: application/json',
                            'Accept: application/json'
                        ],
                        CURLOPT_CONNECTTIMEOUT => 10,
                        CURLOPT_TIMEOUT => 20
                    ]);
                    $signBody = curl_exec($ch);
                    $signStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $signData = is_string($signBody) ? json_decode($signBody, true) : null;
                    if ($signStatus >= 200 && $signStatus < 300 && is_array($signData) && !empty($signData['signedURL'])) {
                        $signedPath = (string)$signData['signedURL'];
                        $update['photo_url'] = preg_match('~^https?://~i', $signedPath)
                            ? $signedPath
                            : rtrim(SUPABASE_URL, '/') . '/storage/v1/' . ltrim($signedPath, '/');
                    } else {
                        $update['photo_url'] = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/report-images/' . $encodedPath;
                    }
                }
            }
            $maintenanceUpdates[] = $update;
        }
    }
}

renderHead('Request Details', 'Request.css', 'regular');

?>

<style>

*.request-page*{max-width:1240px;margin:0 auto;padding:24px 20px 40px;color:#243447}*.request-container*{background:#fff;border-radius:16px;padding:22px;box-shadow:0 5px 24px rgba(20,45,70,.07)}*.request-body*{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(260px,.8fr);gap:22px;margin-top:22px}*.left-column*,*.right-column*{display:flex;flex-direction:column;gap:18px;min-width:0}*.request-id-card*,*.stepper-card*,*.maintenance-card*,*.right-card*{border:1px solid #e4eaf0;border-radius:13px;background:#fff;padding:18px}*.request-id-card*{display:flex;align-items:center;justify-content:space-between;gap:12px}*.request-id-card* h2,*.maintenance-header* h2{font-size:16px;margin:0;font-weight:700}*.request-id-value*{font-size:14px;font-weight:700;color:#1769aa;background:#edf6ff;border-radius:8px;padding:8px 11px;overflow-wrap:anywhere}*.stepper-title*{font-size:16px;margin:0 0 18px}*.stepper*{list-style:none!important;padding:0!important;margin:0!important;display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:0!important;align-items:start!important;width:100%}*.step*{display:grid!important;grid-template-columns:28px minmax(0,1fr)!important;grid-template-rows:30px auto auto!important;column-gap:8px!important;row-gap:4px!important;align-items:center!important;position:relative!important;min-width:0!important;min-height:0!important;padding:0 8px 0 0!important;color:#8b98a5;font-size:12px!important;white-space:normal!important}*.step:not*(*:last-child*)*:before*{content:"";position:absolute;left:28px!important;right:8px!important;top:13px!important;height:2px!important;width:auto!important;background:#dfe6ed!important;z-index:0}*.step-dot*{grid-column:1!important;grid-row:1!important;z-index:1;width:24px!important;height:24px!important;border-radius:50%;display:flex!important;align-items:center;justify-content:center;border:1.5px solid #dfe6ed;background:#fff;font-size:11px!important;box-sizing:border-box}*.step-label*{grid-column:2!important;grid-row:1!important;align-self:center!important;min-width:0!important;font-size:11px!important;font-weight:700!important;line-height:1.2!important;overflow-wrap:anywhere}*.step-time*{grid-column:1 / -1!important;grid-row:2!important;text-align:left!important;font-size:10px!important;line-height:1.3!important;color:#8492a0;font-weight:400!important;padding-left:1px!important;white-space:normal!important}*.step.done*{color:#237a53}*.step.done* *.step-dot*{background:#e7f7ef;border-color:#58b88a;color:#237a53}*.step.done:not*(*:last-child*)*:before*{background:#58b88a!important}*.step.active*{color:#1769aa;font-weight:700}*.step.active* *.step-dot*{border-color:#2385d1;color:#2385d1;box-shadow:0 0 0 4px #e8f3ff}*.step.hold*{color:#a66a12;font-weight:700}*.step.hold* *.step-dot*{border-color:#e5a63c;background:#fff6df;color:#a66a12}*.stepper-hold-reason*{margin-top:12px;padding:10px 12px;border-radius:8px;background:#fff6df;color:#8b5b0b;font-size:13px}*.maintenance-header*{padding-bottom:15px;border-bottom:1px solid #edf0f3;margin-bottom:15px}*.details-grid*{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}*.detail-box*{min-width:0;display:flex;flex-direction:column;gap:6px}*.detail-label*{font-size:12px;color:#788795;font-weight:600}*.detail-value*{font-size:14px;color:#253746;overflow-wrap:anywhere;white-space:pre-wrap}*.description-box*{display:flex;flex-direction:column;gap:8px;margin-top:18px;padding:14px;background:#f7f9fb;border-radius:9px}*.right-card* *.card-title*{font-size:15px;font-weight:700;margin-bottom:14px}*.card-placeholder*{min-height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:15px;border:1px dashed #cbd6df;border-radius:10px;color:#82909c;text-align:center;font-size:13px}*.card-placeholder* svg{fill:none;stroke:currentColor;stroke-width:1.5}*.attached-photo*{display:block;width:100%;max-width:260px;max-height:260px;object-fit:contain;object-position:center;border-radius:10px;background:#f7f9fb;margin:0 auto}*.update-item*{display:flex;gap:10px;padding:12px 0;border-bottom:1px solid #edf0f3}*.update-item:last-child*{border-bottom:0;padding-bottom:0}*.update-mark*{width:9px;height:9px;flex:0 0 9px;border-radius:50%;background:#2c8b60;margin-top:5px}*.update-copy*{font-size:13px;line-height:1.5;color:#4e5e6c}*.update-copy* strong{display:block;color:#263746;margin-bottom:3px}*.request-alert*{margin:18px 0;padding:14px 16px;border-radius:10px;background:#fff0f0;border:1px solid #f0c3c3;color:#9d3030;font-size:13px}*.back-link*{display:inline-flex;align-items:center;gap:7px;margin:0 0 14px;color:#1769aa;text-decoration:none;font-size:13px;font-weight:600}*.back-link:hover*{text-decoration:underline}*.status-pill*{display:inline-block;padding:5px 10px;border-radius:20px;background:#fff3d8;color:#96630d;font-size:12px;font-weight:700}*.status-pill.completed*{background:#e5f7ed;color:#237a53}*.status-pill.in-progress*,*.status-pill.assigned*{background:#e8f3ff;color:#1769aa}*.status-pill.on-hold*{background:#fff3d8;color:#96630d}@media(max-width:800px){*.request-body*{grid-template-columns:1fr}*.request-container*{padding:15px}*.request-page*{padding:15px 10px}*.details-grid*{grid-template-columns:1fr 1fr}}@media(max-width:600px){*.stepper*{grid-template-columns:repeat(2,minmax(0,1fr))!important;row-gap:18px!important}*.step*{padding-right:8px!important}*.step:nth-child*(2)*:before*{display:none!important}}@media(max-width:480px){*.details-grid*{grid-template-columns:1fr}*.stepper*{grid-template-columns:repeat(2,minmax(0,1fr))!important}*.step-label*{font-size:11px!important}*.step-time*{font-size:10px!important}}

</style>

<div class="request-page">

  <section class="request-container">

    <?php renderPageHeader('My request', 'Maintenance Reporting Portal · Barangay Gulod', 'regular'); ?>

    <a class="back-link" href="regular_history.php">‹ Back to My History</a>

    <?php if ($pageError !== ''): ?>

      <div class="request-alert" role="alert"><?= regularRequestEscape($pageError) ?></div>

    <?php else: ?>

    <div class="request-body">

      <div class="left-column">

        <div class="request-id-card"><h2>Request ID</h2><span class="request-id-value">ID#<?= regularRequestEscape($displayId) ?></span></div>

        <div class="stepper-card">

          <h3 class="stepper-title">Request Progress</h3>

          <ol class="stepper">

          <?php foreach ($steps as $i => $step):

            $class = ''; $icon = '';

            if ($onHold && $step['key'] === 'in-progress') { $class = 'hold'; $icon = 'Ⅱ'; }

            elseif ($i < $doneCount) { $class = 'done'; $icon = '✓'; }

            elseif ($i === $activeIndex && $status !== 'Completed') { $class = 'active'; $icon = '●'; }

          ?>

            <li class="step <?= regularRequestEscape($class) ?>"><span class="step-dot"><?= regularRequestEscape($icon) ?></span><span class="step-label"><?= regularRequestEscape($step['label']) ?></span><span class="step-time"><?= regularRequestEscape($step['time']) ?></span></li>

          <?php endforeach; ?>

          </ol>

          <?php if ($onHold): ?><div class="stepper-hold-reason">Request is currently on hold. Please check the Updates section for available information.</div><?php endif; ?>

        </div>

        <div class="maintenance-card">

          <div class="maintenance-header"><h2>Maintenance Details</h2></div>

          <div class="details-grid">

            <div class="detail-box"><span class="detail-label">Concern</span><span class="detail-value"><?= regularRequestEscape($title) ?></span></div>

            <div class="detail-box"><span class="detail-label">Category</span><span class="detail-value"><?= regularRequestEscape($data['equipment_category'] ?? $data['category'] ?? '—') ?></span></div>

            <div class="detail-box"><span class="detail-label">Facility</span><span class="detail-value"><?= regularRequestEscape($facility) ?></span></div>

            <div class="detail-box"><span class="detail-label">Equipment</span><span class="detail-value"><?= regularRequestEscape($equipmentName) ?></span></div>

            <div class="detail-box"><span class="detail-label">Specific Area</span><span class="detail-value"><?= regularRequestEscape($area) ?></span></div>

            <div class="detail-box"><span class="detail-label">Date Reported</span><span class="detail-value"><?= regularRequestEscape($reportedDate) ?></span></div>

            <div class="detail-box"><span class="detail-label">Technician</span><span class="detail-value"><?= regularRequestEscape($technician) ?></span></div>

            <div class="detail-box"><span class="detail-label">Status</span><span class="detail-value"><span class="status-pill <?= regularRequestEscape(strtolower(str_replace(' ', '-', $status))) ?>"><?= regularRequestEscape($status) ?></span></span></div>

          </div>

          <div class="description-box"><span class="detail-label">Description</span><span class="detail-value"><?= regularRequestEscape(trim((string)($data['description'] ?? '')) ?: 'No description provided.') ?></span></div>

        </div>

      </div>

      <div class="right-column">

        <div class="right-card">

          <div class="card-title">Attached Photo</div>

          <?php if ($imageUrl !== ''): ?>

            <a href="<?= regularRequestEscape($imageUrl) ?>" target="_blank" rel="noopener"><img class="attached-photo" src="<?= regularRequestEscape($imageUrl) ?>" alt="Attached maintenance request photo" style="display:block!important;width:auto!important;max-width:220px!important;max-height:220px!important;height:auto!important;object-fit:contain!important;object-position:center!important;margin:0 auto!important;border-radius:10px;background:#f7f9fb;"></a>

            <div style="font-size:12px;color:#8492a0;margin-top:8px;">Select the photo to view it in full size.</div>

          <?php else: ?>

            <div class="card-placeholder"><svg viewBox="0 0 24 24" width="34" height="34" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"></rect><circle cx="9" cy="9.5" r="1.5"></circle><path d="M5 18l5-5.5 3 3.5 2.5-3 3.5 5"></path></svg><span>No image attached</span></div>

          <?php endif; ?>

        </div>

        <div class="right-card small">

          <div class="card-title">Updates</div>

          <div class="update-item"><span class="update-mark"></span><div class="update-copy"><strong>Request submitted : </strong>Your request was recorded on <?= regularRequestEscape($reportedDate) ?>. Current status: <?= regularRequestEscape($status) ?>.</div></div>

          <?php if (!empty($data['assigned_to'])): ?>

          <div class="update-item"><span class="update-mark"></span><div class="update-copy"><strong>Technician assigned</strong><?= regularRequestEscape($technician) ?> is assigned to this request. An assignment timestamp is not available in the current request record.</div></div>

          <?php endif; ?>

          <?php if ($status === 'Completed'): ?>

          <div class="update-item"><span class="update-mark"></span><div class="update-copy"><strong>Marked completed</strong>The current request status is Completed. A completion timestamp or technician note is not available in the current request record.</div></div>

          <?php endif; ?>

          <div style="font-size:11px;color:#8492a0;line-height:1.5;margin-top:12px;">Updates shown here are based on the request's current database fields. Detailed technician notes and exact status-change times will appear once an update/history field or table is connected.</div>

        </div>

      </div>

    </div>

    <?php endif; ?>

  </section>

  <?php if ($data && $pageError === ''): ?>
  <section class="regular-maintenance-history" style="background:#fff;border:1px solid #dfe7ef;border-radius:14px;padding:20px 22px;margin-top:22px;box-shadow:0 5px 24px rgba(20,45,70,.07);">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;border-bottom:1px solid #e9eef3;padding-bottom:14px;margin-bottom:8px;">
      <div>
        <h2 style="margin:0;color:#173b65;font-size:18px;font-weight:800;">Maintenance Updates</h2>
        <p style="margin:5px 0 0;color:#718096;font-size:12px;">Progress notes and photos submitted by the maintenance personnel.</p>
      </div>
    </div>

    <?php if (!$maintenanceUpdates): ?>
      <div style="padding:22px 14px;text-align:center;color:#718096;font-size:13px;border:1px dashed #cbd5e1;border-radius:10px;margin-top:14px;">No maintenance updates have been submitted for this request yet.</div>
    <?php else: foreach ($maintenanceUpdates as $update):
      $updateStatus = (string)($update['display_status'] ?? 'Update');
      $statusLower = strtolower($updateStatus);
      $badgeBg = $statusLower === 'completed' ? '#e5f7ed' : ($statusLower === 'fail to fix' ? '#fdecec' : '#e8f3ff');
      $badgeColor = $statusLower === 'completed' ? '#237a53' : ($statusLower === 'fail to fix' ? '#a33a3a' : '#1769aa');
      $updateTitle = trim((string)($update['title'] ?? '')) ?: 'Maintenance Update';
      $updateTime = !empty($update['created_at']) ? regularRequestDate($update['created_at'], true) : '—';
    ?>
      <article class="regular-maintenance-update" style="border:1px solid #dbe4ed;border-radius:12px;background:#fff;padding:16px 18px;margin-top:14px;box-shadow:0 2px 5px rgba(20,45,70,.04);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
          <div style="min-width:0;flex:1;">
            <div style="font-size:12px;font-weight:750;color:#243447;"> <?= regularRequestEscape($update['author_name'] ?? 'Maintenance Personnel') ?> </div>
            <h3 style="font-size:16px;line-height:1.35;color:#172b43;margin:7px 0 0;font-weight:800;overflow-wrap:anywhere;"><?= regularRequestEscape($updateTitle) ?></h3>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:7px;flex-shrink:0;">
            <span style="font-size:10px;color:#718096;white-space:nowrap;"><?= regularRequestEscape($updateTime) ?></span>
            <span style="display:inline-block;border-radius:999px;padding:5px 10px;background:<?= $badgeBg ?>;color:<?= $badgeColor ?>;font-size:10px;font-weight:800;white-space:nowrap;"><?= regularRequestEscape($updateStatus) ?></span>
          </div>
        </div>
        <?php if (trim((string)($update['display_text'] ?? '')) !== ''): ?>
          <p style="font-size:12px;line-height:1.65;color:#435366;margin:12px 0 0;white-space:pre-wrap;overflow-wrap:anywhere;"><?= regularRequestEscape($update['display_text']) ?></p>
        <?php endif; ?>
        <?php if (!empty($update['photo_url'])): ?>
          <div style="margin-top:12px;background:#f5f7fa;border:1px solid #e3e9ef;border-radius:10px;padding:10px;display:flex;justify-content:center;align-items:center;">
            <a href="<?= regularRequestEscape($update['photo_url']) ?>" target="_blank" rel="noopener" style="display:block;max-width:100%;">
              <img src="<?= regularRequestEscape($update['photo_url']) ?>" alt="Maintenance update photo" style="display:block;width:auto!important;height:auto!important;max-width:100%!important;max-height:260px!important;object-fit:contain!important;border-radius:7px;">
            </a>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; endif; ?>
  </section>
  <?php endif; ?>

</div>

<?php renderFoot(); ?>

</html>
