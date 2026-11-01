<?php
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function secretaryDetailsRest(string $path): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'data' => null, 'error' => 'PHP cURL extension is not enabled.'];
    }
    $headers = [
        'apikey: ' . SUPABASE_SECRET_KEY,
        'Accept: application/json',
    ];
    $ch = curl_init(rtrim(SUPABASE_URL, '/') . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return ['ok' => false, 'data' => null, 'error' => $error ?: 'Unable to connect to Supabase.'];
    }
    $data = json_decode($response, true);
    return ['ok' => $status >= 200 && $status < 300, 'data' => $data, 'error' => $status >= 200 && $status < 300 ? null : 'Unable to load request details.'];
}

function secretaryDetailsEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$requestId = trim((string)($_GET['id'] ?? ''));
$request = null;
$pageError = '';
$lookups = [
    'facility' => 'Unknown facility',
    'floor' => '—',
    'room' => '—',
    'equipment' => 'N/A',
    'submitter' => 'Unknown',
];

if (!preg_match('/^MT-\d+$/', $requestId)) {
    $pageError = 'Invalid request ID. Please return to Approvals and select a request.';
} else {
    $fields = 'request_id,display_id,concern,description,category,priority,approval_status,rejection_reason,date_reported,specific_area,equipment_id,facility_id,floor_id,room_id,reported_by,created_at,image_path';
    $result = secretaryDetailsRest('/rest/v1/maintenance_requests?display_id=eq.' . rawurlencode($requestId) . '&select=' . rawurlencode($fields) . '&limit=1');
    if (!$result['ok'] || !is_array($result['data'])) {
        $pageError = 'Could not load this request from the database. Please try again.';
    } elseif (empty($result['data'][0])) {
        $pageError = 'Request not found. It may have been removed or the ID may be incorrect.';
    } else {
        $request = $result['data'][0];
        $lookups = [
            'facility' => ['table' => 'facilities', 'id' => 'facility_id', 'value' => 'name', 'fallback' => 'Unknown facility'],
            'floor' => ['table' => 'facility_floors', 'id' => 'floor_id', 'value' => 'name', 'fallback' => '—'],
            'room' => ['table' => 'facility_rooms', 'id' => 'room_id', 'value' => 'name', 'fallback' => '—'],
            'equipment' => ['table' => 'equipment', 'id' => 'equipment_id', 'value' => 'name', 'fallback' => 'N/A'],
            'submitter' => ['table' => 'users', 'id' => 'user_id', 'value' => 'full_name', 'fallback' => 'Unknown'],
        ];
        foreach ($lookups as $key => $cfg) {
            $fk = $key === 'submitter' ? ($request['reported_by'] ?? '') : ($request[$cfg['id']] ?? '');
            if (!$fk) { $lookups[$key] = $cfg['fallback']; continue; }
            $select = rawurlencode($cfg['id'] . ',' . $cfg['value']);
            $lookupResult = secretaryDetailsRest('/rest/v1/' . $cfg['table'] . '?' . $cfg['id'] . '=eq.' . rawurlencode((string)$fk) . '&select=' . $select . '&limit=1');
            $lookups[$key] = ($lookupResult['ok'] && !empty($lookupResult['data'][0][$cfg['value']]))
                ? $lookupResult['data'][0][$cfg['value']] : $cfg['fallback'];
        }
    }
}

$status = $request ? (($request['approval_status'] ?? '') ?: 'Pending') : '';
$date = '—';
if ($request && !empty($request['date_reported'])) {
    try { $dt = new DateTime($request['date_reported']); $dt->setTimezone(new DateTimeZone('Asia/Manila')); $date = $dt->format('M j, Y g:i A'); }
    catch (Throwable $e) { $date = (string)$request['date_reported']; }
}
$facilityDisplay = is_array($lookups['facility']) ? 'Unknown facility' : $lookups['facility'];
if ($request && !empty($request['specific_area'])) $facilityDisplay .= ' — ' . $request['specific_area'];

// Resolve the reported image like regular_request.php (private bucket-safe signed URL).
$imageUrl = '';
if ($request && !empty($request['image_path']) && defined('SUPABASE_URL')) {
    $imagePath = trim((string)$request['image_path']);
    if (preg_match('~^https?://~i', $imagePath)) {
        $imageUrl = $imagePath;
    } else {
        $imagePath = ltrim($imagePath, '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $imagePath)));
        if (defined('SUPABASE_SECRET_KEY') && function_exists('curl_init')) {
            $signUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/sign/report-images/' . $encodedPath;
            $ch = curl_init($signUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['expiresIn' => 3600]),
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_SECRET_KEY,
                    'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
                    'Content-Type: application/json', 'Accept: application/json'
                ], CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20
            ]);
            $signBody = curl_exec($ch);
            $signStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $signData = is_string($signBody) ? json_decode($signBody, true) : null;
            if ($signStatus >= 200 && $signStatus < 300 && is_array($signData) && !empty($signData['signedURL'])) {
                $signedPath = (string)$signData['signedURL'];
                $imageUrl = preg_match('~^https?://~i', $signedPath)
                    ? $signedPath
                    : rtrim(SUPABASE_URL, '/') . '/storage/v1/' . ltrim($signedPath, '/');
            } else {
                $imageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/report-images/' . $encodedPath;
            }
        } else {
            $imageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/report-images/' . $encodedPath;
        }
    }
}
renderHead('Request Details', 'Request.css', 'secretary');
?>
<style>
.secretary-detail-page{width:min(100%,1240px);margin:0 auto;padding:24px 20px 40px;color:#243447}
.secretary-detail-container{background:#fff;border:1px solid #d9e5ec;border-radius:16px;padding:16px;box-shadow:0 5px 24px rgba(20,45,70,.07)}
.secretary-detail-container .top-header{padding:16px 22px;margin:0 0 12px;border-radius:16px;background:linear-gradient(100deg,#123f68 0%,#155b91 55%,#1b69a3 100%);box-shadow:0 10px 28px rgba(18,63,104,.18);position:relative;overflow:hidden}
.secretary-detail-container .top-header h1{margin:0 0 4px;color:#fff;font-size:24px;font-weight:800;letter-spacing:-.5px}
.secretary-detail-container .top-header p{margin:0;color:rgba(255,255,255,.85);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.detail-back{display:inline-flex;align-items:center;gap:7px;margin:0 0 8px;color:#1769aa;text-decoration:none;font-size:12px;font-weight:600;border:1px solid #e4eaf0;border-radius:20px;padding:5px 11px;box-shadow:0 2px 5px rgba(20,45,70,.06)}
.detail-back:hover{text-decoration:underline}
.secretary-detail-body{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(260px,.8fr);gap:22px;margin-top:8px}
.secretary-left,.secretary-right{display:flex;flex-direction:column;gap:0;min-width:0}
.secretary-detail-container .request-id-card,.secretary-detail-container .stepper-card,.secretary-detail-container .maintenance-card,.secretary-detail-container .right-card{border:0;border-radius:0;background:transparent;padding:16px 0;box-shadow:none}
.secretary-detail-container .request-id-card{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #e7eef3;padding-top:0;padding-bottom:14px}
.secretary-detail-container .request-id-card h2,.secretary-detail-container .maintenance-header h2{margin:0;font-size:12px;font-weight:800;color:#155b91;text-transform:uppercase;letter-spacing:.06em;border-left:3px solid #1769aa;padding-left:9px}
.secretary-detail-container .request-id-value{font-size:11px;font-weight:800;color:#155b91;background:#e8eff8;border:1px solid #cfe1ee;border-radius:999px;padding:6px 12px;overflow-wrap:anywhere}
.secretary-detail-container .stepper-card{border-bottom:1px solid #e7eef3;padding:16px 0}
.secretary-detail-container .stepper-title{font-size:12px;margin:0 0 18px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#155b91}
.secretary-detail-container .stepper{list-style:none!important;padding:0!important;margin:0!important;display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:0!important;align-items:start!important;position:relative;width:100%}
.secretary-detail-container .step{display:flex;flex-direction:column;align-items:center;text-align:center;gap:5px;position:relative;min-width:0;padding:0 5px;color:#8b98a5;font-size:10px}
.secretary-detail-container .step:not(:last-child):before{content:"";position:absolute;left:50%;right:-50%;top:11px;height:2px;background:#dfe6ed;z-index:0}
.secretary-detail-container .step-dot{width:20px;height:20px;z-index:1;border-radius:50%;display:flex;align-items:center;justify-content:center;border:1.5px solid #dfe6ed;background:#fff;font-size:10px;box-sizing:border-box;color:#94a3b8}
.secretary-detail-container .step.done{color:#237a53}.secretary-detail-container .step.done .step-dot{background:#1769aa;border-color:#1769aa;color:#fff}
.secretary-detail-container .step.done:not(:last-child):before{background:#1769aa}
.secretary-detail-container .step.active{color:#1769aa;font-weight:700}.secretary-detail-container .step.active .step-dot{border-color:#2385d1;color:#2385d1;box-shadow:0 0 0 4px #e8f3ff}
.secretary-detail-container .step.hold{color:#a66a12;font-weight:700}.secretary-detail-container .step.hold .step-dot{border-color:#e5a63c;background:#fff6df;color:#a66a12}
.secretary-detail-container .step-label{font-size:10px;font-weight:700;line-height:1.2}.secretary-detail-container .step-time{font-size:9px;color:#8492a0;line-height:1.3}
.secretary-detail-container .maintenance-card{padding:16px 0 0}
.secretary-detail-container .maintenance-header{padding-bottom:12px;margin-bottom:12px}
.secretary-detail-container .details-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.secretary-detail-container .detail-box{min-width:0;display:flex;flex-direction:column;gap:3px;padding:9px 10px;background:#f4f8fb;border:1px solid #dbe7ef;border-radius:7px}
.secretary-detail-container .detail-label{font-size:9px;color:#788795;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.secretary-detail-container .detail-value{font-size:11px;font-weight:700;color:#253746;overflow-wrap:anywhere;white-space:pre-wrap;line-height:1.4}
.secretary-detail-container .description-box{display:flex;flex-direction:column;gap:6px;margin-top:8px;padding:10px;background:#f4f8fb;border:1px solid #dbe7ef;border-radius:7px;min-height:62px}
.secretary-right{padding-left:20px;border-left:1px solid #e7eef3}
.secretary-detail-container .right-card{padding:0}
.secretary-detail-container .right-card+.right-card{margin-top:18px;padding-top:14px;border-top:1px solid #e7eef3}
.secretary-detail-container .card-title{font-size:11px;font-weight:800;margin-bottom:8px;color:#155b91;text-transform:uppercase;letter-spacing:.05em;border-left:3px solid #1769aa;padding-left:8px}
.secretary-detail-container .attached-photo{display:block;width:auto!important;height:auto!important;max-width:220px!important;max-height:220px!important;object-fit:contain!important;object-position:center!important;margin:0 auto!important;border-radius:8px;background:#f7f9fb}
.secretary-detail-container .photo-note{font-size:9px;color:#8492a0;margin-top:7px}
.secretary-detail-container .update-item{display:flex;gap:7px;padding:4px 0}
.secretary-detail-container .update-mark{width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:#2c8b60;margin-top:5px}
.secretary-detail-container .update-copy{font-size:11px;line-height:1.5;color:#4e5e6c}
.secretary-detail-container .update-copy strong{color:#263746}
.detail-alert{margin:12px 0;padding:12px 14px;border-radius:10px;background:#fff0f0;border:1px solid #f0c3c3;color:#9d3030;font-size:13px}
.detail-status-pill{display:inline-block;padding:4px 8px;border-radius:20px;background:#fff3d8;color:#96630d;font-size:10px;font-weight:800}
.detail-status-pill.approved{background:#e5f7ed;color:#237a53}.detail-status-pill.rejected{background:#fee2e2;color:#991b1b}
@media(max-width:800px){.secretary-detail-body{grid-template-columns:1fr}.secretary-right{padding:14px 0 0;border-left:0;border-top:1px solid #e7eef3}.secretary-detail-container{padding:12px}}
@media(max-width:520px){.secretary-detail-page{padding:12px 8px}.secretary-detail-container .details-grid{grid-template-columns:1fr}.secretary-detail-container .stepper{grid-template-columns:repeat(2,minmax(0,1fr))!important;row-gap:14px!important}.secretary-detail-container .step:nth-child(2):before{display:none}}
</style>
<div class="secretary-detail-page">
  <section class="secretary-detail-container">
    <?php renderPageHeader('Request Detail', 'Maintenance Reporting Portal · Barangay Gulod', 'secretary'); ?>
    <a class="detail-back" href="secretary_approvals.php">‹ Back to Approvals</a>
    <?php if ($pageError): ?>
      <div class="detail-alert" role="alert"><?= secretaryDetailsEscape($pageError) ?></div>
    <?php elseif ($request): ?>
      <?php
        $approvalLower = strtolower(trim((string)$status));
        $isRejected = str_contains($approvalLower, 'reject');
        $isApproved = str_contains($approvalLower, 'approv');
        $stepLabels = ['Submitted', 'Pending Review', 'Approved', 'Completed'];
        $doneSteps = $isRejected ? 1 : ($isApproved ? 3 : 1);
        $activeStep = $isRejected ? 1 : ($isApproved ? 3 : 1);
      ?>
      <div class="secretary-detail-body">
        <div class="secretary-left">
          <div class="request-id-card"><h2>Request ID</h2><span class="request-id-value">ID#<?= secretaryDetailsEscape($request['display_id'] ?? $requestId) ?></span></div>
          <div class="stepper-card">
            <h3 class="stepper-title">Request Progress</h3>
            <ol class="stepper">
              <?php foreach ($stepLabels as $i => $label):
                $stepClass = '';
                $stepIcon = '';
                if ($isRejected && $i === 1) { $stepClass = 'hold'; $stepIcon = '!'; }
                elseif ($i < $doneSteps) { $stepClass = 'done'; $stepIcon = '✓'; }
                elseif ($i === $activeStep) { $stepClass = 'active'; $stepIcon = '●'; }
              ?>
                <li class="step <?= secretaryDetailsEscape($stepClass) ?>"><span class="step-dot"><?= secretaryDetailsEscape($stepIcon) ?></span><span class="step-label"><?= secretaryDetailsEscape($label) ?></span><span class="step-time"><?= $i === 0 ? secretaryDetailsEscape($date) : '—' ?></span></li>
              <?php endforeach; ?>
            </ol>
          </div>
          <div class="maintenance-card">
            <div class="maintenance-header"><h2>Maintenance Details</h2></div>
            <div class="details-grid">
              <div class="detail-box"><span class="detail-label">Concern</span><span class="detail-value"><?= secretaryDetailsEscape($request['concern'] ?? 'Maintenance Request') ?></span></div>
              <div class="detail-box"><span class="detail-label">Category</span><span class="detail-value"><?= secretaryDetailsEscape($request['category'] ?? $request['equipment_category'] ?? '—') ?></span></div>
              <div class="detail-box"><span class="detail-label">Facility</span><span class="detail-value"><?= secretaryDetailsEscape($lookups['facility']) ?></span></div>
              <div class="detail-box"><span class="detail-label">Equipment</span><span class="detail-value"><?= secretaryDetailsEscape($lookups['equipment']) ?></span></div>
              <div class="detail-box"><span class="detail-label">Specific Area</span><span class="detail-value"><?= secretaryDetailsEscape(trim(($lookups['floor'] !== '—' ? (string)$lookups['floor'] : '') . (($lookups['room'] !== '—' ? ' · ' . $lookups['room'] : '')) . (!empty($request['specific_area']) ? ' · ' . $request['specific_area'] : '')) ?: '—') ?></span></div>
              <div class="detail-box"><span class="detail-label">Date Reported</span><span class="detail-value"><?= secretaryDetailsEscape($date) ?></span></div>
              <div class="detail-box"><span class="detail-label">Submitted By</span><span class="detail-value"><?= secretaryDetailsEscape($lookups['submitter']) ?></span></div>
              <div class="detail-box"><span class="detail-label">Priority</span><span class="detail-value"><?= secretaryDetailsEscape($request['priority'] ?? 'Medium') ?></span></div>
              <div class="detail-box"><span class="detail-label">Status</span><span class="detail-value"><span class="detail-status-pill <?= $isApproved ? 'approved' : ($isRejected ? 'rejected' : '') ?>"><?= secretaryDetailsEscape($status === 'Pending' ? 'Waiting for Approval' : $status) ?></span></span></div>
            </div>
            <div class="description-box"><span class="detail-label">Description</span><span class="detail-value"><?= secretaryDetailsEscape(trim((string)($request['description'] ?? '')) ?: 'No description provided.') ?></span></div>
            <?php if (!empty($request['rejection_reason'])): ?><div class="description-box"><span class="detail-label">Decision Notes / Rejection Reason</span><span class="detail-value"><?= secretaryDetailsEscape($request['rejection_reason']) ?></span></div><?php endif; ?>
          </div>
        </div>
        <div class="secretary-right">
          <div class="right-card">
            <div class="card-title">Attached Photo</div>
            <?php if ($imageUrl !== ''): ?>
              <a href="<?= secretaryDetailsEscape($imageUrl) ?>" target="_blank" rel="noopener"><img class="attached-photo" src="<?= secretaryDetailsEscape($imageUrl) ?>" alt="Attached maintenance request photo"></a>
              <div class="photo-note">Select the photo to view it in full size.</div>
            <?php else: ?>
              <div style="font-size:11px;color:#8492a0;padding:12px 0"><?= !empty($request['image_path']) ? 'The attached photo is currently unavailable.' : 'No image attached' ?></div>
            <?php endif; ?>
          </div>
          <div class="right-card">
            <div class="card-title">Updates</div>
            <div class="update-item"><span class="update-mark"></span><div class="update-copy"><strong>Request submitted:</strong> The request was recorded on <?= secretaryDetailsEscape($date) ?>. Current status: <?= secretaryDetailsEscape($status === 'Pending' ? 'Waiting for Approval' : $status) ?>.</div></div>
            <?php if ($isApproved): ?><div class="update-item"><span class="update-mark"></span><div class="update-copy"><strong>Request approved:</strong> The current approval status is approved.</div></div><?php elseif ($isRejected): ?><div class="update-item"><span class="update-mark"></span><div class="update-copy"><strong>Request rejected:</strong> <?= secretaryDetailsEscape($request['rejection_reason'] ?? 'The request has been rejected.') ?></div></div><?php else: ?><div style="font-size:10px;color:#8492a0;line-height:1.5;margin-top:8px">This request is awaiting secretary review. Use the Approvals page to approve or reject it.</div><?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php renderFoot(); ?>
</html>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>
