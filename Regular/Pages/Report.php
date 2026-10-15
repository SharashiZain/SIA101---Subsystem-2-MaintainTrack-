<?php
declare(strict_types=1);
require_once '../../Components/layout.php';
require_once '../../Config/supabase.php';
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

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $concern=trim((string)($_POST['concern']??''));
    $equipmentCategory=trim((string)($_POST['equipment_category']??''));
    $facilityId=trim((string)($_POST['facility_id']??''));
    $floorId=trim((string)($_POST['floor_id']??''));
    $roomId=trim((string)($_POST['room_id']??''));
    $equipmentId=trim((string)($_POST['equipment_id']??''));
    $description=trim((string)($_POST['description']??''));
    $date=trim((string)($_POST['date_reported']??date('Y-m-d')));

    if ($concern==='' || !in_array($equipmentCategory,['Electrical','Non Electrical'],true) || !$facilityId || !$floorId || !$roomId || !$equipmentId || $description==='' || $date==='') $error='Please complete all required fields.';

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
        $r=maintenanceRequest('GET','equipment?select=equipment_id,display_id,name,category,facility_id,floor_id,room_id,status&equipment_id=eq.'.rawurlencode($equipmentId).'&facility_id=eq.'.rawurlencode($facilityId).'&floor_id=eq.'.rawurlencode($floorId).'&room_id=eq.'.rawurlencode($roomId).'&status=eq.Active&limit=1');
        $selectedEquipment=$r['data'][0]??null;
        if (!$facility || !$floor || !$room || !$selectedEquipment) $error='The selected Facility, Floor, Room, or Equipment does not match the Registry. Please select again.';
        elseif (($selectedEquipment['category']??'')!==$equipmentCategory) $error='The selected equipment does not match the chosen category.';
    }

    $dateTimestamp=null;
    if ($error==='') {
        $dt=DateTime::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d')!==$date) $error='Please enter a valid date.';
        else $dateTimestamp=$dt->format('Y-m-d\T00:00:00+08:00');
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
                'priority'=>'Medium', 'status'=>'Pending', 'facility_id'=>$facilityId,
                'floor_id'=>$floorId, 'room_id'=>$roomId, 'specific_area'=>$room['name']??null,
                'equipment_id'=>$equipmentId, 'reported_by'=>$currentUser['user_id'],
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
    <link rel="stylesheet" href="../../Components/css/base.css">
    <link rel="stylesheet" href="../Components/css/Report.css">
    <style>
        /* Keep the original Report UI and make the upload area clickable. */
        .upload-box { position:relative; min-height:155px; width:100%; border:2px dashed #b7d0e0; border-radius:10px; background:linear-gradient(180deg,#f9fcfe,#eef5fa); overflow:hidden; }
        .upload-box .upload-click-area { position:relative; z-index:3; width:100%; min-height:155px; padding:22px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; cursor:pointer; user-select:none; }
        .upload-box .upload-click-area:hover { background:rgba(255,255,255,.45); }
        .upload-box input[type=file] { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
        .upload-icon svg { width:38px; height:38px; fill:none; stroke:currentColor; stroke-width:1.6; }
        .upload-text { display:flex; flex-direction:column; align-items:center; text-align:center; gap:4px; }
        .selected-file { position:absolute; z-index:5; left:14px; right:14px; bottom:12px; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:9px 12px; background:#fff; border:1px solid #d6e2ec; border-radius:8px; box-shadow:0 3px 10px rgba(0,0,0,.08); }
        .selected-file[hidden] { display:none !important; }
        .file-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .view-image-btn { flex:0 0 auto; cursor:pointer; }
        .upload-preview { max-width:100%; max-height:150px; display:none; border-radius:8px; object-fit:contain; }
        .form-hint { display:block; margin-top:5px; color:#718096; font-size:11px; }
        .inline-message { margin-bottom:20px; padding:14px 18px; border-radius:10px; font-size:13px; font-weight:600; }
        .inline-message.error { background:#fdecec; border:1px solid #efb4b4; color:#a33a3a; }
        .inline-message.success { background:#eaf8ef; border:1px solid #a9d8b8; color:#247a3d; }
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
        @media(max-width:650px) { .form-row { gap:14px; } }
    </style>
</head>
<body>
<?php include '../Components/NavBar.php'; ?>
<main class="page">

    <?php if ($error !== ''): ?><div class="inline-message error" id="serverErrorFallback"><?=mrEscape($error)?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="inline-message success" id="serverSuccessFallback"><?=mrEscape($success)?></div><?php endif; ?>
    <?php if (!$facilities || !$floors || !$rooms || !$equipment): ?>
        <div class="inline-message error">Some Registry dropdown data could not be loaded. Please check the facilities, facility_floors, facility_rooms, and equipment tables.</div>
    <?php endif; ?>

    <form class="report-card" id="reportForm" method="POST" enctype="multipart/form-data" novalidate>
        <?php renderPageHeader('My Report', 'Maintenance Reporting Portal · Barangay Gulod', 'regular'); ?>
  
        <div class="section-title">Maintenance Concern Details</div>
        <div class="form-row">
            <div class="form-group">
                <label for="concern">Maintenance Concern <span>*</span></label>
                <input type="text" id="concern" name="concern" maxlength="200" required placeholder="e.g. Air conditioner is not cooling" value="<?=mrEscape($_POST['concern']??'')?>">
            </div>
            <div class="form-group">
                <label for="equipment_category">Concern / Equipment Category <span>*</span></label>
                <select id="equipment_category" name="equipment_category" required>
                    <option value="">Select category</option>
                    <option value="Electrical" <?=($_POST['equipment_category']??'')==='Electrical'?'selected':''?>>Electrical</option>
                    <option value="Non Electrical" <?=($_POST['equipment_category']??'')==='Non Electrical'?'selected':''?>>Non Electrical</option>
                </select>
            </div>
        </div>

        <div class="section-title">Facility & Equipment Reference</div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="facility_id">Facility <span>*</span></label>
                <select id="facility_id" name="facility_id" required>
                    <option value="">Select facility</option>
                    <?php foreach ($facilities as $f): ?>
                        <option value="<?=mrEscape($f['facility_id'])?>" <?=($_POST['facility_id']??'')===($f['facility_id']??'')?'selected':''?>><?=mrEscape($f['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="floor_id">Floor <span>*</span></label>
                <select id="floor_id" name="floor_id" required><option value="">Select floor</option></select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="room_id">Room <span>*</span></label>
                <select id="room_id" name="room_id" required><option value="">Select room</option></select>
            </div>
            <div class="form-group">
                <label for="equipment_id">Equipment <span>*</span></label>
                <select id="equipment_id" name="equipment_id" required><option value="">Select equipment</option></select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="date_reported">Date <span>*</span></label>
                <input type="date" id="date_reported" name="date_reported" required max="<?=date('Y-m-d')?>" value="<?=mrEscape($_POST['date_reported']??date('Y-m-d'))?>">
            </div>
            <div class="form-group">
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
        </div>
        <div class="form-group">
            <label for="description">Description <span>*</span></label>
            <textarea id="description" name="description" rows="6" required placeholder="Describe the problem in detail..."><?=mrEscape($_POST['description']??'')?></textarea>
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
    function fillSelect(select, placeholder, items, key, label, selected='') {
        select.innerHTML=''; const first=document.createElement('option'); first.value=''; first.textContent=placeholder; select.appendChild(first);
        items.forEach(item=>{const opt=document.createElement('option');opt.value=item[key];opt.textContent=label(item);if(String(item[key])===String(selected))opt.selected=true;select.appendChild(opt);});
    }
    function updateEquipment(selected='') {
        const matches=mrEquipment.filter(x=>x.facility_id===facility.value && x.floor_id===floor.value && x.room_id===room.value && x.category===category.value && x.status==='Active').concat(tempEquipment);
        fillSelect(equipment,'Select equipment',matches,'equipment_id',x=>(x.display_id?x.display_id+' — ':'')+x.name+' ('+x.type+')',selected);
    }
    function updateRooms(selected='') {
        const matches=mrRooms.filter(x=>x.floor_id===floor.value).concat(tempRooms);
        fillSelect(room,'Select room',matches,'room_id',x=>x.name+(x.code?' ('+x.code+')':''),selected);
        updateEquipment();
    }
    function updateFloors(selected='') {
        const matches=mrFloors.filter(x=>x.facility_id===facility.value);
        fillSelect(floor,'Select floor',matches,'floor_id',x=>x.name+(x.code?' ('+x.code+')':''),selected);
        updateRooms();
    }
    facility.addEventListener('change',()=>updateFloors());
    floor.addEventListener('change',()=>updateRooms());
    room.addEventListener('change',()=>updateEquipment());
    category.addEventListener('change',()=>updateEquipment());
    updateFloors(oldFloor);
    if(oldFloor){updateRooms(oldRoom);}
    if(oldRoom){updateEquipment(oldEquipment);}

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
    document.getElementById('cancelButton').addEventListener('click',function(){if(confirm('Cancel this maintenance request and return to the dashboard?'))window.location.href='Dashboard.php';});
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
        const btn=document.getElementById('submitButton');btn.disabled=true;btn.textContent='Submitting...';
    });
});
</script>
</body>
</html>