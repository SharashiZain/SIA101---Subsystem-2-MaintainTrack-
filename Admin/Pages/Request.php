<?php
require_once '../../Components/layout.php';
require_once '../../Config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requestPageSupabase(string $method, string $endpoint, ?array $data = null, array $extraHeaders = []): array
{
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
    $headers = [
        'apikey: ' . SUPABASE_SECRET_KEY,
        'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    foreach ($extraHeaders as $header) $headers[] = $header;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) return ['success' => false, 'data' => null, 'error' => $error];
    $decoded = json_decode($response, true);
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => $decoded,
        'error' => ($httpCode >= 400) ? ($decoded['message'] ?? $decoded['details'] ?? $response) : null,
    ];
}

function requestPageText($value, string $fallback = '—'): string
{
    return htmlspecialchars((string)($value ?? $fallback), ENT_QUOTES, 'UTF-8');
}

function requestPageDate($value): string
{
    if (!$value) return '—';
    try {
        return (new DateTime($value))->format('M j, Y g:i A');
    } catch (Throwable $e) {
        return (string)$value;
    }
}

function requestPageStatusKey($status): string
{
    $key = strtolower(trim((string)$status));
    return preg_replace('/[^a-z0-9]+/', '-', $key) ?: 'pending';
}

$requests = [];
$pageError = '';
$formError = '';
$formSuccess = $_SESSION['admin_request_success'] ?? '';
unset($_SESSION['admin_request_success']);

function requestPageUpload(string $path, string $tmp, string $mime): array
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY')) return ['success'=>false,'error'=>'Supabase storage configuration is missing.'];
    $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/report-images/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $bytes = file_get_contents($tmp);
    if ($bytes === false) return ['success'=>false,'error'=>'Unable to read uploaded photo.'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>'POST', CURLOPT_POSTFIELDS=>$bytes, CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SECRET_KEY, 'Authorization: Bearer '.SUPABASE_SECRET_KEY, 'Content-Type: '.$mime, 'x-upsert: false'], CURLOPT_TIMEOUT=>60]);
    $body = curl_exec($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body === false || $error !== '') return ['success'=>false,'error'=>$error ?: 'Photo upload failed.'];
    if ($status < 200 || $status >= 300) { $json = json_decode($body, true); return ['success'=>false,'error'=>is_array($json) ? ($json['message'] ?? $json['error'] ?? $body) : $body]; }
    return ['success'=>true];
}

/* Admin-created requests use the same required fields and storage flow as the Regular User Report form. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['admin_request_action'] ?? '') === 'create_request') {
    $concern = trim((string)($_POST['concern'] ?? ''));
    $equipmentCategory = trim((string)($_POST['equipment_category'] ?? ''));
    $facilityId = trim((string)($_POST['facility_id'] ?? ''));
    $floorId = trim((string)($_POST['floor_id'] ?? ''));
    $roomId = trim((string)($_POST['room_id'] ?? ''));
    $equipmentId = trim((string)($_POST['equipment_id'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $date = trim((string)($_POST['date_reported'] ?? date('Y-m-d')));

    if ($concern === '' || !in_array($equipmentCategory, ['Electrical','Non Electrical'], true) || !$facilityId || !$floorId || !$roomId || !$equipmentId || $description === '' || $date === '') {
        $formError = 'Please complete all required fields.';
    }

    $currentUsername = (string)($_SESSION['username'] ?? $_SESSION['user'] ?? '');
    $currentUser = null;
    if ($formError === '' && $currentUsername === '') $formError = 'Your session has expired. Please log in again.';
    if ($formError === '') {
        $userResult = requestPageSupabase('GET', 'users?username=eq.'.rawurlencode($currentUsername).'&select=user_id,username,full_name,role,status&limit=1');
        if (!$userResult['success'] || empty($userResult['data'][0])) $formError = 'Unable to identify the logged-in admin. Please sign in again.';
        else {
            $currentUser = $userResult['data'][0];
            if (($currentUser['role'] ?? '') !== 'admin') $formError = 'Only an Admin can create a request from this page.';
            elseif (($currentUser['status'] ?? 'Active') !== 'Active') $formError = 'Your account is inactive. Contact the system administrator.';
        }
    }

    if ($formError === '') {
        $f = requestPageSupabase('GET', 'facilities?select=facility_id,name&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $fl = requestPageSupabase('GET', 'facility_floors?select=floor_id,facility_id,name&floor_id=eq.'.rawurlencode($floorId).'&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $rm = requestPageSupabase('GET', 'facility_rooms?select=room_id,floor_id,name&room_id=eq.'.rawurlencode($roomId).'&floor_id=eq.'.rawurlencode($floorId).'&limit=1');
        $eq = requestPageSupabase('GET', 'equipment?select=equipment_id,display_id,name,category,facility_id,floor_id,room_id,status&equipment_id=eq.'.rawurlencode($equipmentId).'&facility_id=eq.'.rawurlencode($facilityId).'&floor_id=eq.'.rawurlencode($floorId).'&room_id=eq.'.rawurlencode($roomId).'&status=eq.Active&limit=1');
        $facility = $f['data'][0] ?? null; $floor = $fl['data'][0] ?? null; $room = $rm['data'][0] ?? null; $selectedEquipment = $eq['data'][0] ?? null;
        if (!$facility || !$floor || !$room || !$selectedEquipment) $formError = 'The selected Facility, Floor, Room, or Equipment does not match the Registry.';
        elseif (($selectedEquipment['category'] ?? '') !== $equipmentCategory) $formError = 'The selected equipment does not match the chosen category.';
    }

    $dateTimestamp = null;
    if ($formError === '') {
        $dt = DateTime::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date || $date > date('Y-m-d')) $formError = 'Please enter a valid date.';
        else $dateTimestamp = $dt->format('Y-m-d\T00:00:00+08:00');
    }

    $imagePath = null;
    if ($formError === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) $formError = 'The photo could not be uploaded.';
        elseif ($file['size'] > 5 * 1024 * 1024) $formError = 'Photo must not exceed 5 MB.';
        else {
            $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($file['tmp_name']);
            $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($extensions[$mime])) $formError = 'Photo must be JPG, PNG, or WEBP.';
            else {
                $latest = requestPageSupabase('GET', 'maintenance_requests?select=display_id&display_id=like.MT-*&order=display_id.desc&limit=1');
                $last = $latest['data'][0]['display_id'] ?? ''; $next = 1;
                if (preg_match('/MT-(\d+)/', (string)$last, $match)) $next = (int)$match[1] + 1;
                $displayId = 'MT-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
                $imagePath = 'maintenance-reports/'.$displayId.'_'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];
                $upload = requestPageUpload($imagePath, $file['tmp_name'], $mime);
                if (!$upload['success']) { $formError = 'Photo upload failed: '.($upload['error'] ?? 'Unknown storage error.'); $imagePath = null; }
            }
        }
    }

    if ($formError === '') {
        $latest = requestPageSupabase('GET', 'maintenance_requests?select=display_id&display_id=like.MT-*&order=display_id.desc&limit=1');
        $last = $latest['data'][0]['display_id'] ?? ''; $next = 1;
        if (preg_match('/MT-(\d+)/', (string)$last, $match)) $next = (int)$match[1] + 1;
        $displayId = 'MT-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
        $requestCategory = $equipmentCategory === 'Electrical' ? 'Electrical' : 'Equipment';
        $payload = ['display_id'=>$displayId, 'concern'=>$concern, 'description'=>$description, 'category'=>$requestCategory, 'equipment_category'=>$equipmentCategory, 'priority'=>'Medium', 'status'=>'Pending', 'facility_id'=>$facilityId, 'floor_id'=>$floorId, 'room_id'=>$roomId, 'specific_area'=>$room['name'] ?? null, 'equipment_id'=>$equipmentId, 'reported_by'=>$currentUser['user_id'], 'image_path'=>$imagePath, 'date_reported'=>$dateTimestamp];
        $saved = requestPageSupabase('POST', 'maintenance_requests', $payload, ['Prefer: return=representation']);
        if (!$saved['success']) $formError = 'Unable to create request. '.($saved['error'] ?? 'Please check the database schema.');
        else {
            $created = $saved['data'][0] ?? []; $requestId = $created['request_id'] ?? null;
            if ($requestId) {
                requestPageSupabase('POST', 'request_timeline', ['request_id'=>$requestId, 'status'=>'Pending', 'actor_user_id'=>$currentUser['user_id'], 'note'=>'Maintenance request created by Admin']);
                requestPageSupabase('POST', 'activity_log', ['action_text'=>$displayId.' created by Admin '.($currentUser['full_name'] ?? $currentUsername), 'related_request_id'=>$requestId, 'actor_user_id'=>$currentUser['user_id']]);
            }
            $_SESSION['admin_request_success'] = 'Maintenance request '.$displayId.' created successfully.';
            header('Location: '.$_SERVER['PHP_SELF']); exit;
        }
    }
}


/* Load lookup tables first so the request list can show readable names. */
$requestsResult = requestPageSupabase(
    'GET',
    'maintenance_requests?select=request_id,display_id,concern,description,category,status,priority,facility_id,floor_id,room_id,equipment_id,reported_by,image_path,date_reported,created_at&order=date_reported.desc.nullslast,created_at.desc'
);
$facilitiesResult = requestPageSupabase('GET', 'facilities?select=facility_id,name');
$floorsResult = requestPageSupabase('GET', 'facility_floors?select=floor_id,facility_id,name,code');
$roomsResult = requestPageSupabase('GET', 'facility_rooms?select=room_id,floor_id,name,code');
$equipmentResult = requestPageSupabase('GET', 'equipment?select=equipment_id,display_id,name,type,category,status,facility_id,floor_id,room_id&order=name.asc');
$usersResult = requestPageSupabase('GET', 'users?select=user_id,full_name,username,email,role');

foreach ([
    'maintenance requests' => $requestsResult,
    'facilities' => $facilitiesResult,
    'floors' => $floorsResult,
    'rooms' => $roomsResult,
    'equipment' => $equipmentResult,
    'users' => $usersResult,
] as $label => $result) {
    if (!$result['success']) {
        $pageError = 'Unable to load ' . $label . ' from the database. ' . ($result['error'] ?? 'Please check the Supabase schema and connection.');
        break;
    }
}

if (!$pageError) {
    $facilityMap = [];
    foreach (($facilitiesResult['data'] ?? []) as $row) $facilityMap[$row['facility_id']] = $row['name'] ?? '';

    $floorMap = [];
    foreach (($floorsResult['data'] ?? []) as $row) $floorMap[$row['floor_id']] = $row['name'] ?? $row['code'] ?? '';

    $roomMap = [];
    foreach (($roomsResult['data'] ?? []) as $row) $roomMap[$row['room_id']] = $row['name'] ?? $row['code'] ?? '';

    $equipmentMap = [];
    foreach (($equipmentResult['data'] ?? []) as $row) {
        $label = trim(($row['display_id'] ?? '') . ' ' . ($row['name'] ?? $row['type'] ?? ''));
        $equipmentMap[$row['equipment_id']] = $label ?: ($row['name'] ?? '');
    }

    $userMap = [];
    foreach (($usersResult['data'] ?? []) as $row) {
        $userMap[$row['user_id']] = [
            'name' => $row['full_name'] ?? $row['username'] ?? $row['email'] ?? 'Unknown user',
            'username' => $row['username'] ?? '',
            'role' => $row['role'] ?? '',
        ];
    }

    foreach (($requestsResult['data'] ?? []) as $row) {
        $status = $row['status'] ?? 'Pending';
        $equipmentName = $equipmentMap[$row['equipment_id'] ?? ''] ?? '—';
        $facilityName = $facilityMap[$row['facility_id'] ?? ''] ?? '';
        $floorName = $floorMap[$row['floor_id'] ?? ''] ?? '';
        $roomName = $roomMap[$row['room_id'] ?? ''] ?? '';
        $parts = array_values(array_filter([$facilityName, $floorName, $roomName], fn($v) => $v !== ''));
        $requester = $userMap[$row['reported_by'] ?? ''] ?? ['name' => 'Unknown user', 'username' => '', 'role' => ''];

        $requests[] = [
            'id' => $row['display_id'] ?? $row['request_id'] ?? '',
            'requestId' => $row['request_id'] ?? '',
            'issue' => $row['concern'] ?? 'Maintenance request',
            'description' => $row['description'] ?? '',
            'equipment' => $equipmentName,
            'location' => $parts ? implode(' · ', $parts) : '—',
            'category' => $row['category'] ?? '—',
            'status' => $status,
            'statusKey' => requestPageStatusKey($status),
            'assignedTo' => $row['assigned_to_name'] ?? 'Unassigned',
            'requester' => $requester['name'],
            'username' => $requester['username'],
            'date' => requestPageDate($row['date_reported'] ?? $row['created_at'] ?? null),
        ];
    }
}

renderHead('Maintenance Requests', 'Request.css');
?>

<div class="page">

    <?php if ($formSuccess): ?><div style="margin:0 0 18px;padding:14px 18px;border-radius:10px;background:#eaf8ef;border:1px solid #a9d8b8;color:#247a3d;font-size:13px;font-weight:600;"><?= requestPageText($formSuccess) ?></div><?php endif; ?>
    <?php if ($formError): ?><div style="margin:0 0 18px;padding:14px 18px;border-radius:10px;background:#fdecec;border:1px solid #efb4b4;color:#a33a3a;font-size:13px;font-weight:600;"><?= requestPageText($formError) ?></div><?php endif; ?>
    <?php if ($pageError): ?>
        <div style="margin:0 0 18px;padding:14px 18px;border-radius:10px;background:#fdecec;border:1px solid #efb4b4;color:#a33a3a;font-size:13px;font-weight:600;">
            <?= requestPageText($pageError) ?>
        </div>
    <?php endif; ?>

    <section class="request-panel">
        <?php renderPageHeader('Maintenance Requests'); ?>
        <div class="filter-bar">
            <input type="text" placeholder="Search ID, issue, equipment" id="requestSearch">

            <select id="requestStatusFilter">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="assigned">Assigned</option>
                <option value="in-progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <select id="requestCategoryFilter">
                <option value="">All Categories</option>
                <?php
                $categories = [];
                foreach ($requests as $request) {
                    if ($request['category'] !== '—') $categories[$request['category']] = true;
                }
                foreach (array_keys($categories) as $category):
                ?>
                    <option value="<?= requestPageText($category) ?>"><?= requestPageText($category) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="requestLocationFilter">
                <option value="">All Labs</option>
                <?php
                $locations = [];
                foreach ($requests as $request) {
                    if ($request['location'] !== '—') $locations[$request['location']] = true;
                }
                foreach (array_keys($locations) as $location):
                ?>
                    <option value="<?= requestPageText($location) ?>"><?= requestPageText($location) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="button" id="requestReset">Reset</button>
            <button class="new-request" type="button" id="newRequestBtn">+ New Request</button>
        </div>

        <div class="table-wrapper request-table-wrap">
            <table class="request-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ISSUE / DESCRIPTION</th>
                        <th>EQUIPMENT</th>
                        <th>LOCATION</th>
                        <th>CATEGORY</th>
                        <th>STATUS</th>
                        <th>ASSIGNED TO</th>
                        <th>DATE REPORTED</th>
                    </tr>
                </thead>
                <tbody id="requestTableBody">
                    <?php foreach ($requests as $r): ?>
                        <tr
                            data-status="<?= requestPageText($r['statusKey']) ?>"
                            data-category="<?= requestPageText($r['category']) ?>"
                            data-location="<?= requestPageText($r['location']) ?>"
                            data-location="<?= requestPageText($r['location']) ?>"
                            data-search="<?= requestPageText($r['id'] . ' ' . $r['issue'] . ' ' . $r['description'] . ' ' . $r['equipment'] . ' ' . $r['location'] . ' ' . $r['category'] . ' ' . $r['requester'] . ' ' . $r['username']) ?>"
                            onclick="window.location.href='Request.php?id=<?= rawurlencode($r['requestId']) ?>'"
                            style="cursor:pointer;"
                        >
                            <td><?= requestPageText($r['id']) ?></td>
                            <td>
                                <b><?= requestPageText($r['issue']) ?></b>
                                <?php if ($r['description'] !== ''): ?><div style="font-size:12px;color:#687985;margin-top:4px;max-width:300px;"><?= requestPageText($r['description']) ?></div><?php endif; ?>
                                <div style="font-size:12px;color:#687985;margin-top:5px;">Requested by: <b><?= requestPageText($r['requester']) ?></b><?php if ($r['username'] !== ''): ?> (@<?= requestPageText($r['username']) ?>)<?php endif; ?></div>
                            </td>
                            <td><?= requestPageText($r['equipment']) ?></td>
                            <td><?= requestPageText($r['location']) ?></td>
                            <td><?= requestPageText($r['category']) ?></td>
                            <td><?= function_exists('statusBadge') ? statusBadge($r['status']) : requestPageText($r['status']) ?></td>
                            <td><?= requestPageText($r['assignedTo']) ?></td>
                            <td><?= requestPageText($r['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="requestNoResults" style="<?= empty($requests) ? 'display:block;' : 'display:none;' ?>text-align:center;padding:40px;color:#687985;">
            <p style="font-size:15px;font-weight:600;"><?= $pageError ? 'Requests could not be loaded.' : 'No maintenance requests found.' ?></p>
        </div>

        <div class="table-footer">
            <span id="requestCount"><?= count($requests) ?> requests shown</span>
            <div class="pagination" id="requestPagination">
                <button type="button" class="page-btn" data-page="prev">‹</button>
                <button type="button" class="page-btn active" data-page="1">1</button>
                <button type="button" class="page-btn" data-page="next">›</button>
            </div>
        </div>
    </section>
</div>

<!-- New Request modal: same fields and database workflow as Regular User Report.php -->
<div class="static-modal" id="newRequestModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="newRequestTitle">
  <div class="static-modal-content request-modal-wide">
    <div class="modal-header"><div><h3 id="newRequestTitle">New Maintenance Request</h3><p class="request-modal-subtitle">Create a request using the same information required on the Regular User Report page.</p></div><button type="button" class="modal-close" id="newRequestClose" aria-label="Close">&times;</button></div>
    <?php if ($formError): ?><div class="request-modal-error"><?=requestPageText($formError)?></div><?php endif; ?>
    <form id="adminRequestForm" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="admin_request_action" value="create_request">
      <div class="request-form-grid">
        <div class="request-form-field"><label for="adminConcern">Maintenance Concern <span>*</span></label><input id="adminConcern" name="concern" type="text" maxlength="200" required placeholder="e.g. Air conditioner is not cooling" value="<?=requestPageText($_POST['concern']??'', '')?>"></div>
        <div class="request-form-field"><label for="adminEquipmentCategory">Concern / Equipment Category <span>*</span></label><select id="adminEquipmentCategory" name="equipment_category" required><option value="">Select category</option><option value="Electrical">Electrical</option><option value="Non Electrical">Non Electrical</option></select></div>
        <div class="request-form-field"><label for="adminFacility">Facility <span>*</span></label><select id="adminFacility" name="facility_id" required><option value="">Select facility</option><?php foreach (($facilitiesResult['data']??[]) as $f): ?><option value="<?=requestPageText($f['facility_id']??'')?>"><?=requestPageText($f['name']??'')?></option><?php endforeach; ?></select></div>
        <div class="request-form-field"><label for="adminFloor">Floor <span>*</span></label><select id="adminFloor" name="floor_id" required><option value="">Select floor</option></select></div>
        <div class="request-form-field"><label for="adminRoom">Room <span>*</span></label><select id="adminRoom" name="room_id" required><option value="">Select room</option></select></div>
        <div class="request-form-field"><label for="adminEquipment">Equipment <span>*</span></label><select id="adminEquipment" name="equipment_id" required><option value="">Select equipment</option></select></div>
        <div class="request-form-field"><label for="adminDate">Date <span>*</span></label><input id="adminDate" name="date_reported" type="date" required max="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>"></div>
        <div class="request-form-field"><label for="adminPhoto">Photo <small>(optional)</small></label><input id="adminPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, or WEBP · Maximum 5 MB</small><div id="adminPhotoName" class="request-photo-name"></div></div>
        <div class="request-form-field request-description-field"><label for="adminDescription">Description <span>*</span></label><textarea id="adminDescription" name="description" rows="4" required placeholder="Describe the problem in detail..."><?=requestPageText($_POST['description']??'', '')?></textarea></div>
      </div>
      <div class="modal-actions"><button type="button" class="modal-cancel" id="newRequestCancel">Cancel</button><button type="submit" class="modal-save" id="adminRequestSubmit">Submit Request</button></div>
    </form>
  </div>
</div>

<style>
/* Keep the table fitted to the page instead of creating a horizontal scrollbar. */
.request-panel { min-width: 0; max-width: 100%; }
.request-table-wrap { width: 100%; max-width: 100%; overflow-x: hidden !important; }
.request-table { width: 100%; max-width: 100%; table-layout: fixed; border-collapse: collapse; }
.request-table th, .request-table td { box-sizing: border-box; white-space: normal !important; overflow-wrap: anywhere; word-break: normal; }
.request-table th:nth-child(1), .request-table td:nth-child(1) { width: 9%; }
.request-table th:nth-child(2), .request-table td:nth-child(2) { width: 24%; }
.request-table th:nth-child(3), .request-table td:nth-child(3) { width: 13%; }
.request-table th:nth-child(4), .request-table td:nth-child(4) { width: 13%; }
.request-table th:nth-child(5), .request-table td:nth-child(5) { width: 10%; }
.request-table th:nth-child(6), .request-table td:nth-child(6) { width: 9%; }
.request-table th:nth-child(7), .request-table td:nth-child(7) { width: 11%; }
.request-table th:nth-child(8), .request-table td:nth-child(8) { width: 11%; }
.request-table td div { max-width: 100% !important; overflow-wrap: anywhere; }
.filter-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
.new-request { flex:0 0 auto; white-space:nowrap; cursor:pointer; }
.static-modal { position:fixed; inset:0; z-index:9999; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.45); }
.static-modal-content { width:min(460px,100%); background:#fff; border-radius:14px; padding:22px; box-shadow:0 18px 55px rgba(0,0,0,.2); }
.modal-header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.modal-header h3 { margin:0; font-size:18px; }
.modal-close { border:0; background:transparent; font-size:26px; cursor:pointer; color:#64748b; }
.modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }

.request-modal-wide { width:min(820px,96vw); max-height:90vh; overflow-y:auto; }
.request-modal-subtitle { margin:6px 0 0; color:#687985; font-size:12px; line-height:1.45; }
.request-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 16px; margin-top:20px; }
.request-form-field { min-width:0; display:flex; flex-direction:column; gap:6px; }
.request-form-field label { font-size:12px; font-weight:650; color:#334155; }
.request-form-field label span { color:#d9534f; }
.request-form-field label small,.request-form-field>small { font-size:11px; color:#718096; font-weight:400; }
.request-form-field input:not([type=file]), .request-form-field select, .request-form-field textarea { width:100%; min-width:0; box-sizing:border-box; border:1px solid #d4dee8; border-radius:8px; padding:10px 11px; color:#263746; background:#fff; font:inherit; font-size:13px; }
.request-form-field input[type=file] { width:100%; min-width:0; font-size:12px; }
.request-form-field textarea { resize:vertical; }
.request-description-field { grid-column:1 / -1; }
.request-photo-name { color:#64748b; font-size:11px; overflow-wrap:anywhere; }
.request-modal-error { margin-top:12px; padding:10px 12px; background:#fdecec; border:1px solid #efb4b4; color:#a33a3a; border-radius:8px; font-size:12px; }
.modal-save { border:0; border-radius:8px; padding:10px 15px; background:#1769aa; color:white; font-weight:650; cursor:pointer; }
.modal-save:disabled { opacity:.65; cursor:wait; }
@media(max-width:650px) { .request-modal-wide { padding:16px; } .request-form-grid { grid-template-columns:1fr; } .request-description-field { grid-column:auto; } }
@media (max-width: 900px) { .request-table th, .request-table td { padding:8px 6px; font-size:11px; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('requestSearch');
    const statusFilter = document.getElementById('requestStatusFilter');
    const categoryFilter = document.getElementById('requestCategoryFilter');
    const locationFilter = document.getElementById('requestLocationFilter');
    const resetBtn = document.getElementById('requestReset');
    const noResults = document.getElementById('requestNoResults');
    const requestCount = document.getElementById('requestCount');
    const tbody = document.getElementById('requestTableBody');
    const allRows = Array.from(tbody.querySelectorAll('tr'));
    const pagination = document.getElementById('requestPagination');
    const perPage = 10;
    let currentPage = 1;
    let filteredRows = allRows;

    function renderPagination() {
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / perPage));
        currentPage = Math.min(currentPage, pageCount);
        pagination.innerHTML = '';
        const prev = document.createElement('button');
        prev.type = 'button'; prev.className = 'page-btn'; prev.dataset.page = 'prev'; prev.textContent = '‹';
        prev.disabled = currentPage === 1;
        pagination.appendChild(prev);

        for (let i = 1; i <= pageCount; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-btn' + (i === currentPage ? ' active' : '');
            btn.dataset.page = String(i);
            btn.textContent = String(i);
            pagination.appendChild(btn);
        }

        const next = document.createElement('button');
        next.type = 'button'; next.className = 'page-btn'; next.dataset.page = 'next'; next.textContent = '›';
        next.disabled = currentPage === pageCount;
        pagination.appendChild(next);

        pagination.querySelectorAll('.page-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (this.dataset.page === 'prev') currentPage = Math.max(1, currentPage - 1);
                else if (this.dataset.page === 'next') currentPage = Math.min(pageCount, currentPage + 1);
                else currentPage = Number(this.dataset.page);
                renderRows();
                renderPagination();
            });
        });
    }

    function renderRows() {
        allRows.forEach(row => row.style.display = 'none');
        const start = (currentPage - 1) * perPage;
        filteredRows.slice(start, start + perPage).forEach(row => row.style.display = '');
        noResults.style.display = filteredRows.length ? 'none' : 'block';
        requestCount.textContent = filteredRows.length
            ? `Showing ${start + 1}–${Math.min(start + perPage, filteredRows.length)} of ${filteredRows.length} requests`
            : '0 requests shown';
    }

    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        const status = statusFilter.value;
        const category = categoryFilter.value;
        const location = locationFilter.value;
        filteredRows = allRows.filter(function (row) {
            const text = (row.dataset.search || row.textContent).toLowerCase();
            return text.includes(query)
                && (!status || row.dataset.status === status)
                && (!category || row.dataset.category === category)
                && (!location || row.dataset.location === location);
        });
        currentPage = 1;
        renderRows();
        renderPagination();
    }

    searchInput.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    categoryFilter.addEventListener('change', applyFilters);
    locationFilter.addEventListener('change', applyFilters);
    resetBtn.addEventListener('click', function () {
        searchInput.value = '';
        statusFilter.value = '';
        categoryFilter.value = '';
        locationFilter.value = '';
        applyFilters();
    });

    const newRequestBtn = document.getElementById('newRequestBtn');
    const newRequestModal = document.getElementById('newRequestModal');
    const closeNewRequest = function () { newRequestModal.style.display = 'none'; };
    newRequestBtn.addEventListener('click', function () { newRequestModal.style.display = 'flex'; });
    document.getElementById('newRequestCancel').addEventListener('click', closeNewRequest);
    document.getElementById('newRequestClose').addEventListener('click', closeNewRequest);
    newRequestModal.addEventListener('click', function (event) { if (event.target === newRequestModal) closeNewRequest(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeNewRequest(); });

    const requestFloors = <?=json_encode($floorsResult['data']??[], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const requestRooms = <?=json_encode($roomsResult['data']??[], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const requestEquipment = <?=json_encode($equipmentResult['data']??[], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const adminCategory = document.getElementById('adminEquipmentCategory');
    const adminFacility = document.getElementById('adminFacility');
    const adminFloor = document.getElementById('adminFloor');
    const adminRoom = document.getElementById('adminRoom');
    const adminEquipment = document.getElementById('adminEquipment');
    function fillRequestSelect(select, placeholder, items, key, label) {
        select.innerHTML = '';
        const first = document.createElement('option'); first.value = ''; first.textContent = placeholder; select.appendChild(first);
        items.forEach(item => { const option = document.createElement('option'); option.value = item[key]; option.textContent = label(item); select.appendChild(option); });
    }
    function updateAdminEquipment() {
        const matches = requestEquipment.filter(item => item.facility_id === adminFacility.value && item.floor_id === adminFloor.value && item.room_id === adminRoom.value && item.category === adminCategory.value && item.status === 'Active');
        fillRequestSelect(adminEquipment, 'Select equipment', matches, 'equipment_id', item => (item.display_id ? item.display_id + ' — ' : '') + (item.name || item.type || 'Equipment') + (item.type ? ' (' + item.type + ')' : ''));
    }
    function updateAdminRooms() {
        fillRequestSelect(adminRoom, 'Select room', requestRooms.filter(item => item.floor_id === adminFloor.value), 'room_id', item => (item.name || item.code || 'Room') + (item.code ? ' (' + item.code + ')' : ''));
        updateAdminEquipment();
    }
    function updateAdminFloors() {
        fillRequestSelect(adminFloor, 'Select floor', requestFloors.filter(item => item.facility_id === adminFacility.value), 'floor_id', item => (item.name || item.code || 'Floor') + (item.code ? ' (' + item.code + ')' : ''));
        updateAdminRooms();
    }
    adminFacility.addEventListener('change', updateAdminFloors);
    adminFloor.addEventListener('change', updateAdminRooms);
    adminRoom.addEventListener('change', updateAdminEquipment);
    adminCategory.addEventListener('change', updateAdminEquipment);
    document.getElementById('adminPhoto').addEventListener('change', function () {
        const file = this.files && this.files[0];
        document.getElementById('adminPhotoName').textContent = file ? file.name : '';
        if (file && !['image/jpeg','image/png','image/webp'].includes(file.type)) { alert('Photo must be JPG, PNG, or WEBP.'); this.value = ''; document.getElementById('adminPhotoName').textContent = ''; }
        else if (file && file.size > 5 * 1024 * 1024) { alert('Photo must not exceed 5 MB.'); this.value = ''; document.getElementById('adminPhotoName').textContent = ''; }
    });
    document.getElementById('adminRequestForm').addEventListener('submit', function (event) {
        let valid = true;
        this.querySelectorAll('[required]').forEach(field => { if (!field.value.trim()) { field.style.borderColor = '#d9534f'; valid = false; } else field.style.borderColor = ''; });
        if (!valid) { event.preventDefault(); alert('Please complete all required fields.'); return; }
        const button = document.getElementById('adminRequestSubmit'); button.disabled = true; button.textContent = 'Submitting...';
    });

    <?php if ($formError): ?>newRequestModal.style.display = 'flex';<?php endif; ?>

    renderRows();
    renderPagination();
});
</script>

<?php renderFoot(); ?>
