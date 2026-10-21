<?php
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/PriorityScore.php';
require_once __DIR__ . '/../../includes/DuplicateReport.php';

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
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) return ['success' => false, 'status' => 0, 'data' => null, 'error' => $error];
    $decoded = json_decode($response, true);
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
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

// Removes an uploaded photo from storage when the database insert fails after the upload.
function requestPageDeleteUpload(string $path): bool
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY')) return false;
    $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/report-images/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>'DELETE', CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SECRET_KEY, 'Authorization: Bearer '.SUPABASE_SECRET_KEY], CURLOPT_TIMEOUT=>30]);
    curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $status >= 200 && $status < 300;
}

// Next MT- number, same as the Regular User Report page.
function requestPageNextDisplayId(): string
{
    $latest = requestPageSupabase('GET', 'maintenance_requests?select=display_id&display_id=like.MT-*&order=display_id.desc&limit=1');
    $last = $latest['data'][0]['display_id'] ?? '';
    $next = 1;
    if (preg_match('/MT-(\d+)/', (string)$last, $match)) $next = (int)$match[1] + 1;
    return 'MT-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}

/* Pre-check called by the page's JavaScript before the New Request form is sent (keeps the chosen photo). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['check_duplicate'])) {
    $dup = findDuplicateReport(
        function (string $endpoint): ?array { $r = requestPageSupabase('GET', $endpoint); return ($r['success'] && is_array($r['data'])) ? $r['data'] : null; },
        trim((string)($_POST['room_id'] ?? '')), trim((string)($_POST['equipment_id'] ?? '')), (string)($_POST['concern'] ?? '')
    );
    header('Content-Type: application/json');
    echo json_encode($dup ? ['duplicate'=>true, 'label'=>duplicateReportLabel($dup), 'concern'=>$dup['concern'], 'display_id'=>$dup['display_id'], 'progress'=>duplicateReportProgress($dup), 'handled'=>duplicateReportIsHandled($dup)] : ['duplicate'=>false, 'check_failed'=>!empty($GLOBALS['duplicateCheckFailed'])]);
    exit;
}

/* New requests created by an Admin use the same fields, checks, auto priority and storage flow as the Regular User Report page. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['admin_request_action'] ?? '') === 'create_request') {
    $concern = reportUppercase((string)($_POST['concern'] ?? ''));
    $equipmentCategory = trim((string)($_POST['equipment_category'] ?? ''));
    $facilityId = trim((string)($_POST['facility_id'] ?? ''));
    $floorId = trim((string)($_POST['floor_id'] ?? ''));
    $roomId = trim((string)($_POST['room_id'] ?? ''));
    $equipmentId = trim((string)($_POST['equipment_id'] ?? ''));
    $description = reportUppercase((string)($_POST['description'] ?? ''));
    $date = trim((string)($_POST['date_reported'] ?? date('Y-m-d')));

    if ($concern === '' || !in_array($equipmentCategory, ['Electrical','Non Electrical'], true) || !$facilityId || !$floorId || !$roomId || $description === '' || $date === '') {
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

    $facility = $floor = $room = $selectedEquipment = null;
    if ($formError === '') {
        $f = requestPageSupabase('GET', 'facilities?select=facility_id,name&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $facility = $f['data'][0] ?? null;
        $fl = requestPageSupabase('GET', 'facility_floors?select=floor_id,facility_id,name&floor_id=eq.'.rawurlencode($floorId).'&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $floor = $fl['data'][0] ?? null;
        $rm = requestPageSupabase('GET', 'facility_rooms?select=room_id,floor_id,name&room_id=eq.'.rawurlencode($roomId).'&floor_id=eq.'.rawurlencode($floorId).'&limit=1');
        $room = $rm['data'][0] ?? null;
        // Equipment is optional: it is only checked against the Registry when one was picked.
        if ($equipmentId !== '') {
            $eq = requestPageSupabase('GET', 'equipment?select=equipment_id,display_id,name,type,category,facility_id,floor_id,room_id,status&equipment_id=eq.'.rawurlencode($equipmentId).'&facility_id=eq.'.rawurlencode($facilityId).'&floor_id=eq.'.rawurlencode($floorId).'&room_id=eq.'.rawurlencode($roomId).'&status=eq.Active&limit=1');
            $selectedEquipment = $eq['data'][0] ?? null;
        }
        if (!$facility || !$floor || !$room || ($equipmentId !== '' && !$selectedEquipment)) {
            $formError = 'The selected Facility, Floor, Room, or Equipment does not match the Registry. Please select again.';
        } elseif ($equipmentId !== '' && ($selectedEquipment['category'] ?? '') !== $equipmentCategory) {
            $formError = 'The selected equipment does not match the chosen category.';
        }
    }

    $dateTimestamp = null;
    if ($formError === '') {
        $dt = DateTime::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date || $date > date('Y-m-d')) $formError = 'Please enter a valid date.';
        else $dateTimestamp = $dt->format('Y-m-d\T00:00:00+08:00');
    }

    // Same concern already open for this room/equipment? Ask for confirmation (the page normally asks first via JavaScript).
    if ($formError === '' && ($_POST['confirm_duplicate'] ?? '') !== '1') {
        $dup = findDuplicateReport(
            function (string $endpoint): ?array { $r = requestPageSupabase('GET', $endpoint); return ($r['success'] && is_array($r['data'])) ? $r['data'] : null; },
            $roomId, $equipmentId, $concern
        );
        if ($dup) $formError = 'Unable to create request. This concern was already reported: '.duplicateReportLabel($dup).'. '.duplicateReportProgress($dup).' If it is a different problem, press Submit Request again and choose "Submit anyway".';
    }

    $imagePath = null; $displayId = '';
    if ($formError === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) $formError = 'The photo could not be uploaded.';
        elseif ($file['size'] > 5 * 1024 * 1024) $formError = 'Photo must not exceed 5 MB.';
        else {
            $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($file['tmp_name']);
            $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($extensions[$mime])) $formError = 'Photo must be JPG, PNG, or WEBP.';
            else {
                $displayId = requestPageNextDisplayId();
                $imagePath = 'maintenance-reports/'.$displayId.'_'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];
                $upload = requestPageUpload($imagePath, $file['tmp_name'], $mime);
                if (!$upload['success']) { $formError = 'Photo upload failed: '.($upload['error'] ?? 'Unknown storage error.'); $imagePath = null; }
            }
        }
    }

    if ($formError === '') {
        // Keep the existing request_category enum; the exact Electrical/Non Electrical choice is stored separately.
        $requestCategory = $equipmentCategory === 'Electrical' ? 'Electrical' : 'Equipment';
        $saved = null;

        // Priority comes from the priority score (includes/PriorityScore.php); falls back to Medium if scoring fails.
        $autoPriority = 'Medium';
        try {
            $since = gmdate('Y-m-d\TH:i:s\Z', time() - PRIORITY_RECURRENCE_WINDOW_DAYS * 86400);
            $recentRows = [];
            // Recurrence is counted per equipment, so with no equipment selected there is nothing to compare.
            $recent = $equipmentId !== '' ? requestPageSupabase('GET', 'maintenance_requests?select=request_id,equipment_id,date_reported&equipment_id=eq.'.rawurlencode($equipmentId).'&date_reported=gte.'.rawurlencode($since)) : ['success'=>false,'data'=>[]];
            foreach (($recent['success'] && is_array($recent['data'])) ? $recent['data'] : [] as $rr) {
                $recentRows[] = ['id'=>(string)($rr['request_id']??''),'equipment_id'=>(string)($rr['equipment_id']??''),'date_reported'=>$rr['date_reported']??''];
            }
            $scored = priorityAutoLevel([
                'id'=>'NEW','facility'=>(string)($facility['name']??''),'room'=>(string)($room['name']??''),
                'equipment_id'=>$equipmentId,'date_reported'=>$dateTimestamp,
                'equipment_name'=>(string)($selectedEquipment['name']??''),'equipment_type'=>(string)($selectedEquipment['type']??''),
                'concern'=>$concern
            ], $recentRows);
            $autoPriority = $scored['level'];
        } catch (Throwable $e) {
            $autoPriority = 'Medium';
        }

        // Up to 3 tries: if two requests get the same MT- number at the same moment (HTTP 409), take the next number and retry.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0 || $displayId === '') $displayId = requestPageNextDisplayId();
            $payload = [
                'display_id'=>$displayId, 'concern'=>$concern, 'description'=>$description,
                'category'=>$requestCategory, 'equipment_category'=>$equipmentCategory,
                'priority'=>$autoPriority, 'status'=>'Pending', 'facility_id'=>$facilityId,
                'floor_id'=>$floorId, 'room_id'=>$roomId, 'specific_area'=>$room['name'] ?? null,
                'equipment_id'=>$equipmentId !== '' ? $equipmentId : null, 'reported_by'=>$currentUser['user_id'],
                'image_path'=>$imagePath, 'date_reported'=>$dateTimestamp
            ];
            $saved = requestPageSupabase('POST', 'maintenance_requests', $payload, ['Prefer: return=representation']);
            if ($saved['success'] || (int)$saved['status'] !== 409) break;
        }

        if (!$saved['success']) {
            $formError = 'Unable to create request. '.($saved['error'] ?? 'Please check the database schema.');
            // The request was not saved, so remove the photo that was already uploaded.
            if ($imagePath) { requestPageDeleteUpload($imagePath); $imagePath = null; }
        } else {
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


/* Update or delete an existing maintenance request from the Admin request details modal. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['admin_request_action'] ?? ''), ['update_request','delete_request'], true)) {
    $action = (string)$_POST['admin_request_action'];
    $requestId = trim((string)($_POST['request_id'] ?? ''));
    if ($requestId === '') {
        $_SESSION['admin_request_success'] = 'Unable to process request: missing request ID.';
    } elseif ($action === 'update_request') {
        $concern = trim((string)($_POST['edit_concern'] ?? ''));
        $description = trim((string)($_POST['edit_description'] ?? ''));
        $category = trim((string)($_POST['edit_category'] ?? ''));
        $priority = trim((string)($_POST['edit_priority'] ?? ''));
        $status = trim((string)($_POST['edit_status'] ?? ''));
        $facilityId = trim((string)($_POST['edit_facility_id'] ?? ''));
        $floorId = trim((string)($_POST['edit_floor_id'] ?? ''));
        $roomId = trim((string)($_POST['edit_room_id'] ?? ''));
        $equipmentId = trim((string)($_POST['edit_equipment_id'] ?? ''));
        $reportedBy = trim((string)($_POST['edit_reported_by'] ?? ''));
        $assignedTo = trim((string)($_POST['edit_assigned_to'] ?? ''));
        $dateReported = trim((string)($_POST['edit_date_reported'] ?? ''));
        $allowedCategories = ['Electrical','Equipment','Non Electrical','Non-Electrical'];
        $allowedPriorities = ['Low','Medium','High','Urgent'];
        $allowedStatuses = ['Pending','Assigned','In Progress','Completed','Cancelled','Rejected'];
        $dateValue = DateTime::createFromFormat('!Y-m-d', $dateReported, new DateTimeZone('Asia/Manila'));
        $validDate = $dateValue && $dateValue->format('Y-m-d') === $dateReported && $dateReported <= date('Y-m-d');
        if ($concern === '' || $description === '' || !in_array($category, $allowedCategories, true) || !in_array($priority, $allowedPriorities, true) || !in_array($status, $allowedStatuses, true) || !$facilityId || !$floorId || !$roomId || !$equipmentId || !$reportedBy || !$validDate) {
            $_SESSION['admin_request_success'] = 'Update failed: please complete all editable request details with valid values.';
        } else {
            $payload = ['concern'=>$concern,'description'=>$description,'category'=>$category,'priority'=>$priority,'status'=>$status,'facility_id'=>$facilityId,'floor_id'=>$floorId,'room_id'=>$roomId,'equipment_id'=>$equipmentId,'reported_by'=>$reportedBy,'assigned_to'=>($assignedTo !== '' ? $assignedTo : null),'date_reported'=>$dateValue->format('Y-m-d\T00:00:00+08:00')];
            $payload['specific_area'] = null;
            $roomCheck = requestPageSupabase('GET', 'facility_rooms?select=name&room_id=eq.'.rawurlencode($roomId).'&limit=1');
            if (!empty($roomCheck['data'][0]['name'])) $payload['specific_area'] = $roomCheck['data'][0]['name'];
            $updated = requestPageSupabase('PATCH', 'maintenance_requests?request_id=eq.'.rawurlencode($requestId), $payload, ['Prefer: return=representation']);
            if ($updated['success'] && !empty($updated['data'])) {
                $_SESSION['admin_request_success'] = 'Maintenance request updated successfully.';
            } else {
                $_SESSION['admin_request_success'] = 'Unable to update request. '.($updated['error'] ?? 'Please check the database schema.');
            }
        }
    } else {
        $deleted = requestPageSupabase('DELETE', 'maintenance_requests?request_id=eq.'.rawurlencode($requestId), null, ['Prefer: return=representation']);
        if ($deleted['success']) $_SESSION['admin_request_success'] = 'Maintenance request deleted successfully.';
        else $_SESSION['admin_request_success'] = 'Unable to delete request. '.($deleted['error'] ?? 'Please check the database schema.');
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}


/* Load lookup tables first so the request list can show readable names. */
$requestsResult = requestPageSupabase(
    'GET',
    'maintenance_requests?select=request_id,display_id,concern,description,category,status,priority,facility_id,floor_id,room_id,equipment_id,reported_by,assigned_to,image_path,date_reported,created_at&order=date_reported.desc.nullslast,created_at.desc'
);
$facilitiesResult = requestPageSupabase('GET', 'facilities?select=facility_id,name');
$floorsResult = requestPageSupabase('GET', 'facility_floors?select=floor_id,facility_id,name,code');
$roomsResult = requestPageSupabase('GET', 'facility_rooms?select=room_id,floor_id,name,code');
$equipmentResult = requestPageSupabase('GET', 'equipment?select=equipment_id,display_id,name,type,category,status,facility_id,floor_id,room_id&order=name.asc');
$usersResult = requestPageSupabase('GET', 'users?select=user_id,full_name,username,email,role,specialization_category,specialization_type');

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
            'facilityId' => $row['facility_id'] ?? '',
            'floorId' => $row['floor_id'] ?? '',
            'roomId' => $row['room_id'] ?? '',
            'equipmentId' => $row['equipment_id'] ?? '',
            'reportedById' => $row['reported_by'] ?? '',
            'assignedToId' => $row['assigned_to'] ?? '',
            'dateRaw' => !empty($row['date_reported']) ? date('Y-m-d', strtotime($row['date_reported'])) : (!empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : date('Y-m-d')),
            'issue' => $row['concern'] ?? 'Maintenance request',
            'description' => $row['description'] ?? '',
            'equipment' => $equipmentName,
            'location' => $parts ? implode(' · ', $parts) : '—',
            'category' => $row['category'] ?? '—',
            'priority' => $row['priority'] ?? '—',
            'status' => $status,
            'statusKey' => requestPageStatusKey($status),
            'assignedTo' => !empty($row['assigned_to']) ? ($userMap[$row['assigned_to']]['name'] ?? 'Assigned personnel') : 'Unassigned',
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
                            data-search="<?= requestPageText($r['id'] . ' ' . $r['issue'] . ' ' . $r['description'] . ' ' . $r['equipment'] . ' ' . $r['location'] . ' ' . $r['category'] . ' ' . $r['requester'] . ' ' . $r['username'] . ' ' . $r['assignedTo']) ?>"
                            data-request="<?= requestPageText(json_encode(['requestId'=>$r['requestId'],'id'=>$r['id'],'issue'=>$r['issue'],'description'=>$r['description'],'equipment'=>$r['equipment'],'location'=>$r['location'],'category'=>$r['category'],'priority'=>$r['priority'],'status'=>$r['status'],'assignedTo'=>$r['assignedTo'],'requester'=>$r['requester'],'username'=>$r['username'],'date'=>$r['date'],'facilityId'=>$r['facilityId'],'floorId'=>$r['floorId'],'roomId'=>$r['roomId'],'equipmentId'=>$r['equipmentId'],'reportedById'=>$r['reportedById'],'assignedToId'=>$r['assignedToId'],'dateRaw'=>$r['dateRaw']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
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

<!-- Loading overlay shown while a new request is being submitted -->
<div class="loading-overlay" id="loadingOverlay" hidden role="status" aria-live="polite" aria-label="Submitting request">
    <img src="<?= requestPageText(appAssetUrl('assets/images/loading.svg'), '') ?>" alt="Loading">
</div>

<!-- Request details modal -->
<div class="static-modal" id="requestDetailsModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="requestDetailsTitle">
  <div class="static-modal-content request-details-content">
    <div class="modal-header">
      <div><h3 id="requestDetailsTitle">Request Details</h3><p class="request-modal-subtitle" id="requestDetailsId">Maintenance request information</p></div>
      <button type="button" class="modal-close" id="requestDetailsClose" aria-label="Close">&times;</button>
    </div>
    <div class="request-details-grid">
      <div class="request-detail-item"><span>Concern</span><strong id="detailIssue">—</strong></div>
      <div class="request-detail-item"><span>Status</span><strong id="detailStatus">—</strong></div>
      <div class="request-detail-item"><span>Priority</span><strong id="detailPriority">—</strong></div>
      <div class="request-detail-item"><span>Category</span><strong id="detailCategory">—</strong></div>
      <div class="request-detail-item"><span>Equipment</span><strong id="detailEquipment">—</strong></div>
      <div class="request-detail-item"><span>Location</span><strong id="detailLocation">—</strong></div>
      <div class="request-detail-item"><span>Requested By</span><strong id="detailRequester">—</strong></div>
      <div class="request-detail-item"><span>Assigned To</span><strong id="detailAssignedTo">—</strong></div>
      <div class="request-detail-item"><span>Date Reported</span><strong id="detailDate">—</strong></div>
      <div class="request-detail-item request-detail-description"><span>Description</span><p id="detailDescription">—</p></div>
    </div>
    <div class="modal-actions request-detail-actions"><div class="request-detail-action-group"><button type="button" class="modal-edit" id="requestDetailsEdit">Edit</button><button type="button" class="modal-delete" id="requestDetailsDelete">Delete</button></div><button type="button" class="modal-cancel" id="requestDetailsDone">Close</button></div>
  </div>
</div>


<!-- Edit Request modal -->
<div class="static-modal" id="editRequestModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="editRequestTitle">
  <div class="static-modal-content request-modal-wide">
    <div class="modal-header"><div><h3 id="editRequestTitle">Edit Maintenance Request</h3><p class="request-modal-subtitle" id="editRequestSubtitle">Update request information</p></div><button type="button" class="modal-close" id="editRequestClose" aria-label="Close">&times;</button></div>
    <form method="POST" id="editRequestForm">
      <input type="hidden" name="admin_request_action" value="update_request"><input type="hidden" name="request_id" id="editRequestId">
      <div class="request-form-grid">
        <div class="request-form-field"><label for="editConcern">Concern *</label><input id="editConcern" name="edit_concern" maxlength="200" required></div>
        <div class="request-form-field"><label for="editStatus">Status *</label><select id="editStatus" name="edit_status" required><option>Pending</option><option>Assigned</option><option>In Progress</option><option>Completed</option><option>Cancelled</option><option>Rejected</option></select></div>
        <div class="request-form-field"><label for="editPriority">Priority *</label><select id="editPriority" name="edit_priority" required><option>Low</option><option>Medium</option><option>High</option><option>Urgent</option></select></div>
        <div class="request-form-field"><label for="editCategory">Category *</label><select id="editCategory" name="edit_category" required><option value="Electrical">Electrical</option><option value="Equipment">Equipment</option><option value="Non Electrical">Non Electrical</option><option value="Non-Electrical">Non-Electrical</option></select></div>
        <div class="request-form-field"><label for="editFacility">Facility *</label><select id="editFacility" name="edit_facility_id" required><option value="">Select facility</option><?php foreach (($facilitiesResult['data']??[]) as $f): ?><option value="<?=requestPageText($f['facility_id']??'')?>"><?=requestPageText($f['name']??'')?></option><?php endforeach; ?></select></div>
        <div class="request-form-field"><label for="editFloor">Floor *</label><select id="editFloor" name="edit_floor_id" required><option value="">Select floor</option><?php foreach (($floorsResult['data']??[]) as $f): ?><option value="<?=requestPageText($f['floor_id']??'')?>" data-facility="<?=requestPageText($f['facility_id']??'')?>"><?=requestPageText($f['name']??$f['code']??'')?></option><?php endforeach; ?></select></div>
        <div class="request-form-field"><label for="editRoom">Room *</label><select id="editRoom" name="edit_room_id" required><option value="">Select room</option><?php foreach (($roomsResult['data']??[]) as $r): ?><option value="<?=requestPageText($r['room_id']??'')?>" data-floor="<?=requestPageText($r['floor_id']??'')?>"><?=requestPageText($r['name']??$r['code']??'')?></option><?php endforeach; ?></select></div>
        <div class="request-form-field"><label for="editEquipment">Equipment *</label><select id="editEquipment" name="edit_equipment_id" required><option value="">Select equipment</option><?php foreach (($equipmentResult['data']??[]) as $e): ?><option value="<?=requestPageText($e['equipment_id']??'')?>" data-facility="<?=requestPageText($e['facility_id']??'')?>" data-floor="<?=requestPageText($e['floor_id']??'')?>" data-room="<?=requestPageText($e['room_id']??'')?>"><?=requestPageText(trim(($e['display_id']??'').' '.($e['name']??$e['type']??'')))?></option><?php endforeach; ?></select></div>
        <div class="request-form-field"><label for="editReportedBy">Requested By *</label><select id="editReportedBy" name="edit_reported_by" required><option value="">Select requester</option><?php foreach (($usersResult['data']??[]) as $u): ?><option value="<?=requestPageText($u['user_id']??'')?>"><?=requestPageText($u['full_name']??$u['username']??$u['email']??'Unknown user')?><?=!empty($u['username'])?' (@'.requestPageText($u['username']).')':''?></option><?php endforeach; ?></select></div>
        <div class="request-form-field"><label for="editAssignedTo">Assigned Maintenance Personnel</label><select id="editAssignedTo" name="edit_assigned_to"><option value="">Unassigned</option><?php foreach (($usersResult['data']??[]) as $u): ?>
          <?php
            $personRole = strtolower(trim((string)($u['role'] ?? '')));
            $personType = trim((string)($u['specialization_type'] ?? ''));
            $personCategory = trim((string)($u['specialization_category'] ?? ''));
            $electricalTypes = ['computer technician','network technician','electrician','aircon technician'];
            $nonElectricalTypes = ['plumber','pipefitter','carpenter','mason','painter','general maintenance technician'];
            $typeKey = strtolower($personType);
            if (!in_array(strtolower($personCategory), ['electrical','non-electrical','non electrical'], true)) {
                if (in_array($typeKey, $electricalTypes, true)) $personCategory = 'Electrical';
                elseif (in_array($typeKey, $nonElectricalTypes, true)) $personCategory = 'Non-Electrical';
            }
            $isMaintenancePerson = (strpos($personRole, 'maintenance') !== false) || ($personType !== '');
          ?>
          <?php if ($isMaintenancePerson && !empty($u['user_id'])): ?>
            <option value="<?=requestPageText($u['user_id'])?>" data-specialization="<?=requestPageText($personCategory)?>" data-specialization-type="<?=requestPageText($personType)?>"><?=requestPageText($u['full_name']??$u['username']??$u['email']??'Maintenance Personnel')?><?= $personType !== '' ? ' — '.requestPageText($personType) : '' ?></option>
          <?php endif; ?>
        <?php endforeach; ?></select><small id="editAssignedHint" class="request-field-hint">Personnel are filtered according to the request category.</small></div>
        <div class="request-form-field"><label for="editDateReported">Date Reported *</label><input type="date" id="editDateReported" name="edit_date_reported" required></div>
        <div class="request-form-field request-description-field"><label for="editDescription">Description *</label><textarea id="editDescription" name="edit_description" rows="4" required></textarea></div>
      </div>
      <div class="modal-actions"><button type="button" class="modal-cancel" id="editRequestCancel">Cancel</button><button type="submit" class="modal-save">Save Changes</button></div>
    </form>
  </div>
</div>
<form method="POST" id="deleteRequestForm" style="display:none"><input type="hidden" name="admin_request_action" value="delete_request"><input type="hidden" name="request_id" id="deleteRequestId"></form>

<!-- Duplicate warning (shown before a new request is sent) -->
<div class="static-modal" id="duplicateRequestModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="duplicateRequestTitle">
  <div class="static-modal-content request-result-content">
    <div class="request-result-icon" style="background:#fff3d9;color:#a86408;" aria-hidden="true">!</div>
    <h3 id="duplicateRequestTitle">This concern may already be reported</h3>
    <p id="duplicateRequestText"></p>
    <div class="modal-actions"><button type="button" class="modal-cancel" id="duplicateRequestCancel">Cancel</button><button type="button" class="modal-save" id="duplicateRequestProceed">Submit anyway</button></div>
  </div>
</div>

<!-- Result / notice modal (success, errors, missing fields) -->
<div class="static-modal" id="requestResultModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="requestResultTitle">
  <div class="static-modal-content request-result-content">
    <div class="request-result-icon" id="requestResultIcon" aria-hidden="true">✓</div>
    <h3 id="requestResultTitle">Request Saved</h3>
    <p id="requestResultMessage"><?=requestPageText($formSuccess, '')?></p>
    <div class="modal-actions"><button type="button" class="modal-save" id="requestResultOk">OK</button></div>
  </div>
</div>

<!-- New Request modal: same fields, checks, photo upload and layout as the Regular User Report page -->
<div class="static-modal" id="newRequestModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="newRequestTitle">
  <div class="static-modal-content new-report-card">
    <div class="modal-header">
      <div><h3 id="newRequestTitle">New Maintenance Request</h3><p class="request-modal-subtitle">Same information required on the Regular User Report page.</p></div>
      <button type="button" class="modal-close" id="newRequestClose" aria-label="Close">&times;</button>
    </div>
    <?php if ($formError): ?><div class="request-modal-error"><?=requestPageText($formError)?></div><?php endif; ?>

    <form id="adminRequestForm" method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="admin_request_action" value="create_request">
      <input type="hidden" name="confirm_duplicate" id="confirmDuplicate" value="0">

      <div class="section-title section-title-row">
        <span>Maintenance Concern Details</span>
        <button type="button" class="section-reset-btn" id="resetDetailsBtn" aria-label="Reset Maintenance Concern Details" title="Reset this section">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><polyline points="3 3 3 9 9 9"></polyline></svg>
        </button>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="concern">Maintenance Concern <span class="req">*</span></label>
          <input type="text" id="concern" name="concern" maxlength="200" required placeholder="e.g. Air conditioner is not cooling" value="<?=requestPageText($_POST['concern'] ?? '', '')?>">
        </div>
        <div class="form-group">
          <label for="equipment_category">Concern / Equipment Category <span class="req">*</span></label>
          <select id="equipment_category" name="equipment_category" required>
            <option value="" disabled hidden style="display:none" <?=($_POST['equipment_category']??'')===''?'selected':''?>>Select category</option>
            <option value="Electrical" <?=($_POST['equipment_category']??'')==='Electrical'?'selected':''?>>Electrical</option>
            <option value="Non Electrical" <?=($_POST['equipment_category']??'')==='Non Electrical'?'selected':''?>>Non Electrical</option>
          </select>
        </div>
      </div>

      <div class="section-title section-title-row">
        <span>Facility & Equipment Reference</span>
        <button type="button" class="section-reset-btn" id="resetFacilityBtn" aria-label="Reset Facility and Equipment Reference" title="Reset this section">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><polyline points="3 3 3 9 9 9"></polyline></svg>
        </button>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="facility_id">Facility <span class="req">*</span></label>
          <select id="facility_id" name="facility_id" required>
            <option value="" disabled hidden style="display:none" <?=($_POST['facility_id']??'')===''?'selected':''?>>Select facility</option>
            <?php foreach (($facilitiesResult['data'] ?? []) as $f): ?>
              <option value="<?=requestPageText($f['facility_id']??'', '')?>" <?=($_POST['facility_id']??'')===($f['facility_id']??'')?'selected':''?>><?=requestPageText($f['name']??'', '')?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="floor_id">Floor <span class="req">*</span></label>
          <div class="select-wrap"><select id="floor_id" name="floor_id" required><option value="" disabled selected hidden style="display:none">Select floor</option></select><div class="select-guard"></div></div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="room_id">Room <span class="req">*</span></label>
          <div class="select-wrap"><select id="room_id" name="room_id" required><option value="" disabled selected hidden style="display:none">Select room</option></select><div class="select-guard"></div></div>
        </div>
        <div class="form-group">
          <label for="equipment_id">Equipment <span class="optional-tag">(optional)</span></label>
          <div class="select-wrap"><select id="equipment_id" name="equipment_id"><option value="" disabled selected hidden style="display:none">Select equipment (optional)</option></select><div class="select-guard"></div></div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="date_reported">Date <span class="req">*</span></label>
          <input type="date" id="date_reported" name="date_reported" required max="<?=date('Y-m-d')?>" value="<?=requestPageText($_POST['date_reported'] ?? date('Y-m-d'), '')?>">
        </div>
        <div class="form-group photo-group">
          <label for="photo">Photo <span class="optional-tag">(optional)</span></label>
          <div class="upload-box" id="uploadBox">
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
            <label for="photo" class="upload-click-area" id="uploadClickArea">
              <span class="upload-text" id="uploadText"><strong>Click to upload a photo</strong></span>
            </label>
            <div class="selected-file" id="selectedFile" hidden><span class="file-name" id="fileName"></span><button type="button" class="view-image-btn" id="viewImageBtn">View</button></div>
          </div>
          <small class="form-hint">JPG, PNG, or WEBP · Maximum 5 MB</small>
        </div>
      </div>

      <div class="form-group description-group">
        <label for="description">Description <span class="req">*</span></label>
        <textarea id="description" name="description" rows="3" required placeholder="Describe the problem in detail..."><?=requestPageText($_POST['description'] ?? '', '')?></textarea>
      </div>

      <div class="form-actions">
        <button type="button" class="cancel-button" id="cancelButton">Cancel</button>
        <button type="submit" class="submit-button" id="submitButton">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- Photo preview (opened from the View button in the New Request form) -->
<div class="image-modal" id="imageModal" hidden>
    <div class="image-modal-content">
        <button type="button" class="close-image-modal" id="closeImageModal" aria-label="Close image preview">&times;</button>
        <img id="imageModalPreview" src="" alt="Selected photo preview">
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

.request-result-content { width:min(420px,100%); text-align:center; padding:28px 24px 22px; }
.request-result-content h3 { margin:10px 0 8px; color:#173b65; font-size:19px; }
.request-result-content p { margin:0; color:#526174; font-size:13px; line-height:1.55; overflow-wrap:anywhere; }
.request-result-icon { width:48px; height:48px; margin:0 auto; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#dcfce7; color:#15803d; font-size:27px; font-weight:800; }
.request-result-content .modal-actions { justify-content:center; margin-top:22px; }

.request-modal-wide { width:min(820px,96vw); max-height:90vh; overflow-y:auto; }
.request-modal-subtitle { margin:6px 0 0; color:#687985; font-size:12px; line-height:1.45; }
.request-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 16px; margin-top:20px; }
.request-form-field { min-width:0; display:flex; flex-direction:column; gap:6px; }
.request-form-field label { font-size:12px; font-weight:650; color:#334155; }
.request-form-field label small,.request-form-field>small { font-size:11px; color:#718096; font-weight:400; }
.request-form-field input:not([type=file]), .request-form-field select, .request-form-field textarea { width:100%; min-width:0; box-sizing:border-box; border:1px solid #d4dee8; border-radius:8px; padding:10px 11px; color:#263746; background:#fff; font:inherit; font-size:13px; }
.request-form-field input[type=file] { width:100%; min-width:0; font-size:12px; }
.request-form-field textarea { resize:vertical; }
.request-description-field { grid-column:1 / -1; }
.request-field-hint { display:block; margin-top:6px; font-size:12px; color:#718096; line-height:1.4; }
.request-modal-error { margin-top:12px; padding:10px 12px; background:#fdecec; border:1px solid #efb4b4; color:#a33a3a; border-radius:8px; font-size:12px; }
.modal-save { border:0; border-radius:8px; padding:10px 15px; background:#1769aa; color:white; font-weight:650; cursor:pointer; }
.modal-save:disabled { opacity:.65; cursor:wait; }
.modal-cancel { border:1px solid #d4dee8; border-radius:8px; padding:10px 15px; background:#fff; color:#334155; font-weight:650; cursor:pointer; }
.modal-edit, .modal-delete { border:0; border-radius:8px; padding:10px 15px; font-weight:650; cursor:pointer; }
.modal-edit { background:#1769aa; color:#fff; }
.modal-delete { background:#fee2e2; color:#b91c1c; }
.request-detail-actions { justify-content:space-between; align-items:center; gap:12px; }
.request-detail-action-group { display:flex; align-items:center; gap:10px; }
.request-detail-actions .modal-cancel { margin-left:auto; }

.request-details-content { width:min(760px,96vw); max-height:90vh; overflow-y:auto; }
.request-details-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:20px; }
.request-detail-item { min-width:0; padding:12px 13px; border:1px solid #e2e8f0; border-radius:9px; background:#f8fafc; display:flex; flex-direction:column; gap:6px; }
.request-detail-item span { color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.035em; }
.request-detail-item strong { color:#243447; font-size:13px; font-weight:650; overflow-wrap:anywhere; }
.request-detail-description { grid-column:1 / -1; }
.request-detail-description p { margin:0; color:#334155; font-size:13px; line-height:1.6; white-space:pre-wrap; overflow-wrap:anywhere; }
.request-table tbody tr:hover { background:#f5f9fc; }
@media(max-width:650px) { .request-details-content { padding:16px; } .request-details-grid { grid-template-columns:1fr; } .request-detail-description { grid-column:auto; } }

@media(max-width:650px) { .request-modal-wide { padding:16px; } .request-form-grid { grid-template-columns:1fr; } .request-description-field { grid-column:auto; } }
@media (max-width: 900px) { .request-table th, .request-table td { padding:8px 6px; font-size:11px; } }

/* ---- New Request modal: same design as the Regular User Report page, fitted to the window without scrolling ---- */
#newRequestModal { padding:12px; }
#newRequestModal .new-report-card { width:min(860px,96vw); max-height:none; overflow:hidden; padding:16px 20px; border-radius:16px; }
#newRequestModal .modal-header h3 { font-size:17px; color:#111c25; }
#newRequestModal .request-modal-subtitle { margin:2px 0 0; font-size:11.5px; }
#newRequestModal .request-modal-error { margin-top:10px; padding:8px 12px; background:#fdecec; border:1px solid #efb4b4; color:#a33a3a; border-radius:8px; font-size:12px; font-weight:600; }
#newRequestModal .section-title { margin:8px 0; padding:5px 12px; border-left:4px solid #1769aa; border-radius:8px; background:#eaf3f8; color:#155b91; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.8px; line-height:1.5; }
#newRequestModal .section-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
#newRequestModal .section-reset-btn { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:26px; height:26px; margin:0; padding:0; border:1px solid #b7d0e0; border-radius:7px; background:#fff; color:#1769aa; cursor:pointer; transition:background .15s, border-color .15s; }
#newRequestModal .section-reset-btn:hover { background:#eef5fa; border-color:#1769aa; }
#newRequestModal .section-reset-btn:focus-visible { outline:2px solid #1769aa; outline-offset:2px; }
#newRequestModal .section-reset-btn svg { width:14px; height:14px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
#newRequestModal .form-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px 16px; }
#newRequestModal .form-group { min-width:0; margin:0; display:flex; flex-direction:column; }
#newRequestModal .form-group label { display:block; margin:0 0 4px 2px; color:#111c25; font-size:12px; font-weight:700; }
#newRequestModal .req { color:#dc2626; }
#newRequestModal .optional-tag { color:#94a3b8; font-weight:500; }
#newRequestModal .form-group input:not([type="file"]), #newRequestModal .form-group select { width:100%; height:36px; box-sizing:border-box; padding:0 12px; border:1px solid #d9e5ec; border-radius:8px; background:#fff; color:#17212b; font:inherit; font-size:13px; outline:none; transition:border-color .18s ease, box-shadow .18s ease; }
#newRequestModal .form-group textarea { width:100%; height:50px; box-sizing:border-box; padding:8px 12px; border:1px solid #d9e5ec; border-radius:8px; background:#fff; color:#17212b; font:inherit; font-size:13px; outline:none; resize:none; transition:border-color .18s ease, box-shadow .18s ease; }
#newRequestModal .form-group input:focus, #newRequestModal .form-group select:focus, #newRequestModal .form-group textarea:focus { border-color:#2f85c8; box-shadow:0 0 0 3px rgba(47,128,199,.15); }
#newRequestModal #concern, #newRequestModal #description { text-transform:uppercase; }
#newRequestModal .select-wrap { position:relative; width:100%; }
#newRequestModal .select-guard { display:none; position:absolute; inset:0; z-index:2; }
#newRequestModal .select-wrap.locked .select-guard { display:block; }
#newRequestModal .form-group select:disabled { background:#f1f5f9; color:#94a3b8; opacity:1; }
#newRequestModal .form-group.is-locked label { color:#94a3b8; }
#newRequestModal .description-group { margin-top:10px; }
#newRequestModal .upload-box { position:relative; height:50px; border:2px dashed #b7d0e0; border-radius:8px; background:linear-gradient(180deg,#f9fcfe,#eef5fa); }
#newRequestModal .upload-box:hover { border-color:#2f85c8; }
#newRequestModal .upload-box input[type="file"] { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
#newRequestModal .form-group label.upload-click-area { position:absolute; inset:0; z-index:2; margin:0; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#155b91; font-size:12px; font-weight:400; }
#newRequestModal .form-group label.upload-click-area:hover { background:rgba(255,255,255,.45); }
#newRequestModal .upload-text strong { font-size:12px; color:#155b91; }
#newRequestModal .selected-file { position:absolute; z-index:3; left:6px; right:6px; bottom:6px; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:4px 6px 4px 10px; background:#fff; border:1px solid #d6e2ec; border-radius:7px; }
#newRequestModal .selected-file[hidden] { display:none; }
#newRequestModal .file-name { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#17212b; font-size:12px; }
#newRequestModal .view-image-btn { flex:0 0 auto; height:24px; padding:0 10px; border:1px solid #b7d0e0; border-radius:6px; background:#fff; color:#1769aa; font-size:11px; font-weight:700; cursor:pointer; }
#newRequestModal .form-hint { display:block; margin-top:3px; color:#718096; font-size:11px; }
#newRequestModal .form-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:10px; padding-top:10px; border-top:1px solid #e7eef3; }
#newRequestModal .cancel-button, #newRequestModal .submit-button { height:38px; min-width:116px; padding:0 18px; border-radius:10px; font:inherit; font-size:13px; font-weight:700; cursor:pointer; transition:all .18s ease; }
#newRequestModal .cancel-button { border:1px solid #cfdde5; background:#f7fafc; color:#245b7c; }
#newRequestModal .cancel-button:hover { background:#eef4f8; border-color:#b9cfdd; }
#newRequestModal .submit-button { border:0; background:linear-gradient(135deg,#2f85c8,#1769aa); color:#fff; box-shadow:0 4px 10px rgba(23,105,170,.22); }
#newRequestModal .submit-button:hover { transform:translateY(-1px); }
#newRequestModal .submit-button:disabled { opacity:.65; cursor:wait; transform:none; }
@media (max-width:650px) {
  #newRequestModal .new-report-card { max-height:94vh; overflow-y:auto; }
  #newRequestModal .form-row { grid-template-columns:1fr; }
}

/* Photo preview and loading overlay (same as the Regular User Report page) */
.image-modal { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.78); }
.image-modal[hidden], .loading-overlay[hidden] { display:none !important; }
.image-modal-content { position:relative; max-width:min(900px,95vw); max-height:90vh; }
.image-modal-content img { display:block; max-width:100%; max-height:85vh; border-radius:10px; background:#fff; }
.close-image-modal { position:absolute; top:-14px; right:-14px; width:36px; height:36px; border:0; border-radius:50%; background:#fff; color:#1f2937; font-size:25px; cursor:pointer; }
.loading-overlay { position:fixed; inset:0; z-index:99999; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.35); -webkit-backdrop-filter:blur(8px); backdrop-filter:blur(8px); }
.loading-overlay img { width:220px; height:220px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const detailsModal = document.getElementById('requestDetailsModal');
    const detailFields = {
        issue: document.getElementById('detailIssue'), status: document.getElementById('detailStatus'),
        priority: document.getElementById('detailPriority'), category: document.getElementById('detailCategory'),
        equipment: document.getElementById('detailEquipment'), location: document.getElementById('detailLocation'),
        requester: document.getElementById('detailRequester'), assignedTo: document.getElementById('detailAssignedTo'),
        date: document.getElementById('detailDate'), description: document.getElementById('detailDescription')
    };
    const editModal = document.getElementById('editRequestModal');
    let activeRequest = null;
    function closeDetailsModal() { detailsModal.style.display = 'none'; }
    function closeEditModal() { editModal.style.display = 'none'; }
    const editFacility = document.getElementById('editFacility');
    const editAssignedTo = document.getElementById('editAssignedTo');
    const editCategory = document.getElementById('editCategory');
    function normalizeSpecialization(value) {
        return String(value || '').toLowerCase().replace(/[^a-z]/g, '');
    }
    function filterAssignedPersonnel(preserveCurrent = true) {
        if (!editAssignedTo || !editCategory) return;
        const category = normalizeSpecialization(editCategory.value);
        const currentValue = editAssignedTo.value;
        Array.from(editAssignedTo.options).forEach(option => {
            if (!option.value) { option.hidden = false; return; }
            let spec = normalizeSpecialization(option.dataset.specialization);
            const type = normalizeSpecialization(option.dataset.specializationType);
            if (!spec) {
                if (['computertechnician','networktechnician','electrician','aircontechnician'].includes(type)) spec = 'electrical';
                else if (['plumber','pipefitter','carpenter','mason','painter','generalmaintenancetechnician'].includes(type)) spec = 'nonelectrical';
            }
            const isCurrent = preserveCurrent && option.value === currentValue;
            // Treat Electrical and Non-Electrical as distinct categories; allow Equipment requests to use either category.
            let matches = false;
            if (category === 'electrical') matches = spec === 'electrical';
            else if (category === 'nonelectrical') matches = spec === 'nonelectrical';
            else if (category === 'equipment') matches = spec === 'electrical' || spec === 'nonelectrical';
            else matches = spec === category;
            option.hidden = !matches && !isCurrent;
        });
        if (currentValue && editAssignedTo.selectedOptions[0] && editAssignedTo.selectedOptions[0].hidden) {
            editAssignedTo.value = '';
        }
        const hint = document.getElementById('editAssignedHint');
        if (hint) hint.textContent = category === 'electrical'
            ? 'Showing Electrical maintenance personnel only.'
            : category === 'nonelectrical'
                ? 'Showing Non-Electrical maintenance personnel only.'
                : category === 'equipment'
                    ? 'Showing personnel from Electrical and Non-Electrical categories.'
                    : 'Personnel are filtered according to the request category.';
    }
    editCategory.addEventListener('change', function () {
        filterAssignedPersonnel(false);
        if (editAssignedTo) editAssignedTo.value = '';
    });

    const editFloor = document.getElementById('editFloor');
    const editRoom = document.getElementById('editRoom');
    const editEquipment = document.getElementById('editEquipment');
    function filterEditLocations(resetChildren) {
        const facility = editFacility.value, floor = editFloor.value, room = editRoom.value;
        Array.from(editFloor.options).forEach(o => { if (o.value) o.hidden = !!facility && o.dataset.facility !== facility; });
        if (editFloor.selectedOptions[0] && editFloor.selectedOptions[0].hidden) editFloor.value = '';
        const selectedFloor = editFloor.value;
        Array.from(editRoom.options).forEach(o => { if (o.value) o.hidden = !!selectedFloor && o.dataset.floor !== selectedFloor; });
        if (editRoom.selectedOptions[0] && editRoom.selectedOptions[0].hidden) editRoom.value = '';
        const selectedRoom = editRoom.value;
        Array.from(editEquipment.options).forEach(o => { if (o.value) o.hidden = (!!facility && o.dataset.facility !== facility) || (!!selectedFloor && o.dataset.floor !== selectedFloor) || (!!selectedRoom && o.dataset.room !== selectedRoom); });
        if (editEquipment.selectedOptions[0] && editEquipment.selectedOptions[0].hidden) editEquipment.value = '';
        if (resetChildren) { editFloor.value = ''; editRoom.value = ''; editEquipment.value = ''; }
    }
    editFacility.addEventListener('change', () => filterEditLocations(true));
    editFloor.addEventListener('change', () => { editRoom.value = ''; editEquipment.value = ''; filterEditLocations(false); });
    editRoom.addEventListener('change', () => { editEquipment.value = ''; filterEditLocations(false); });
    document.getElementById('requestDetailsEdit').addEventListener('click', function () {
        if (!activeRequest) return;
        document.getElementById('editRequestId').value = activeRequest.requestId || '';
        document.getElementById('editRequestSubtitle').textContent = activeRequest.id || 'Update request information';
        document.getElementById('editConcern').value = activeRequest.issue || '';
        document.getElementById('editDescription').value = activeRequest.description || '';
        document.getElementById('editDateReported').value = activeRequest.dateRaw || '';
        const valueMap = {editCategory:activeRequest.category, editPriority:activeRequest.priority, editStatus:activeRequest.status, editFacility:activeRequest.facilityId, editFloor:activeRequest.floorId, editRoom:activeRequest.roomId, editEquipment:activeRequest.equipmentId, editReportedBy:activeRequest.reportedById};
        Object.keys(valueMap).forEach(function (id) {
            const el = document.getElementById(id), value = valueMap[id];
            if (el && value && Array.from(el.options).some(option => option.value === value)) el.value = value;
        });
        const assignedSelect = document.getElementById('editAssignedTo');
        const assignedId = String(activeRequest.assignedToId || '');
        if (assignedSelect) {
            if (assignedId && !Array.from(assignedSelect.options).some(option => option.value === assignedId)) {
                // Preserve an existing assignment even if the user's role label does not exactly match the dropdown filter.
                const currentOption = document.createElement('option');
                currentOption.value = assignedId;
                currentOption.textContent = activeRequest.assignedTo && activeRequest.assignedTo !== 'Unassigned' ? activeRequest.assignedTo : 'Currently assigned personnel';
                currentOption.dataset.currentAssignment = 'true';
                assignedSelect.appendChild(currentOption);
            }
            assignedSelect.value = assignedId;
            if (assignedId && assignedSelect.value !== assignedId) {
                console.warn('Existing assigned_to ID is not present in the personnel dropdown. Check whether maintenance_requests.assigned_to matches users.user_id.');
            }
        }
        filterAssignedPersonnel(true);
        filterEditLocations(false);
        closeDetailsModal(); editModal.style.display = 'flex';
    });
    document.getElementById('requestDetailsDelete').addEventListener('click', function () {
        if (!activeRequest || !activeRequest.requestId) { alert('Missing request ID.'); return; }
        if (confirm('Delete maintenance request ' + (activeRequest.id || '') + '? This action cannot be undone.')) {
            document.getElementById('deleteRequestId').value = activeRequest.requestId;
            document.getElementById('deleteRequestForm').submit();
        }
    });
    document.getElementById('editRequestClose').addEventListener('click', closeEditModal);
    document.getElementById('editRequestCancel').addEventListener('click', closeEditModal);
    editModal.addEventListener('click', function (event) { if (event.target === editModal) closeEditModal(); });
    document.querySelectorAll('#requestTableBody tr[data-request]').forEach(function (row) {
        row.addEventListener('click', function () {
            let data;
            try { data = JSON.parse(this.dataset.request || '{}'); } catch (e) { return; }
            activeRequest = data;
            document.getElementById('requestDetailsId').textContent = data.id || 'Maintenance request information';
            Object.keys(detailFields).forEach(function (key) { detailFields[key].textContent = data[key] || '—'; });
            detailsModal.style.display = 'flex';
        });
    });
    document.getElementById('requestDetailsClose').addEventListener('click', closeDetailsModal);
    document.getElementById('requestDetailsDone').addEventListener('click', closeDetailsModal);
    detailsModal.addEventListener('click', function (event) { if (event.target === detailsModal) closeDetailsModal(); });

    const searchInput = document.getElementById('requestSearch');
    const statusFilter = document.getElementById('requestStatusFilter');
    const categoryFilter = document.getElementById('requestCategoryFilter');
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
        filteredRows = allRows.filter(function (row) {
            const text = (row.dataset.search || row.textContent).toLowerCase();
            return text.includes(query)
                && (!status || row.dataset.status === status)
                && (!category || row.dataset.category === category);
        });
        currentPage = 1;
        renderRows();
        renderPagination();
    }

    searchInput.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    categoryFilter.addEventListener('change', applyFilters);
    resetBtn.addEventListener('click', function () {
        searchInput.value = '';
        statusFilter.value = '';
        categoryFilter.value = '';
        applyFilters();
    });

    // ---- New Request: same locks, reset buttons, photo handling, duplicate check and submit flow as the Regular User Report page ----
    const newRequestBtn = document.getElementById('newRequestBtn');
    const newRequestModal = document.getElementById('newRequestModal');
    const requestResultModal = document.getElementById('requestResultModal');
    const imageModal = document.getElementById('imageModal');
    const imageModalPreview = document.getElementById('imageModalPreview');
    const closeRequestResult = function () { requestResultModal.style.display = 'none'; };
    document.getElementById('requestResultOk').addEventListener('click', closeRequestResult);
    requestResultModal.addEventListener('click', function (event) { if (event.target === requestResultModal) closeRequestResult(); });
    function showNotice(kind, title, message) {
        const icon = document.getElementById('requestResultIcon');
        document.getElementById('requestResultTitle').textContent = title;
        document.getElementById('requestResultMessage').textContent = message;
        icon.textContent = kind === 'success' ? '✓' : '!';
        icon.style.background = kind === 'success' ? '#dcfce7' : '#fee2e2';
        icon.style.color = kind === 'success' ? '#15803d' : '#b91c1c';
        requestResultModal.style.display = 'flex';
    }
    const closeNewRequest = function () { newRequestModal.style.display = 'none'; };
    const closeImageModal = function () { imageModal.hidden = true; };
    newRequestBtn.addEventListener('click', function () { newRequestModal.style.display = 'flex'; });
    document.getElementById('cancelButton').addEventListener('click', closeNewRequest);
    document.getElementById('newRequestClose').addEventListener('click', closeNewRequest);
    newRequestModal.addEventListener('click', function (event) { if (event.target === newRequestModal) closeNewRequest(); });
    imageModal.addEventListener('click', function (event) { if (event.target === imageModal) closeImageModal(); });
    document.getElementById('closeImageModal').addEventListener('click', closeImageModal);
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (!imageModal.hidden) { closeImageModal(); return; }
        closeNewRequest(); closeDetailsModal(); closeEditModal(); closeRequestResult();
    });

    const requestFloors = <?=json_encode($floorsResult['data']??[], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const requestRooms = <?=json_encode($roomsResult['data']??[], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const requestEquipment = <?=json_encode($equipmentResult['data']??[], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const oldFloor = <?=json_encode((string)($_POST['floor_id']??''), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const oldRoom = <?=json_encode((string)($_POST['room_id']??''), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const oldEquipment = <?=json_encode((string)($_POST['equipment_id']??''), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;

    const nrCategory = document.getElementById('equipment_category');
    const nrFacility = document.getElementById('facility_id');
    const nrFloor = document.getElementById('floor_id');
    const nrRoom = document.getElementById('room_id');
    const nrEquipment = document.getElementById('equipment_id');

    // Dependent dropdowns stay locked until every field they depend on has a value.
    // "needs" is listed top-to-bottom, so a click on a locked dropdown goes to the first missing field.
    const lockDefs = [
        { select: nrFloor,     needs: [nrFacility] },
        { select: nrRoom,      needs: [nrFacility, nrFloor] },
        { select: nrEquipment, needs: [nrCategory, nrFacility, nrFloor, nrRoom] }
    ];
    lockDefs.forEach(d => {
        d.group = d.select.closest('.form-group');
        d.wrap  = d.select.closest('.select-wrap');
        d.guard = d.wrap.querySelector('.select-guard');
        d.label = d.group.querySelector('label');
    });
    function nrFirstMissing(d) { return d.needs.find(el => !el.value) || null; }
    function nrSyncLocks() {
        lockDefs.forEach(d => {
            const locked = !!nrFirstMissing(d);
            d.select.disabled = locked;
            d.group.classList.toggle('is-locked', locked);
            d.wrap.classList.toggle('locked', locked);
        });
    }
    function nrFlagField(el) {
        el.style.setProperty('border-color', '#d9534f', 'important');
        el.style.setProperty('box-shadow', '0 0 0 3px rgba(217,83,79,.18)', 'important');
        el.addEventListener('change', function clearFlag() {
            el.style.removeProperty('border-color');
            el.style.removeProperty('box-shadow');
            el.removeEventListener('change', clearFlag);
        });
    }
    function nrRedirectTo(el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.focus({ preventScroll: true });
        nrFlagField(el);
    }
    lockDefs.forEach(d => {
        const onLockedClick = function (e) {
            const missing = nrFirstMissing(d);
            if (!missing) return;
            e.preventDefault();
            nrRedirectTo(missing);
        };
        d.guard.addEventListener('click', onLockedClick);
        d.label.addEventListener('click', onLockedClick);
    });

    // The placeholder ("Select ...") is shown as the field's label only; it is hidden so it never appears in the list of choices.
    function nrFillSelect(select, placeholder, items, key, label, selected = '') {
        select.innerHTML = '';
        const first = document.createElement('option');
        first.value = ''; first.textContent = placeholder;
        first.disabled = true; first.hidden = true; first.style.display = 'none'; first.selected = true;
        select.appendChild(first);
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[key]; opt.textContent = label(item);
            if (String(item[key]) === String(selected)) opt.selected = true;
            select.appendChild(opt);
        });
    }
    function nrUpdateEquipment(selected = '') {
        const matches = requestEquipment.filter(x =>
            String(x.facility_id) === String(nrFacility.value) &&
            String(x.floor_id) === String(nrFloor.value) &&
            String(x.room_id) === String(nrRoom.value) &&
            String(x.category || '').toLowerCase() === String(nrCategory.value).toLowerCase() &&
            String(x.status || '').toLowerCase() === 'active'
        );
        nrFillSelect(nrEquipment, 'Select equipment (optional)', matches, 'equipment_id',
            x => (x.display_id ? x.display_id + ' — ' : '') + (x.name || x.type || 'Equipment') + (x.type ? ' (' + x.type + ')' : ''),
            selected
        );
        // Equipment is optional: add a choice that leaves it blank (also lets the user clear a previous pick).
        const none = document.createElement('option');
        none.value = ''; none.textContent = 'No specific equipment';
        nrEquipment.insertBefore(none, nrEquipment.options[1] || null);
    }
    function nrUpdateRooms(selected = '') {
        const matches = requestRooms.filter(x => String(x.floor_id) === String(nrFloor.value));
        nrFillSelect(nrRoom, 'Select room', matches, 'room_id',
            x => (x.name || x.code || 'Room') + (x.code ? ' (' + x.code + ')' : ''), selected
        );
        nrUpdateEquipment();
    }
    function nrUpdateFloors(selected = '') {
        const matches = requestFloors.filter(x => String(x.facility_id) === String(nrFacility.value));
        nrFillSelect(nrFloor, 'Select floor', matches, 'floor_id',
            x => (x.name || x.code || 'Floor') + (x.code ? ' (' + x.code + ')' : ''), selected
        );
        nrUpdateRooms();
    }

    nrFacility.addEventListener('change', () => nrUpdateFloors());
    nrFloor.addEventListener('change', () => nrUpdateRooms());
    nrRoom.addEventListener('change', () => nrUpdateEquipment());
    nrCategory.addEventListener('change', () => nrUpdateEquipment());
    // Registered after the handlers above, so locks refresh once the lists have been refilled.
    [nrFacility, nrFloor, nrRoom, nrCategory].forEach(el => el.addEventListener('change', nrSyncLocks));

    // Reset buttons: clear only their own section and refresh anything that depends on it.
    function nrClearFlag(el) {
        el.style.removeProperty('border-color');
        el.style.removeProperty('box-shadow');
    }
    document.getElementById('resetDetailsBtn').addEventListener('click', function () {
        const concernEl = document.getElementById('concern');
        concernEl.value = '';
        nrClearFlag(concernEl);
        nrCategory.selectedIndex = 0;
        nrClearFlag(nrCategory);
        nrCategory.dispatchEvent(new Event('change'));
    });
    document.getElementById('resetFacilityBtn').addEventListener('click', function () {
        nrFacility.selectedIndex = 0;
        nrClearFlag(nrFacility);
        // Dispatching change clears the floor, room and equipment lists and re-locks them.
        nrFacility.dispatchEvent(new Event('change'));
    });

    nrUpdateFloors(oldFloor);
    if (oldFloor) nrUpdateRooms(oldRoom);
    if (oldRoom) nrUpdateEquipment(oldEquipment);
    nrSyncLocks();

    // Photo: same checks as the Regular User Report page; the View button opens a preview.
    const nrPhoto = document.getElementById('photo');
    const nrSelectedFile = document.getElementById('selectedFile');
    const nrFileName = document.getElementById('fileName');
    const nrUploadText = document.getElementById('uploadText');
    let nrPreviewUrl = null;
    function nrClearPhoto() {
        nrPhoto.value = '';
        if (nrPreviewUrl) { URL.revokeObjectURL(nrPreviewUrl); nrPreviewUrl = null; }
        nrSelectedFile.hidden = true;
        nrUploadText.style.display = '';
    }
    nrPhoto.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            showNotice('error', 'Unsupported photo', 'Please upload a JPG, PNG, or WEBP image.');
            nrClearPhoto(); return;
        }
        if (file.size > 5 * 1024 * 1024) {
            showNotice('error', 'Photo too large', 'Photo must not exceed 5 MB.');
            nrClearPhoto(); return;
        }
        if (nrPreviewUrl) URL.revokeObjectURL(nrPreviewUrl);
        nrPreviewUrl = URL.createObjectURL(file);
        nrFileName.textContent = file.name;
        nrSelectedFile.hidden = false;
        nrUploadText.style.display = 'none';
    });
    document.getElementById('viewImageBtn').addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        if (nrPreviewUrl) { imageModalPreview.src = nrPreviewUrl; imageModal.hidden = false; }
    });

    // Reports are written in ALL CAPS so the same concern is always typed the same way.
    ['concern', 'description'].forEach(function (id) {
        const el = document.getElementById(id); if (!el) return;
        el.addEventListener('input', function () {
            const a = el.selectionStart, b = el.selectionEnd;
            el.value = el.value.toUpperCase();
            try { el.setSelectionRange(a, b); } catch (_) {}
        });
        el.value = el.value.toUpperCase();
    });

    // Submit: required-field check, duplicate check (photo stays selected), then send.
    document.getElementById('adminRequestForm').addEventListener('submit', function (e) {
        let valid = true; let firstInvalid = null;
        this.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) { field.style.borderColor = '#d9534f'; valid = false; if (!firstInvalid) firstInvalid = field; }
            else field.style.borderColor = '';
        });
        if (!valid) {
            e.preventDefault();
            showNotice('error', 'Missing required fields', 'Please fill in all required fields before submitting the maintenance request.');
            if (firstInvalid) firstInvalid.focus({ preventScroll: true });
            return;
        }
        if (this.dataset.dupChecked === '1') return; // already checked, let the form send
        e.preventDefault();
        const form = this, button = document.getElementById('submitButton');
        const dupModal = document.getElementById('duplicateRequestModal');
        button.disabled = true; button.textContent = 'Checking...';
        const fd = new FormData();
        ['concern', 'room_id', 'equipment_id'].forEach(n => fd.append(n, form.elements[n] ? form.elements[n].value : ''));
        function sendForm() {
            form.dataset.dupChecked = '1';
            button.disabled = true; button.textContent = 'Submitting...';
            document.getElementById('loadingOverlay').hidden = false;
            form.submit();
        }
        fetch(window.location.pathname + '?check_duplicate=1', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json()).catch(() => ({ duplicate: false, check_failed: true }))
            .then(res => {
                button.disabled = false; button.textContent = 'Submit Request';
                if (res && res.duplicate) {
                    document.getElementById('duplicateRequestTitle').textContent = res.handled ? 'Someone is already looking at this concern' : 'This concern is already reported';
                    document.getElementById('duplicateRequestText').textContent = '"' + res.concern + '" is already open as ' + res.label + '. ' + res.progress + ' Please check the request list before sending another report. If your problem is different, you can still submit it.';
                    dupModal.style.display = 'flex';
                } else sendForm();
            });
        document.getElementById('duplicateRequestProceed').onclick = function () {
            dupModal.style.display = 'none';
            document.getElementById('confirmDuplicate').value = '1';
            sendForm();
        };
    });
    document.getElementById('duplicateRequestCancel').addEventListener('click', function () { document.getElementById('duplicateRequestModal').style.display = 'none'; });

    // If the browser restores this page from the back/forward cache, clear the loading state.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.getElementById('loadingOverlay').hidden = true;
            const b = document.getElementById('submitButton'); b.disabled = false; b.textContent = 'Submit Request';
            document.getElementById('adminRequestForm').dataset.dupChecked = '';
            document.getElementById('confirmDuplicate').value = '0';
        }
    });

    <?php if ($formError): ?>newRequestModal.style.display = 'flex';<?php endif; ?>
    <?php if ($formSuccess !== ''): ?>
    const resultMessage = <?=json_encode($formSuccess, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
    const resultIsError = /unable|failed|error|invalid|please complete/i.test(resultMessage);
    document.getElementById('requestResultMessage').textContent = resultMessage;
    document.getElementById('requestResultTitle').textContent = resultIsError ? 'Unable to Save Request' : 'Request Saved Successfully';
    document.getElementById('requestResultIcon').textContent = resultIsError ? '!' : '✓';
    if (resultIsError) { document.getElementById('requestResultIcon').style.background = '#fee2e2'; document.getElementById('requestResultIcon').style.color = '#b91c1c'; }
    requestResultModal.style.display = 'flex';
    <?php endif; ?>

    renderRows();
    renderPagination();
});
</script>

<?php renderFoot(); ?>