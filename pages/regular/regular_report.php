<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/PriorityScore.php';
require_once __DIR__ . '/../../includes/DuplicateReport.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function maintenanceRequest(string $method, string $endpoint, ?array $payload = null, array $extraHeaders = []): array {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
        return ['success'=>false,'status'=>0,'data'=>null,'error'=>'Supabase configuration or PHP cURL is missing.'];
    }
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
    $headers = ['apikey: '.SUPABASE_SECRET_KEY, 'Authorization: Bearer '.SUPABASE_SECRET_KEY, 'Content-Type: application/json', 'Accept: application/json'];
    foreach ($extraHeaders as $header) $headers[] = $header;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>strtoupper($method), CURLOPT_HTTPHEADER=>$headers, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>45]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch); $curlError = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body === false || $curlError !== '') return ['success'=>false,'status'=>$status,'data'=>null,'error'=>$curlError ?: 'Request failed.'];
    $decoded = json_decode($body, true);
    return ['success'=>$status>=200 && $status<300,'status'=>$status,'data'=>$decoded,'error'=>($status<200 || $status>=300) ? (is_array($decoded) ? ($decoded['message']??$decoded['hint']??$decoded['details']??$body) : $body) : null];
}
function mrEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function mrGet(string $endpoint): array { $r=maintenanceRequest('GET',$endpoint); return ($r['success'] && is_array($r['data'])) ? $r['data'] : []; }
function mrUpload(string $path, string $tmp, string $mime): array {
    $url=rtrim(SUPABASE_URL,'/').'/storage/v1/object/report-images/'.implode('/',array_map('rawurlencode',explode('/',$path)));
    $bytes=file_get_contents($tmp); if ($bytes===false) return ['success'=>false,'error'=>'Unable to read uploaded image.'];
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_POSTFIELDS=>$bytes,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SECRET_KEY,'Authorization: Bearer '.SUPABASE_SECRET_KEY,'Content-Type: '.$mime,'x-upsert: false'],CURLOPT_TIMEOUT=>60]);
    $body=curl_exec($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body===false || $err!=='') return ['success'=>false,'error'=>$err ?: 'Storage upload failed.'];
    if ($status<200 || $status>=300) { $j=json_decode($body,true); return ['success'=>false,'error'=>is_array($j)?($j['message']??$j['error']??$body):$body]; }
    return ['success'=>true];
}
// Removes an uploaded image from storage (used when the database insert fails after the photo was already uploaded).
function mrDeleteUpload(string $path): bool {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY')) return false;
    $url=rtrim(SUPABASE_URL,'/').'/storage/v1/object/report-images/'.implode('/',array_map('rawurlencode',explode('/',$path)));
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'DELETE',CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SECRET_KEY,'Authorization: Bearer '.SUPABASE_SECRET_KEY],CURLOPT_TIMEOUT=>30]);
    curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return $status>=200 && $status<300;
}

$error=''; $success=$_SESSION['maintenance_request_success']??''; unset($_SESSION['maintenance_request_success']);
$facilities=mrGet('facilities?select=facility_id,name,code&order=name.asc');
$floors=mrGet('facility_floors?select=floor_id,facility_id,name,code&order=name.asc');
$rooms=mrGet('facility_rooms?select=room_id,floor_id,name,code&order=name.asc');
$equipment=mrGet('equipment?select=equipment_id,display_id,name,type,category,facility_id,floor_id,room_id,status&status=eq.Active&order=name.asc');

/* Pre-check called by the page's JavaScript before the form is sent (keeps the chosen photo). */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['check_duplicate'])) {
    $dup=findDuplicateReport(function(string $e): ?array { $r=maintenanceRequest('GET',$e); return ($r['success'] && is_array($r['data'])) ? $r['data'] : null; },trim((string)($_POST['room_id']??'')),trim((string)($_POST['equipment_id']??'')),(string)($_POST['concern']??''));
    header('Content-Type: application/json');
    echo json_encode($dup ? ['duplicate'=>true,'label'=>duplicateReportLabel($dup),'concern'=>$dup['concern'],'display_id'=>$dup['display_id'],'progress'=>duplicateReportProgress($dup),'handled'=>duplicateReportIsHandled($dup)] : ['duplicate'=>false,'check_failed'=>!empty($GLOBALS['duplicateCheckFailed'])]);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $concern=reportUppercase((string)($_POST['concern']??''));
    $equipmentCategory=trim((string)($_POST['equipment_category']??''));
    $facilityId=trim((string)($_POST['facility_id']??''));
    $floorId=trim((string)($_POST['floor_id']??''));
    $roomId=trim((string)($_POST['room_id']??''));
    $equipmentId=trim((string)($_POST['equipment_id']??''));
    $description=reportUppercase((string)($_POST['description']??''));
    $date=trim((string)($_POST['date_reported']??date('Y-m-d')));

    if ($concern==='' || !in_array($equipmentCategory,['Electrical','Non Electrical'],true) || !$facilityId || !$floorId || !$roomId || $description==='' || $date==='') $error='Please complete all required fields.';

    $currentUsername=(string)($_SESSION['username']??$_SESSION['user']??''); $currentUser=null;
    if ($error==='' && $currentUsername==='') $error='Your session has expired. Please log in again.';
    if ($error==='') {
        $user=maintenanceRequest('GET','users?username=eq.'.rawurlencode($currentUsername).'&select=user_id,username,full_name,role,status&limit=1');
        if (!$user['success'] || empty($user['data'][0])) $error='Unable to identify the logged-in user. Please sign in again.';
        else {
            $currentUser=$user['data'][0];
            if (($currentUser['role']??'')!=='regular') $error='Only Regular Users can submit maintenance requests.';
            elseif (($currentUser['status']??'')!=='Active') $error='Your account is inactive. Contact the administrator.';
        }
    }

    $facility=$floor=$room=$selectedEquipment=null;
    if ($error==='') {
        $r=maintenanceRequest('GET','facilities?select=facility_id,name&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $facility=$r['data'][0]??null;
        $r=maintenanceRequest('GET','facility_floors?select=floor_id,facility_id,name&floor_id=eq.'.rawurlencode($floorId).'&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $floor=$r['data'][0]??null;
        $r=maintenanceRequest('GET','facility_rooms?select=room_id,floor_id,name&room_id=eq.'.rawurlencode($roomId).'&floor_id=eq.'.rawurlencode($floorId).'&limit=1');
        $room=$r['data'][0]??null;
        // Equipment is optional: it is only checked against the Registry when the user picked one.
        if ($equipmentId!=='') {
            $r=maintenanceRequest('GET','equipment?select=equipment_id,display_id,name,type,category,facility_id,floor_id,room_id,status&equipment_id=eq.'.rawurlencode($equipmentId).'&facility_id=eq.'.rawurlencode($facilityId).'&floor_id=eq.'.rawurlencode($floorId).'&room_id=eq.'.rawurlencode($roomId).'&status=eq.Active&limit=1');
            $selectedEquipment=$r['data'][0]??null;
        }
        if (!$facility || !$floor || !$room || ($equipmentId!=='' && !$selectedEquipment)) $error='The selected Facility, Floor, Room, or Equipment does not match the Registry. Please select again.';
        elseif ($equipmentId!=='' && ($selectedEquipment['category']??'')!==$equipmentCategory) $error='The selected equipment does not match the chosen category.';
    }

    $dateTimestamp=null;
    if ($error==='') {
        $dt=DateTime::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d')!==$date) $error='Please enter a valid date.';
        else $dateTimestamp=$dt->format('Y-m-d\T00:00:00+08:00');
    }

    // Same concern already open for this equipment/room? Ask the user to confirm (the page normally asks first via JavaScript).
    if ($error==='' && ($_POST['confirm_duplicate']??'')!=='1') {
        $dup=findDuplicateReport(function(string $e): ?array { $r=maintenanceRequest('GET',$e); return ($r['success'] && is_array($r['data'])) ? $r['data'] : null; },$roomId,$equipmentId,$concern);
        if ($dup) $error='This concern was already reported: '.duplicateReportLabel($dup).'. '.duplicateReportProgress($dup).' If your problem is different, press Submit Request again and choose "Submit anyway".';
    }

    $imagePath=null; $displayId='';
    if ($error==='' && isset($_FILES['photo']) && $_FILES['photo']['error']!==UPLOAD_ERR_NO_FILE) {
        $file=$_FILES['photo'];
        if ($file['error']!==UPLOAD_ERR_OK) $error='The photo could not be uploaded.';
        elseif ($file['size']>5*1024*1024) $error='Photo must not exceed 5 MB.';
        else {
            $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
            $exts=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($exts[$mime])) $error='Photo must be JPG, PNG, or WEBP.';
            else {
                $latest=maintenanceRequest('GET','maintenance_requests?select=display_id&display_id=like.MT-*&order=display_id.desc&limit=1');
                $next=1; $last=$latest['data'][0]['display_id']??'';
                if (preg_match('/MT-(\d+)/',(string)$last,$m)) $next=(int)$m[1]+1;
                $displayId='MT-'.str_pad((string)$next,5,'0',STR_PAD_LEFT);
                $imagePath='maintenance-reports/'.$displayId.'_'.bin2hex(random_bytes(8)).'.'.$exts[$mime];
                $up=mrUpload($imagePath,$file['tmp_name'],$mime);
                if (!$up['success']) { $error='Photo upload failed: '.($up['error']??'Unknown storage error.'); $imagePath=null; }
            }
        }
    }

    if ($error==='') {
        // Preserve the existing request_category enum while storing the user's exact Electrical/Non Electrical selection separately.
        $requestCategory=$equipmentCategory==='Electrical'?'Electrical':'Equipment';
        $saved=null;

        // Priority level comes from the priority score (Components/PriorityScore.php), not a fixed value.
        // If anything goes wrong while scoring, fall back to Medium so the report can still be submitted.
        $autoPriority='Medium';
        try {
            $since=gmdate('Y-m-d\TH:i:s\Z', time() - PRIORITY_RECURRENCE_WINDOW_DAYS * 86400);
            $recentRows=[];
            // Recurrence is counted per equipment, so with no equipment selected there is nothing to compare.
            $recent=$equipmentId!=='' ? maintenanceRequest('GET','maintenance_requests?select=request_id,equipment_id,date_reported&equipment_id=eq.'.rawurlencode($equipmentId).'&date_reported=gte.'.rawurlencode($since)) : ['success'=>false,'data'=>[]];
            foreach (($recent['success'] && is_array($recent['data'])) ? $recent['data'] : [] as $rr) {
                $recentRows[]=['id'=>(string)($rr['request_id']??''),'equipment_id'=>(string)($rr['equipment_id']??''),'date_reported'=>$rr['date_reported']??''];
            }
            $scored=priorityAutoLevel([
                'id'=>'NEW','facility'=>(string)($facility['name']??''),'room'=>(string)($room['name']??''),
                'equipment_id'=>$equipmentId,'date_reported'=>$dateTimestamp,
                'equipment_name'=>(string)($selectedEquipment['name']??''),'equipment_type'=>(string)($selectedEquipment['type']??''),
                'concern'=>$concern
            ],$recentRows);
            $autoPriority=$scored['level'];
        } catch (Throwable $e) { $autoPriority='Medium'; }

        // Try up to 3 times: if two users submit at the same moment and get the same MT- number (HTTP 409 conflict), pick the next number and retry.
        for ($attempt=0; $attempt<3; $attempt++) {
            if ($attempt>0 || $displayId==='') {
                $latest=maintenanceRequest('GET','maintenance_requests?select=display_id&display_id=like.MT-*&order=display_id.desc&limit=1');
                $next=1; $last=$latest['data'][0]['display_id']??''; if (preg_match('/MT-(\d+)/',(string)$last,$m)) $next=(int)$m[1]+1;
                $displayId='MT-'.str_pad((string)$next,5,'0',STR_PAD_LEFT);
            }
            $payload=[
                'display_id'=>$displayId, 'concern'=>$concern, 'description'=>$description,
                'category'=>$requestCategory, 'equipment_category'=>$equipmentCategory,
                'priority'=>$autoPriority, 'status'=>'Pending', 'facility_id'=>$facilityId,
                'floor_id'=>$floorId, 'room_id'=>$roomId, 'specific_area'=>$room['name']??null,
                'equipment_id'=>$equipmentId!==''?$equipmentId:null, 'reported_by'=>$currentUser['user_id'],
                'image_path'=>$imagePath, 'date_reported'=>$dateTimestamp
            ];
            $saved=maintenanceRequest('POST','maintenance_requests',$payload,['Prefer: return=representation']);
            if ($saved['success'] || (int)$saved['status']!==409) break;
        }
        if (!$saved['success']) {
            $error='Unable to submit maintenance request. '.($saved['error']??'Please check the database migration and try again.');
            // The request was not saved, so remove the photo that was already uploaded to storage.
            if ($imagePath) { mrDeleteUpload($imagePath); $imagePath=null; }
        } else {
            $created=$saved['data'][0]??[]; $requestId=$created['request_id']??null;
            if ($requestId) {
                maintenanceRequest('POST','request_timeline',['request_id'=>$requestId,'status'=>'Pending','actor_user_id'=>$currentUser['user_id'],'note'=>'Maintenance request submitted']);
                maintenanceRequest('POST','activity_log',['action_text'=>$displayId.' submitted by '.($currentUser['full_name']??'Regular User'),'related_request_id'=>$requestId,'actor_user_id'=>$currentUser['user_id']]);
            }
            $_SESSION['maintenance_request_success']='Maintenance request '.$displayId.' submitted successfully.';
            header('Location: '.$_SERVER['PHP_SELF']); exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('rolePreferenceAttributes') ? rolePreferenceAttributes('regular') : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaintainTrack | Maintenance Request</title>
    <link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/regular/regular_Report.css')) ?>">
    <style>
        /* Keep the original Report UI and make the upload area clickable. */
        .upload-box { position:relative; min-height:155px; width:100%; border:2px dashed #b7d0e0; border-radius:10px; background:linear-gradient(180deg,#f9fcfe,#eef5fa); overflow:hidden; }
        .upload-box .upload-click-area { box-sizing:border-box; position:relative; z-index:3; width:100%; min-height:155px; max-height:190px; padding:14px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; cursor:pointer; user-select:none; }
        .upload-box .upload-click-area:hover { background:rgba(255,255,255,.45); }
        .upload-box input[type=file] { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
        .upload-icon svg { width:38px; height:38px; fill:none; stroke:currentColor; stroke-width:1.6; }
        .upload-text { display:flex; flex-direction:column; align-items:center; text-align:center; gap:4px; }
        .selected-file { position:absolute; z-index:5; left:14px; right:14px; bottom:12px; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:9px 12px; background:#fff; border:1px solid #d6e2ec; border-radius:8px; box-shadow:0 3px 10px rgba(0,0,0,.08); }
        .selected-file[hidden] { display:none !important; }
        .file-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .view-image-btn { flex:0 0 auto; cursor:pointer; }
        .upload-preview { width:auto !important; height:auto !important; max-width:100% !important; max-height:120px !important; display:none; border-radius:8px; object-fit:contain; flex:0 1 auto; }
        .form-hint { display:block; margin-top:5px; color:#718096; font-size:11px; }
        .inline-message { margin-bottom:20px; padding:14px 18px; border-radius:10px; font-size:13px; font-weight:600; }
        .inline-message.error { background:#fdecec; border:1px solid #efb4b4; color:#a33a3a; }
        .inline-message.success { background:#eaf8ef; border:1px solid #a9d8b8; color:#247a3d; }
        #concern, #description { text-transform:uppercase; }
        .report-feedback-actions { gap:10px; }
        .report-feedback-actions button.secondary { background:#e5e7eb !important; color:#374151; }
        .report-feedback-modal { position:fixed; inset:0; z-index:10010; display:flex; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.58); }
        .report-feedback-modal[hidden] { display:none !important; }
        .report-feedback-card { width:min(430px,100%); background:#fff; border-radius:16px; padding:30px 28px 24px; text-align:center; box-shadow:0 22px 60px rgba(0,0,0,.22); animation:reportModalIn .18s ease-out; }
        .report-feedback-icon { width:58px; height:58px; margin:0 auto 16px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:700; }
        .report-feedback-modal.warning .report-feedback-icon { color:#a86408; background:#fff3d9; }
        .report-feedback-modal.success .report-feedback-icon { color:#237a42; background:#e5f7eb; }
        .report-feedback-modal.error .report-feedback-icon { color:#a83333; background:#fde8e8; }
        .report-feedback-card h3 { margin:0 0 9px; color:#1f2937; font-size:20px; }
        .report-feedback-card p { margin:0; color:#64748b; font-size:14px; line-height:1.6; overflow-wrap:anywhere; }
        .report-feedback-actions { margin-top:24px; display:flex; justify-content:center; }
        .report-feedback-actions button { min-width:105px; border:0; border-radius:8px; padding:11px 22px; background:#1769aa; color:#fff; font-weight:600; cursor:pointer; }
        .report-feedback-modal.warning .report-feedback-actions button { background:#a86408; }
        .report-feedback-modal.success .report-feedback-actions button { background:#237a42; }
        .report-feedback-modal.error .report-feedback-actions button { background:#a83333; }
        @keyframes reportModalIn { from { opacity:0; transform:translateY(7px) scale(.98); } to { opacity:1; transform:translateY(0) scale(1); } }
        .image-modal { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.78); }
        .image-modal[hidden] { display:none !important; }
        .image-modal-content { position:relative; max-width:min(900px,95vw); max-height:90vh; }
        .image-modal-content img { display:block; max-width:100%; max-height:85vh; border-radius:10px; background:#fff; }
        .close-image-modal { position:absolute; top:-14px; right:-14px; width:36px; height:36px; border:0; border-radius:50%; background:#fff; color:#1f2937; font-size:25px; cursor:pointer; }
        .description-group { margin-top: 2px; }
        .photo-group { margin-top: 18px; width: 100%; }
        .photo-group .upload-box, .photo-group .upload-click-area { box-sizing: border-box; width: 100%; }
        .photo-group .upload-preview { max-width: 100%; max-height: 120px; object-fit: contain; }
        @media(max-width:650px) { .form-row { gap:14px; } }
        /* Locked (dependent) dropdowns: greyed out until the field they depend on is chosen. Clicking one sends the user to the missing field. */
        .select-wrap { position:relative; width:100%; }
        .select-guard { display:none; position:absolute; inset:0; z-index:2; }
        .select-wrap.locked .select-guard { display:block; }
        .form-group select:disabled { background-color:#f1f5f9 !important; color:#94a3b8; opacity:1; }
        .form-group.is-locked label { color:#94a3b8; }
        /* Full-screen loading overlay: blurs the whole page while the request is being submitted. */
        .loading-overlay { position:fixed; inset:0; z-index:99999; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.35); -webkit-backdrop-filter:blur(8px); backdrop-filter:blur(8px); }
        .loading-overlay[hidden] { display:none !important; }
        .loading-overlay img { width:220px; height:220px; }
        /* Section titles with a reset button pinned to the far right. */
        .section-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .section-reset-btn { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:30px; height:30px; margin-left:auto; padding:0; border:1px solid #b7d0e0; border-radius:8px; background:#fff; color:#1769aa; cursor:pointer; transition:background .15s, border-color .15s; }
        .section-reset-btn:hover { background:#eef5fa; border-color:#1769aa; }
        .section-reset-btn:focus-visible { outline:2px solid #1769aa; outline-offset:2px; }
        .section-reset-btn svg { width:16px; height:16px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }

        /* Date calendar: replaces the browser's default date field. */
        .date-picker { position:relative; width:100%; }
        .date-picker input[type=text] { width:100%; box-sizing:border-box; padding-right:46px; cursor:pointer; }
        .date-toggle { position:absolute; top:50%; right:8px; transform:translateY(-50%); width:32px; height:32px; display:flex; align-items:center; justify-content:center; padding:0; border:0; border-radius:8px; background:transparent; color:#1769aa; cursor:pointer; transition:background .18s ease; }
        .date-toggle:hover { background:#e4effa; }
        .date-toggle:focus-visible { outline:2px solid #1769aa; outline-offset:1px; }
        .date-toggle svg { width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
        .date-popup { position:absolute; top:calc(100% + 8px); left:0; z-index:50; width:100%; min-width:290px; box-sizing:border-box; padding:14px; background:#fff; border:1px solid #d9e1e8; border-radius:14px; box-shadow:0 16px 40px rgba(15,35,55,.18); animation:datePop .16s ease-out; }
        .date-popup[hidden] { display:none; }
        @keyframes datePop { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }
        .date-popup-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .date-month-label { font-size:15px; font-weight:800; color:#153c66; }
        .date-nav { width:32px; height:32px; padding:0; border:1px solid #d9e1e8; border-radius:8px; background:#fff; color:#334155; font-size:18px; line-height:1; cursor:pointer; transition:background .18s ease, border-color .18s ease, color .18s ease; }
        .date-nav:hover:not(:disabled) { background:#eef5fa; border-color:#9dbfe0; color:#1769aa; }
        .date-nav:disabled { background:#f4f7fa; color:#a3b0bd; border-color:#e6ecf1; cursor:not-allowed; }
        .date-weekdays, .date-days { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
        .date-weekdays span { text-align:center; padding:4px 0 6px; font-size:11px; font-weight:800; color:#71839a; text-transform:uppercase; }
        .date-day { height:36px; padding:0; border:1px solid transparent; border-radius:10px; background:transparent; color:#334155; font:inherit; font-size:13px; font-weight:700; cursor:pointer; transition:background .18s ease, color .18s ease, border-color .18s ease; }
        .date-day:hover:not(:disabled):not(.selected) { background:#e4effa; border-color:#9dbfe0; color:#1769aa; }
        .date-day.today { border-color:#9dbfe0; }
        .date-day.selected { background:linear-gradient(135deg,#1769aa,#1d4f91); color:#fff; box-shadow:0 6px 14px rgba(23,105,170,.3); }
        .date-day:disabled { color:#c3ccd6; cursor:not-allowed; }
        .date-popup-foot { display:flex; justify-content:center; margin-top:12px; padding-top:10px; border-top:1px solid #e6eef4; }
        .date-today-btn { border:0; background:transparent; color:#1769aa; font:inherit; font-size:12px; font-weight:800; cursor:pointer; padding:6px 12px; border-radius:8px; }
        .date-today-btn:hover { background:#e4effa; }
    </style>
</head>
<body>
<div class="loading-overlay" id="loadingOverlay" hidden role="status" aria-live="polite" aria-label="Submitting your request">
    <img src="<?= e(appAssetUrl('assets/images/loading.svg')) ?>" alt="Loading">
</div>
<?php include __DIR__ . '/../../Components/regular_navbar.php'; ?>
<main class="page">

    <?php if ($error !== ''): ?><div class="inline-message error" id="serverErrorFallback"><?=mrEscape($error)?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="inline-message success" id="serverSuccessFallback"><?=mrEscape($success)?></div><?php endif; ?>
    <?php if (!$facilities || !$floors || !$rooms || !$equipment): ?>
        <div class="inline-message error">Some Registry dropdown data could not be loaded. Please check the facilities, facility_floors, facility_rooms, and equipment tables.</div>
    <?php endif; ?>

    <form class="report-card" id="reportForm" method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="confirm_duplicate" id="confirmDuplicate" value="0">
        <?php renderPageHeader('My Report', 'Maintenance Reporting Portal · Barangay Gulod', 'regular'); ?>

        <div class="section-title section-title-row">
            <span>Maintenance Concern Details</span>
            <button type="button" class="section-reset-btn" id="resetDetailsBtn" aria-label="Reset Maintenance Concern Details" title="Reset this section">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><polyline points="3 3 3 9 9 9"></polyline></svg>
            </button>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="concern">Maintenance Concern <span>*</span></label>
                <input type="text" id="concern" name="concern" maxlength="200" required placeholder="e.g. Air conditioner is not cooling" value="<?=mrEscape($_POST['concern']??'')?>">
            </div>
            <div class="form-group">
                <label for="equipment_category">Concern / Equipment Category <span>*</span></label>
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
                <label for="facility_id">Facility <span>*</span></label>
                <select id="facility_id" name="facility_id" required>
                    <option value="" disabled hidden style="display:none" <?=($_POST['facility_id']??'')===''?'selected':''?>>Select facility</option>
                    <?php foreach ($facilities as $f): ?>
                        <option value="<?=mrEscape($f['facility_id'])?>" <?=($_POST['facility_id']??'')===($f['facility_id']??'')?'selected':''?>><?=mrEscape($f['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="floor_id">Floor <span>*</span></label>
                <div class="select-wrap"><select id="floor_id" name="floor_id" required><option value="" disabled selected hidden style="display:none">Select floor</option></select><div class="select-guard"></div></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="room_id">Room <span>*</span></label>
                <div class="select-wrap"><select id="room_id" name="room_id" required><option value="" disabled selected hidden style="display:none">Select room</option></select><div class="select-guard"></div></div>
            </div>
            <div class="form-group">
                <label for="equipment_id">Equipment <span style="font-weight:400;">(optional)</span></label>
                <div class="select-wrap"><select id="equipment_id" name="equipment_id"><option value="" disabled selected hidden style="display:none">Select equipment (optional)</option></select><div class="select-guard"></div></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="date_display">Date <span>*</span></label>
                <div class="date-picker" id="datePicker" data-max="<?=date('Y-m-d')?>">
                    <input type="hidden" id="date_reported" name="date_reported" value="<?=mrEscape($_POST['date_reported']??date('Y-m-d'))?>">
                    <input type="text" id="date_display" required readonly autocomplete="off" placeholder="Select date" aria-haspopup="dialog" aria-expanded="false" aria-controls="datePopup">
                    <button type="button" class="date-toggle" id="dateToggle" aria-label="Open calendar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </button>

                    <div class="date-popup" id="datePopup" role="dialog" aria-label="Choose date" hidden>
                        <div class="date-popup-head">
                            <button type="button" class="date-nav" id="datePrevMonth" aria-label="Previous month">‹</button>
                            <span class="date-month-label" id="dateMonthLabel"></span>
                            <button type="button" class="date-nav" id="dateNextMonth" aria-label="Next month">›</button>
                        </div>
                        <div class="date-weekdays" aria-hidden="true"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div>
                        <div class="date-days" id="dateDays"></div>
                        <div class="date-popup-foot">
                            <button type="button" class="date-today-btn" id="dateTodayBtn">Today</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group" aria-hidden="true"></div>
        </div>
        <div class="form-group description-group">
            <label for="description">Description <span>*</span></label>
            <textarea id="description" name="description" rows="5" required placeholder="Describe the problem in detail..."><?=mrEscape($_POST['description']??'')?></textarea>
        </div>
        <div class="form-group photo-group">
            <label for="photo">Photo <span style="font-weight:400;">(optional)</span></label>
            <div class="upload-box" id="uploadBox">
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                <label for="photo" class="upload-click-area" id="uploadClickArea">
                    <div class="upload-icon" id="uploadIcon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"></rect><circle cx="9" cy="9" r="1.5"></circle><path d="M6.5 18l4.5-5 2.5 3 2-2.5 2 4.5"></path></svg></div>
                    <div class="upload-text" id="uploadText"><strong>Click to upload</strong><span>Attach a photo of the problem (optional)</span></div>
                    <img id="uploadPreview" class="upload-preview" alt="Selected photo preview">
                </label>
                <div class="selected-file" id="selectedFile" hidden><span class="file-name" id="fileName"></span><button type="button" class="view-image-btn" id="viewImageBtn">View</button></div>
            </div>
            <small class="form-hint">JPG, PNG, or WEBP · Maximum 5 MB</small>
        </div>
        <div class="form-actions">
            <button type="button" class="cancel-button" id="cancelButton">Cancel</button>
            <button type="submit" class="submit-button" id="submitButton">Submit Request</button>
        </div>
    </form>
</main>

<div class="image-modal" id="imageModal" hidden>
    <div class="image-modal-content">
        <button type="button" class="close-image-modal" id="closeImageModal" aria-label="Close image preview">&times;</button>
        <img id="imageModalPreview" src="" alt="Selected photo preview">
    </div>
</div>

<div class="report-feedback-modal warning" id="duplicateModal" hidden role="dialog" aria-modal="true" aria-labelledby="duplicateTitle">
    <div class="report-feedback-card">
        <div class="report-feedback-icon">!</div>
        <h3 id="duplicateTitle">This concern may already be reported</h3>
        <p id="duplicateText"></p>
        <div class="report-feedback-actions">
            <button type="button" class="secondary" id="duplicateCancel">Cancel</button>
            <button type="button" id="duplicateProceed">Submit anyway</button>
        </div>
    </div>
</div>

<div class="report-feedback-modal" id="reportFeedbackModal" hidden role="dialog" aria-modal="true" aria-labelledby="reportFeedbackTitle">
    <div class="report-feedback-card">
        <div class="report-feedback-icon" id="reportFeedbackIcon">!</div>
        <h3 id="reportFeedbackTitle">Notice</h3>
        <p id="reportFeedbackText"></p>
        <div class="report-feedback-actions"><button type="button" id="reportFeedbackOk">OK</button></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mrFloors = <?=json_encode($floors,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const mrRooms = <?=json_encode($rooms,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const mrEquipment = <?=json_encode($equipment,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const oldFloor = <?=json_encode((string)($_POST['floor_id']??''))?>;
    const oldRoom = <?=json_encode((string)($_POST['room_id']??''))?>;
    const oldEquipment = <?=json_encode((string)($_POST['equipment_id']??''))?>;

    const category = document.getElementById('equipment_category');
    const facility = document.getElementById('facility_id');
    const floor = document.getElementById('floor_id');
    const room = document.getElementById('room_id');
    const equipment = document.getElementById('equipment_id');

    // Dependent dropdowns stay locked until every field they depend on has a value.
    // "needs" is listed top-to-bottom, so a click on a locked dropdown goes to the first missing field.
    const lockDefs = [
        { select: floor,     needs: [facility] },
        { select: room,      needs: [facility, floor] },
        { select: equipment, needs: [category, facility, floor, room] }
    ];
    lockDefs.forEach(d => {
        d.group = d.select.closest('.form-group');
        d.wrap  = d.select.closest('.select-wrap');
        d.guard = d.wrap.querySelector('.select-guard');
        d.label = d.group.querySelector('label');
    });
    function firstMissing(d) { return d.needs.find(el => !el.value) || null; }
    function syncLocks() {
        lockDefs.forEach(d => {
            const locked = !!firstMissing(d);
            d.select.disabled = locked;
            d.group.classList.toggle('is-locked', locked);
            d.wrap.classList.toggle('locked', locked);
        });
    }
    function flagField(el) {
        el.style.setProperty('border-color', '#d9534f', 'important');
        el.style.setProperty('box-shadow', '0 0 0 3px rgba(217,83,79,.18)', 'important');
        el.addEventListener('change', function clearFlag() {
            el.style.removeProperty('border-color');
            el.style.removeProperty('box-shadow');
            el.removeEventListener('change', clearFlag);
        });
    }
    function redirectTo(el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.focus({ preventScroll: true });
        flagField(el);
    }
    lockDefs.forEach(d => {
        const onLockedClick = function (e) {
            const missing = firstMissing(d);
            if (!missing) return;
            e.preventDefault();
            redirectTo(missing);
        };
        d.guard.addEventListener('click', onLockedClick);
        d.label.addEventListener('click', onLockedClick);
    });

    // The placeholder ("Select ...") is shown as the field's label only; it is disabled and hidden so it never appears in the list of choices.
    function fillSelect(select, placeholder, items, key, label, selected='') {
        select.innerHTML='';
        const first=document.createElement('option');
        first.value=''; first.textContent=placeholder;
        first.disabled=true; first.hidden=true; first.style.display='none'; first.selected=true;
        select.appendChild(first);
        items.forEach(item=>{const opt=document.createElement('option');opt.value=item[key];opt.textContent=label(item);if(String(item[key])===String(selected))opt.selected=true;select.appendChild(opt);});
    }

    function updateEquipment(selected='') {
        const matches = mrEquipment.filter(x =>
            String(x.facility_id) === String(facility.value) &&
            String(x.floor_id) === String(floor.value) &&
            String(x.room_id) === String(room.value) &&
            String(x.category || '').toLowerCase() === String(category.value).toLowerCase() &&
            String(x.status || '').toLowerCase() === 'active'
        );
        fillSelect(equipment, 'Select equipment (optional)', matches, 'equipment_id',
            x => (x.display_id ? x.display_id + ' — ' : '') + x.name + (x.type ? ' (' + x.type + ')' : ''),
            selected
        );
        // Equipment is optional: add a choice that leaves it blank (also lets the user clear a previous pick).
        const none = document.createElement('option');
        none.value = ''; none.textContent = 'No specific equipment';
        equipment.insertBefore(none, equipment.options[1] || null);
    }

    function updateRooms(selected='') {
        const matches = mrRooms.filter(x => String(x.floor_id) === String(floor.value));
        fillSelect(room, 'Select room', matches, 'room_id',
            x => x.name + (x.code ? ' (' + x.code + ')' : ''), selected
        );
        updateEquipment();
    }

    function updateFloors(selected='') {
        const matches = mrFloors.filter(x => String(x.facility_id) === String(facility.value));
        fillSelect(floor, 'Select floor', matches, 'floor_id',
            x => x.name + (x.code ? ' (' + x.code + ')' : ''), selected
        );
        updateRooms();
    }

    facility.addEventListener('change',()=>updateFloors());
    floor.addEventListener('change',()=>updateRooms());
    room.addEventListener('change',()=>updateEquipment());
    category.addEventListener('change',()=>updateEquipment());

    // Registered after the handlers above, so locks refresh once the lists have been refilled.
    [facility, floor, room, category].forEach(el => el.addEventListener('change', syncLocks));

    // Reset buttons: clear only their own section and refresh anything that depends on it.
    function clearFieldFlag(el) {
        el.style.removeProperty('border-color');
        el.style.removeProperty('box-shadow');
        el.style.borderColor = '';
    }
    document.getElementById('resetDetailsBtn').addEventListener('click', function () {
        const concernEl = document.getElementById('concern');
        concernEl.value = '';
        clearFieldFlag(concernEl);
        category.selectedIndex = 0;
        clearFieldFlag(category);
        category.dispatchEvent(new Event('change'));
    });
    document.getElementById('resetFacilityBtn').addEventListener('click', function () {
        facility.selectedIndex = 0;
        clearFieldFlag(facility);
        // Dispatching change clears the floor, room and equipment lists and re-locks them.
        facility.dispatchEvent(new Event('change'));
    });

    updateFloors(oldFloor);
    if(oldFloor){updateRooms(oldRoom);}
    if(oldRoom){updateEquipment(oldEquipment);}
    syncLocks();

    /* ==========================================
       DATE CALENDAR (replaces the browser date field)
       The chosen day is saved in the hidden date_reported field as YYYY-MM-DD.
       Days after today cannot be picked.
       ========================================== */
    (function () {
        const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const picker = document.getElementById('datePicker');
        const hidden = document.getElementById('date_reported');
        const input = document.getElementById('date_display');
        const toggle = document.getElementById('dateToggle');
        const popup = document.getElementById('datePopup');
        const grid = document.getElementById('dateDays');
        const monthLabel = document.getElementById('dateMonthLabel');
        const prevBtn = document.getElementById('datePrevMonth');
        const nextBtn = document.getElementById('dateNextMonth');

        function parseISO(s) {
            const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
            return m ? { y: +m[1], m: +m[2] - 1, d: +m[3] } : null;
        }
        function pad(n) { return String(n).padStart(2, '0'); }
        function toISO(o) { return o.y + '-' + pad(o.m + 1) + '-' + pad(o.d); }
        function toText(o) { return MONTHS[o.m] + ' ' + o.d + ', ' + o.y; }
        function key(o) { return o.y * 10000 + o.m * 100 + o.d; }

        const now = new Date();
        const maxDate = parseISO(picker.dataset.max) || { y: now.getFullYear(), m: now.getMonth(), d: now.getDate() };
        let selected = parseISO(hidden.value);
        if (selected && key(selected) > key(maxDate)) selected = maxDate;

        function setSelected(o) {
            selected = o;
            hidden.value = toISO(o);
            input.value = toText(o);
            clearFieldFlag(input);
        }
        if (selected) setSelected(selected);

        let viewY = (selected || maxDate).y;
        let viewM = (selected || maxDate).m;

        function render() {
            monthLabel.textContent = MONTHS[viewM] + ' ' + viewY;
            grid.innerHTML = '';
            const firstWeekday = new Date(viewY, viewM, 1).getDay();
            const daysInMonth = new Date(viewY, viewM + 1, 0).getDate();
            for (let i = 0; i < firstWeekday; i++) grid.appendChild(document.createElement('span'));
            for (let d = 1; d <= daysInMonth; d++) {
                const cell = { y: viewY, m: viewM, d: d };
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'date-day';
                btn.textContent = d;
                btn.title = toText(cell);
                if (key(cell) > key(maxDate)) btn.disabled = true;
                if (key(cell) === key(maxDate)) btn.classList.add('today');
                if (selected && key(cell) === key(selected)) btn.classList.add('selected');
                btn.addEventListener('click', function () { setSelected(cell); closePopup(); });
                grid.appendChild(btn);
            }
            // No going past the month that holds today.
            nextBtn.disabled = (viewY * 12 + viewM) >= (maxDate.y * 12 + maxDate.m);
        }

        function openPopup() {
            const base = selected || maxDate;
            viewY = base.y; viewM = base.m;
            render();
            popup.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
        function closePopup() {
            popup.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        }
        function togglePopup() { popup.hidden ? openPopup() : closePopup(); }

        input.addEventListener('click', togglePopup);
        toggle.addEventListener('click', togglePopup);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') { e.preventDefault(); openPopup(); }
        });
        prevBtn.addEventListener('click', function () {
            viewM--; if (viewM < 0) { viewM = 11; viewY--; }
            render();
        });
        nextBtn.addEventListener('click', function () {
            if (nextBtn.disabled) return;
            viewM++; if (viewM > 11) { viewM = 0; viewY++; }
            render();
        });
        document.getElementById('dateTodayBtn').addEventListener('click', function () {
            setSelected(maxDate);
            closePopup();
        });
        document.addEventListener('click', function (e) {
            if (!popup.hidden && !picker.contains(e.target)) closePopup();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !popup.hidden) { closePopup(); input.focus(); }
        });
    })();

    const photo=document.getElementById('photo'), selectedFile=document.getElementById('selectedFile'), fileName=document.getElementById('fileName'), uploadPreview=document.getElementById('uploadPreview'), uploadIcon=document.getElementById('uploadIcon'), uploadText=document.getElementById('uploadText');
    const imageModal=document.getElementById('imageModal'), modalPreview=document.getElementById('imageModalPreview');
    let previewUrl=null;
    photo.addEventListener('change',function(){
        const file=this.files&&this.files[0]; if(!file)return;
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)){alert('Please upload a JPG, PNG, or WEBP image.');this.value='';return;}
        if(file.size>5*1024*1024){alert('Photo must not exceed 5 MB.');this.value='';return;}
        if(previewUrl)URL.revokeObjectURL(previewUrl); previewUrl=URL.createObjectURL(file); fileName.textContent=file.name;selectedFile.hidden=false;uploadPreview.src=previewUrl;uploadPreview.style.display='block';uploadIcon.style.display='none';uploadText.style.display='none';
    });
    document.getElementById('viewImageBtn').addEventListener('click',function(e){e.preventDefault();e.stopPropagation();if(previewUrl){modalPreview.src=previewUrl;imageModal.hidden=false;}});
    function closeModal(){imageModal.hidden=true;}
    document.getElementById('closeImageModal').addEventListener('click',closeModal);
    imageModal.addEventListener('click',e=>{if(e.target===imageModal)closeModal();});
    document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
    document.getElementById('cancelButton').addEventListener('click',function(){if(confirm('Cancel this maintenance request and return to the dashboard?'))window.location.href='regular_dashboard.php';});

    const feedbackModal=document.getElementById('reportFeedbackModal');
    const feedbackIcon=document.getElementById('reportFeedbackIcon');
    const feedbackTitle=document.getElementById('reportFeedbackTitle');
    const feedbackText=document.getElementById('reportFeedbackText');
    function showFeedback(kind,title,message){
        feedbackModal.className='report-feedback-modal '+kind;
        feedbackIcon.textContent=kind==='success'?'✓':(kind==='warning'?'!':'×');
        feedbackTitle.textContent=title;
        feedbackText.textContent=message;
        feedbackModal.hidden=false;
        document.getElementById('reportFeedbackOk').focus();
    }
    function hideFeedback(){feedbackModal.hidden=true;}
    document.getElementById('reportFeedbackOk').addEventListener('click',hideFeedback);
    feedbackModal.addEventListener('click',e=>{if(e.target===feedbackModal)hideFeedback();});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!feedbackModal.hidden)hideFeedback();});

    const serverError=<?=json_encode($error,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    const serverSuccess=<?=json_encode($success,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
    if(serverError){
        const fallback=document.getElementById('serverErrorFallback');if(fallback)fallback.style.display='none';
        showFeedback('error','Unable to submit request',serverError);
    } else if(serverSuccess){
        const fallback=document.getElementById('serverSuccessFallback');if(fallback)fallback.style.display='none';
        showFeedback('success','Request submitted successfully',serverSuccess);
    }

    // If the browser restores this page from the back/forward cache, clear the loading state.
    window.addEventListener('pageshow',function(e){
        if(e.persisted){
            document.getElementById('loadingOverlay').hidden=true;
            const b=document.getElementById('submitButton');b.disabled=false;b.textContent='Submit Request';document.getElementById('reportForm').dataset.dupChecked='';document.getElementById('confirmDuplicate').value='0';
        }
    });

    document.getElementById('reportForm').addEventListener('submit',function(e){
        let valid=true;let firstInvalid=null;
        this.querySelectorAll('[required]').forEach(field=>{
            if(!field.value.trim()){field.style.borderColor='#d9534f';valid=false;if(!firstInvalid)firstInvalid=field;}
            else field.style.borderColor='';
        });
        if(!valid){
            e.preventDefault();
            showFeedback('warning','Missing required fields','Please fill in all required fields before submitting your maintenance request.');
            if(firstInvalid)firstInvalid.focus({preventScroll:true});
            return;
        }
        // Check for an existing open report with the same concern before sending (photo stays selected).
        if(this.dataset.dupChecked==='1')return; // already checked, let the form send
        e.preventDefault();
        const form=this, btn=document.getElementById('submitButton');
        btn.disabled=true;btn.textContent='Checking...';
        const fd=new FormData();
        ['concern','room_id','equipment_id'].forEach(n=>fd.append(n,form.elements[n]?form.elements[n].value:''));
        fetch(window.location.pathname+'?check_duplicate=1',{method:'POST',body:fd,credentials:'same-origin'})
            .then(r=>r.json()).catch(()=>({duplicate:false,check_failed:true}))
            .then(res=>{
                btn.disabled=false;btn.textContent='Submit Request';
                if(res&&res.check_failed)console.warn('Duplicate check could not read existing requests.');
                if(res&&res.duplicate){
                    document.getElementById('duplicateTitle').textContent=res.handled?'Someone is already looking at this concern':'This concern is already reported';
                    document.getElementById('duplicateText').textContent='"'+res.concern+'" is already open as '+res.label+'. '+res.progress+' Please check My History before sending another report. If your problem is different, you can still submit it.';
                    document.getElementById('duplicateModal').hidden=false;
                } else sendForm();
            });
        function sendForm(){
            form.dataset.dupChecked='1';
            btn.disabled=true;btn.textContent='Submitting...';
            document.getElementById('loadingOverlay').hidden=false;
            form.submit();
        }
        document.getElementById('duplicateProceed').onclick=function(){document.getElementById('duplicateModal').hidden=true;document.getElementById('confirmDuplicate').value='1';sendForm();};
    });
    document.getElementById('duplicateCancel').addEventListener('click',function(){document.getElementById('duplicateModal').hidden=true;});
    document.getElementById('duplicateModal').addEventListener('click',function(e){if(e.target===this)this.hidden=true;});

    // Reports are written in ALL CAPS so the same concern is always typed the same way.
    ['concern','description'].forEach(function(id){
        const el=document.getElementById(id);if(!el)return;
        el.addEventListener('input',function(){const a=el.selectionStart,b=el.selectionEnd;el.value=el.value.toUpperCase();try{el.setSelectionRange(a,b);}catch(_){}});
        el.value=el.value.toUpperCase();
    });
});
</script>
</body>
</html>