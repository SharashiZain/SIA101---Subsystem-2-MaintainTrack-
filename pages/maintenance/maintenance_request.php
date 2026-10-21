<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function mpApi(string $method, string $endpoint, ?array $payload = null, array $extraHeaders = []): array {
    $base = defined('SUPABASE_URL') ? (string)SUPABASE_URL : '';
    $key = defined('SUPABASE_SECRET_KEY') ? (string)SUPABASE_SECRET_KEY : '';
    if ($base === '' || $key === '' || !function_exists('curl_init')) return ['success'=>false,'data'=>null,'error'=>'Supabase configuration or PHP cURL is unavailable.'];
    $ch = curl_init(rtrim($base,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers = ['apikey: '.$key,'Authorization: Bearer '.$key,'Accept: application/json','Content-Type: application/json'];
    foreach ($extraHeaders as $h) $headers[] = $h;
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30]);
    if ($payload !== null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    $raw=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw===false) return ['success'=>false,'data'=>null,'error'=>$err ?: 'Supabase request failed.'];
    $decoded=json_decode($raw,true); $ok=$code>=200 && $code<300;
    return ['success'=>$ok,'data'=>$decoded,'error'=>$ok?null:(is_array($decoded)?($decoded['message']??$decoded['details']??$decoded['hint']??$raw):$raw),'status'=>$code];
}
function mpText($v, string $fallback='—'): string { return htmlspecialchars((string)($v ?? $fallback),ENT_QUOTES,'UTF-8'); }
function mpIsUuid(string $value): bool { return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value); }
function mpUpload(string $path, string $tmp, string $mime): array {
    $base=rtrim((string)SUPABASE_URL,'/'); $key=(string)SUPABASE_SECRET_KEY;
    $url=$base.'/storage/v1/object/report-images/'.implode('/',array_map('rawurlencode',explode('/',$path)));
    $bytes=file_get_contents($tmp); if ($bytes===false) return ['success'=>false,'error'=>'Could not read the uploaded photo.'];
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_POSTFIELDS=>$bytes,CURLOPT_HTTPHEADER=>['apikey: '.$key,'Authorization: Bearer '.$key,'Content-Type: '.$mime,'x-upsert: false'],CURLOPT_TIMEOUT=>60]);
    $raw=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw===false || $err!=='') return ['success'=>false,'error'=>$err ?: 'Photo upload failed.'];
    if ($code<200 || $code>=300) { $j=json_decode($raw,true); return ['success'=>false,'error'=>is_array($j)?($j['message']??$j['error']??$raw):$raw]; }
    return ['success'=>true];
}
function mpSignedUrl(string $path): string {
    if ($path==='') return '';
    $url=rtrim((string)SUPABASE_URL,'/').'/storage/v1/object/sign/report-images/'.implode('/',array_map('rawurlencode',explode('/',$path)));
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['expiresIn'=>3600]),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SECRET_KEY,'Authorization: Bearer '.SUPABASE_SECRET_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
    $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $j=json_decode((string)$raw,true);
    if ($code<200 || $code>=300 || empty($j['signedURL'])) return '';
    $s=(string)$j['signedURL']; return str_starts_with($s,'http')?$s:rtrim((string)SUPABASE_URL,'/').'/storage/v1'.$s;
}

/**
 * Resolves the logged-in account to its users.user_id. The session may hold either the users.user_id
 * or the Supabase auth_user_id, and assigned_to always stores users.user_id, so both are checked.
 */
function mpResolveCurrentUser(): ?array {
    $sessionId = trim((string)($_SESSION['user_id'] ?? ''));
    $sessionUsername = trim((string)($_SESSION['username'] ?? $_SESSION['user'] ?? ''));
    $filters = [];
    if (mpIsUuid($sessionId)) {
        $filters[] = 'user_id.eq.'.$sessionId;
        $filters[] = 'auth_user_id.eq.'.$sessionId;
    }
    if ($sessionUsername !== '') $filters[] = 'username.eq.'.$sessionUsername;
    if (!$filters) return null;
    $q = mpApi('GET', 'users?or=('.rawurlencode(implode(',', $filters)).')&select=user_id,username,full_name,role,status&limit=1');
    $u = $q['success'] && !empty($q['data'][0]) ? $q['data'][0] : null;
    if (!$u) return null;
    $_SESSION['user_id'] = (string)$u['user_id'];
    $_SESSION['full_name'] = $u['full_name'] ?? '';
    $_SESSION['role'] = $u['role'] ?? ($_SESSION['role'] ?? '');
    return $u;
}

$pageError=''; $pageSuccess=''; $request=null; $updates=[]; $showUpdateModal=false;
$currentUser = mpResolveCurrentUser();
$currentUserId = $currentUser ? (string)$currentUser['user_id'] : '';
// The id may be the request UUID or the human-readable display ID (e.g. MT-00134).
$requestId=trim((string)($_GET['id']??$_POST['request_id']??''));

if ($currentUserId==='' || strtolower((string)($_SESSION['role']??''))!=='maintenance') $pageError='This page is available to logged-in Maintenance Personnel only. Please sign in again.';
if ($pageError==='' && $requestId==='') $pageError='Missing request ID. Please open this page from My Tasks.';

if ($pageError==='') {
    // Only send the value to the uuid column when it really is a UUID; otherwise match on display_id.
    $lookupColumn = mpIsUuid($requestId) ? 'request_id' : 'display_id';
    $rr=mpApi('GET','maintenance_requests?'.$lookupColumn.'=eq.'.rawurlencode($requestId).'&select=request_id,display_id,concern,description,category,equipment_category,priority,status,facility_id,floor_id,room_id,equipment_id,specific_area,reported_by,assigned_to,image_path,date_reported,date_assigned,date_started,date_completed,created_at&limit=1');
    if (!$rr['success']) {
        $pageError='Unable to load request. '.($rr['error']??'');
    } elseif (empty($rr['data'][0])) {
        $pageError='No request with ID "'.$requestId.'" was found. Please open this page from My Tasks.';
    } elseif ((string)($rr['data'][0]['assigned_to'] ?? '') !== $currentUserId) {
        $pageError='This request ('.($rr['data'][0]['display_id'] ?? $requestId).') is not assigned to your account.';
    } else {
        $request=$rr['data'][0];
        // From here on, every query uses the real request UUID.
        $requestId=(string)$request['request_id'];
    }
}

/* ---- Reject request (reason is required and saved to maintenance_rejections) ---- */
if ($_SERVER['REQUEST_METHOD']==='POST' && $pageError==='' && ($_POST['form_action']??'')==='reject') {
    $rejectReason=trim((string)($_POST['reject_reason']??''));
    $currentStatusLower=strtolower(trim((string)($request['status']??'')));
    if ($rejectReason==='') {
        $pageError='Please enter a reason for rejecting this request.';
    } elseif (in_array($currentStatusLower,['completed','rejected'],true)) {
        $pageError='This request can no longer be rejected.';
    } else {
        $savedReject=mpApi('PATCH','maintenance_requests?request_id=eq.'.rawurlencode($requestId).'&assigned_to=eq.'.rawurlencode($currentUserId),['status'=>'Rejected'],['Prefer: return=representation']);
        if (!$savedReject['success'] || empty($savedReject['data'])) {
            $pageError='Unable to reject request. '.($savedReject['error']??'No matching assigned request was updated.');
        } else {
            $insertReject=mpApi('POST','maintenance_rejections',['request_id'=>$requestId,'rejected_by'=>$currentUserId,'reason'=>$rejectReason],['Prefer: return=minimal']);
            if (!$insertReject['success']) {
                $pageError='The request was rejected, but saving the reason failed: '.($insertReject['error']??'');
            } else {
                mpApi('POST','request_timeline',['request_id'=>$requestId,'status'=>'Rejected','actor_user_id'=>$currentUserId,'note'=>mb_substr('Rejected: '.$rejectReason,0,255)],['Prefer: return=minimal']);
                $mpName=(string)($_SESSION['full_name']??$_SESSION['username']??'Maintenance Personnel');
                mpApi('POST','activity_log',['action_text'=>mb_substr('Request '.($request['display_id']??$requestId).' rejected by '.$mpName.': '.$rejectReason,0,255),'related_request_id'=>$requestId,'actor_user_id'=>$currentUserId],['Prefer: return=minimal']);
                $_SESSION['maintenance_request_flash']='Maintenance request rejected.';
                header('Location: maintenance_request.php?id='.rawurlencode($requestId)); exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $pageError==='' && ($_POST['form_action']??'update')==='update') {
    $status=trim((string)($_POST['update_status']??''));
    $title=trim((string)($_POST['update_title']??''));
    $description=trim((string)($_POST['update_description']??''));
    $allowed=['In Progress','Completed','Fail to Fix'];
    if (!in_array($status,$allowed,true) || $title==='' || $description==='') $pageError='Please select a status and enter both the update title and description.';
    $photoPath=null;
    if ($pageError==='' && isset($_FILES['update_photo']) && $_FILES['update_photo']['error']!==UPLOAD_ERR_NO_FILE) {
        $file=$_FILES['update_photo'];
        if ($file['error']!==UPLOAD_ERR_OK) {
            $pageError = match ((int)$file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The photo is larger than the server accepts. Ask the administrator to raise upload_max_filesize and post_max_size in php.ini, or use a smaller photo (max 5 MB).',
                UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not save the photo. Please try again.',
                default => 'The photo upload did not complete.',
            };
        }
        elseif ($file['size']>5*1024*1024) $pageError='Photo must not exceed 5 MB.';
        else {
            $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']); $exts=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($exts[$mime])) $pageError='Upload a JPG, PNG, or WEBP image.';
            else { $photoPath='maintenance-updates/'.preg_replace('/[^A-Za-z0-9_-]/','',(string)($request['display_id']??'request')).'_'.bin2hex(random_bytes(8)).'.'.$exts[$mime]; $up=mpUpload($photoPath,$file['tmp_name'],$mime); if (!$up['success']) { $pageError='Photo upload failed: '.($up['error']??'Unknown error'); $photoPath=null; } }
        }
    }
    if ($pageError==='') {
        // The migration adds Fail to Fix to the request_status enum so the chosen status is stored directly.
        $requestStatus=$status;
        $patch=['status'=>$requestStatus];
        if ($requestStatus==='In Progress' && empty($request['date_started'])) $patch['date_started']=date('c');
        if ($requestStatus==='Completed') $patch['date_completed']=date('c');
        $savedStatus=mpApi('PATCH','maintenance_requests?request_id=eq.'.rawurlencode($requestId).'&assigned_to=eq.'.rawurlencode($currentUserId),$patch,['Prefer: return=representation']);
        if (!$savedStatus['success'] || empty($savedStatus['data'])) $pageError='Unable to update request status. '.($savedStatus['error']??'No matching assigned request was updated.');
        else {
            $updateText=$description;
            $insert=mpApi('POST','maintenance_updates',['request_id'=>$requestId,'author_user_id'=>$currentUserId,'title'=>$title,'status'=>$status,'update_text'=>$updateText,'photo_path'=>$photoPath],['Prefer: return=representation']);
            if (!$insert['success']) $pageError='The request status was updated, but saving the update log failed: '.($insert['error']??'');
            else {
                mpApi('POST','request_timeline',['request_id'=>$requestId,'status'=>$requestStatus,'actor_user_id'=>$currentUserId,'note'=>mb_substr($status.': '.$title,0,255)],['Prefer: return=minimal']);
                $mpName=(string)($_SESSION['full_name']??$_SESSION['username']??'Maintenance Personnel');
                mpApi('POST','activity_log',['action_text'=>mb_substr('Request '.($request['display_id']??$requestId).' updated by '.$mpName.': '.$status.' — '.$title,0,255),'related_request_id'=>$requestId,'actor_user_id'=>$currentUserId],['Prefer: return=minimal']);
                $_SESSION['maintenance_request_flash']='Maintenance update saved successfully.';
                header('Location: maintenance_request.php?id='.rawurlencode($requestId)); exit;
            }
        }
    }
    // Keep the update modal open with the error, so the user does not lose what they typed.
    if ($pageError!=='') $showUpdateModal=true;
}
if (!empty($_SESSION['maintenance_request_flash'])) { $pageSuccess=(string)$_SESSION['maintenance_request_flash']; unset($_SESSION['maintenance_request_flash']); }

if ($request && $pageError==='') {
    $lookups=[];
    foreach ([
        'facility'=>['facilities','facility_id',$request['facility_id']??'','name'],
        'floor'=>['facility_floors','floor_id',$request['floor_id']??'','name'],
        'room'=>['facility_rooms','room_id',$request['room_id']??'','name'],
        'equipment'=>['equipment','equipment_id',$request['equipment_id']??'','display_id,name'],
        'reporter'=>['users','user_id',$request['reported_by']??'','full_name,username']
    ] as $key=>$spec) {
        if (empty($spec[2])) { $lookups[$key]='—'; continue; }
        $q=mpApi('GET',$spec[0].'?'.$spec[1].'=eq.'.rawurlencode((string)$spec[2]).'&select='.$spec[3].'&limit=1');
        $x=$q['data'][0]??[];
        if ($key==='equipment') $lookups[$key]=trim((string)($x['display_id']??'').' '.(string)($x['name']??''));
        elseif ($key==='reporter') $lookups[$key]=(string)($x['full_name']??$x['username']??'—');
        else $lookups[$key]=(string)($x['name']??'—');
    }
    $ur=mpApi('GET','maintenance_updates?request_id=eq.'.rawurlencode($requestId).'&select=update_id,author_user_id,title,status,update_text,photo_path,created_at&order=created_at.desc');
    if ($ur['success'] && is_array($ur['data'])) {
        $updates=$ur['data'];
        foreach ($updates as &$up) {
            $up['photo_url'] = !empty($up['photo_path']) ? mpSignedUrl((string)$up['photo_path']) : '';
            $up['title'] = $up['title'] ?? 'Maintenance Update';
            $up['description'] = (string)($up['update_text'] ?? '');
            $up['update_status'] = $up['status'] ?? '';
            $authorId = (string)($up['author_user_id'] ?? '');
            $authorName = '';
            if ($authorId !== '') {
                $authorResult = mpApi('GET', 'users?user_id=eq.' . rawurlencode($authorId) . '&select=full_name,username&limit=1');
                $author = $authorResult['data'][0] ?? [];
                $authorName = trim((string)($author['full_name'] ?? $author['username'] ?? ''));
            }
            $up['author_name'] = $authorName !== '' ? $authorName : 'Maintenance Personnel';
        }
        unset($up);
    }
}

$currentStatusText = strtolower(trim((string)($request['status'] ?? '')));
$isRejected = $currentStatusText === 'rejected';
$canReject = $request && !$isRejected && $currentStatusText !== 'completed';

renderHead('Request Details', 'Request.css', 'maintenance');
?>
<style>
/* Photo drop zone (kept here so it works even if Request.css is cached or outdated) */
#updateModal .mp-dropzone {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 84px;
  padding: 12px;
  border: 2px dashed #9fb8cc;
  border-radius: 12px;
  background: #f7fbff;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}
#updateModal .mp-dropzone:hover,
#updateModal .mp-dropzone:focus-visible,
#updateModal .mp-dropzone.is-dragover {
  border-color: #1769aa;
  background: #eaf3fb;
  outline: none;
}
#updateModal .mp-drop-inner strong {
  display: block;
  font-size: 14px;
  color: #12355f;
}
#updateModal .mp-drop-inner span {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: #687985;
}
</style>
<div class="page mp-request-page">
  <?php if ($pageError!==''): ?><div class="mp-alert error" role="alert"><?=mpText($pageError)?></div><?php endif; ?>
  <?php if ($pageSuccess!==''): ?><div class="mp-alert success" role="status"><?=mpText($pageSuccess)?></div><?php endif; ?>

  <?php if ($request && $pageError===''): ?>
    <a class="mp-back-top" href="maintenance_task.php"><span aria-hidden="true">←</span> Back to My Tasks</a>

    <main class="mp-request-shell">
      <header class="mp-page-banner">
        <div>
          <h1>Request Details</h1>
          <p>MAINTENANCE PERSONNEL PORTAL · BARANGAY GULOD</p>
        </div>
        <div class="mp-user-chip">
          <span><?=mpText($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Maintenance Personnel')?></span>
          <b>MAINTENANCE</b>
        </div>
      </header>

      <section class="mp-request-id-card<?= $isRejected ? ' is-rejected' : '' ?>">
        <div>
          <span class="mp-kicker">REQUEST ID</span>
          <h2><?=mpText($request['display_id'] ?? $request['request_id'])?></h2>
          <p><?=mpText($request['concern'] ?? 'Maintenance Request')?></p>
        </div>
        <div class="mp-request-id-actions">
          <button class="mp-btn" type="button" id="openUpdateModal" style="<?= $isRejected ? 'display:none' : '' ?>">Update Maintenance Request</button>
          <button class="mp-btn-reject" type="button" id="openRejectModal" style="<?= $canReject ? '' : 'display:none' ?>">Reject Request</button>
          <span class="mp-status"><?=mpText($request['status'] ?? 'Assigned')?></span>
        </div>
      </section>

      <section class="mp-progress-card">
        <h2 class="mp-section-title">Request Progress</h2>
        <?php
          $currentStatus = strtolower(trim((string)($request['status'] ?? 'Pending')));
          $steps = [
            ['label'=>'Submitted', 'done'=>true],
            ['label'=>'Assigned', 'done'=>in_array($currentStatus, ['assigned','in progress','completed','fail to fix'], true)],
            ['label'=>'In Progress', 'done'=>in_array($currentStatus, ['in progress','completed'], true), 'active'=>$currentStatus==='in progress'],
            ['label'=>'Completed', 'done'=>$currentStatus==='completed', 'active'=>$currentStatus==='completed']
          ];
        ?>
        <ol class="mp-progress-steps">
          <?php foreach ($steps as $i=>$step): ?>
            <li class="<?=!empty($step['active'])?'active':(!empty($step['done'])?'done':'')?> <?=($currentStatus==='fail to fix' && $i===2)?'failed':''?>">
              <span class="mp-step-dot"><?=!empty($step['done'])?'✓':''?></span>
              <strong><?=mpText($step['label'])?></strong>
              <small><?php
                if ($i===0) echo !empty($request['date_reported']) ? mpText(date('M j · g:i A', strtotime($request['date_reported']))) : 'Submitted';
                elseif ($i===1) echo !empty($request['date_assigned']) ? mpText(date('M j · g:i A', strtotime($request['date_assigned']))) : '—';
                elseif ($i===2) echo !empty($request['date_started']) ? mpText(date('M j · g:i A', strtotime($request['date_started']))) : '—';
                else echo !empty($request['date_completed']) ? mpText(date('M j · g:i A', strtotime($request['date_completed']))) : '—';
              ?></small>
            </li>
          <?php endforeach; ?>
        </ol>
        <?php if ($currentStatus==='fail to fix'): ?><p class="mp-fail-note">Latest result: Fail to Fix</p><?php endif; ?>
      </section>

      <div class="mp-detail-columns">
        <div class="mp-detail-main">
          <section class="mp-panel">
            <h2 class="mp-section-title">Maintenance Details</h2>
            <div class="mp-detail-grid">
              <div class="mp-detail-field"><span>Concern</span><strong><?=mpText($request['concern']??'—')?></strong></div>
              <div class="mp-detail-field"><span>Category</span><strong><?=mpText($request['equipment_category']??$request['category']??'—')?></strong></div>
              <div class="mp-detail-field"><span>Facility</span><strong><?=mpText($lookups['facility']??'—')?></strong></div>
              <div class="mp-detail-field"><span>Equipment</span><strong><?=mpText($lookups['equipment']??'—')?></strong></div>
              <div class="mp-detail-field"><span>Specific Area</span><strong><?=mpText(trim(($lookups['floor']??'').' · '.($lookups['room']??''), ' ·') . (!empty($request['specific_area']) ? ' · '.$request['specific_area'] : ''))?></strong></div>
              <div class="mp-detail-field"><span>Priority</span><strong><?=mpText($request['priority']??'—')?></strong></div>
              <div class="mp-detail-field"><span>Requested By</span><strong><?=mpText($lookups['reporter']??'—')?></strong></div>
              <div class="mp-detail-field"><span>Date Reported</span><strong><?=mpText(!empty($request['date_reported'])?date('M j, Y g:i A',strtotime($request['date_reported'])):'—')?></strong></div>
              <div class="mp-detail-field mp-detail-wide"><span>Reported Description</span><p><?=nl2br(mpText($request['description']??'No description provided.'))?></p></div>
            </div>
          </section>

          <section class="mp-panel mp-maintenance-updates">
            <div class="mp-panel-heading">
              <div><h2 class="mp-section-title">Maintenance Updates</h2><p>Work progress and notes recorded for this request.</p></div>
            </div>
            <?php if (!$updates): ?>
              <div class="mp-empty">No maintenance updates have been submitted yet.</div>
            <?php else: foreach ($updates as $up): ?>
              <article class="mp-update-card">
                <div class="mp-update-meta">
                  <strong class="mp-update-author"><?=mpText($up['author_name']??'Maintenance Personnel')?></strong>
                  <div class="mp-update-meta-right">
                    <time><?=mpText(!empty($up['created_at'])?date('M j, Y · g:i A',strtotime($up['created_at'])):'')?></time>
                    <span class="mp-update-status"><?=mpText($up['update_status']?:'Update')?></span>
                  </div>
                </div>
                <h3><?=mpText($up['title'])?></h3>
                <p class="mp-update-description"><?=nl2br(mpText($up['description']))?></p>
                <?php if (!empty($up['photo_url'])): ?>
                  <a class="mp-update-photo" href="<?=mpText($up['photo_url'])?>" target="_blank" rel="noopener" aria-label="Open maintenance update picture">
                    <img src="<?=mpText($up['photo_url'])?>" alt="Maintenance progress photo">
                  </a>
                <?php endif; ?>
              </article>
            <?php endforeach; endif; ?>
          </section>
        </div>

        <aside class="mp-detail-side">
          <section class="mp-panel">
            <h2 class="mp-section-title">Reported Photo</h2>
            <?php if (!empty($request['image_path'])): $originalPhoto=mpSignedUrl((string)$request['image_path']); ?>
              <?php if ($originalPhoto!==''): ?>
                <a class="mp-reported-photo" href="<?=mpText($originalPhoto)?>" target="_blank" rel="noopener"><img src="<?=mpText($originalPhoto)?>" alt="Original maintenance report photo"></a>
                <p class="mp-photo-hint">Select the photo to view it in full size.</p>
              <?php else: ?><p class="mp-photo-hint">The reported photo is currently unavailable.</p><?php endif; ?>
            <?php else: ?><p class="mp-photo-hint">No photo was attached to the original request.</p><?php endif; ?>
          </section>

        </aside>
      </div>
    </main>

    <div class="mp-overlay" id="updateModal" style="display:<?= $showUpdateModal ? 'flex' : 'none' ?>" role="dialog" aria-modal="true" aria-labelledby="updateModalTitle">
      <div class="mp-modal">
        <div class="mp-modal-head"><div><h2 id="updateModalTitle">Update Maintenance Request</h2><p><?=mpText($request['display_id']??$request['request_id'])?></p></div><button type="button" class="mp-x" id="closeUpdateModal" aria-label="Close">&times;</button></div>
        <form method="POST" enctype="multipart/form-data" id="maintenanceUpdateForm">
          <input type="hidden" name="request_id" value="<?=mpText($requestId)?>">
          <input type="hidden" name="form_action" value="update">
          <label class="mp-label" for="updateStatus">Status <span>*</span></label>
          <select id="updateStatus" name="update_status" required><option value="">Select status</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option><option value="Fail to Fix">Fail to Fix</option></select>
          <label class="mp-label" for="updateTitle">Title <span>*</span></label>
          <input id="updateTitle" name="update_title" type="text" maxlength="200" required value="<?=mpText($request['concern']??'')?>" placeholder="e.g. Replaced damaged power cable">
          <label class="mp-label" for="updateDescription">Description <span>*</span></label>
          <textarea id="updateDescription" name="update_description" rows="3" required placeholder="Describe the work done, findings, or reason it could not be fixed..."></textarea>
          <span class="mp-label">Add Picture <small>(optional · JPG, PNG, WEBP · max 5 MB)</small></span>
          <div class="mp-dropzone" id="mpDropzone" tabindex="0" role="button" aria-describedby="mpPhotoName">
            <input id="updatePhoto" name="update_photo" type="file" accept="image/jpeg,image/png,image/webp" hidden>
            <div class="mp-drop-inner">
              <strong>Drag a photo here</strong>
              <span>or click to browse</span>
            </div>
          </div>
          <div id="mpPhotoName" class="mp-photo-name">No picture selected</div>
          <div class="mp-modal-actions"><button type="button" class="mp-cancel" id="cancelUpdateModal">Cancel</button><button type="submit" class="mp-btn" id="saveMaintenanceUpdate">Save Update</button></div>
        </form>
      </div>
    </div>

    <div class="mp-overlay" id="rejectModal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
      <div class="mp-modal">
        <div class="mp-modal-head"><div><h2 id="rejectModalTitle">Reject Maintenance Request</h2><p><?=mpText($request['display_id']??$request['request_id'])?></p></div><button type="button" class="mp-x" id="closeRejectModal" aria-label="Close">&times;</button></div>
        <form method="POST" id="rejectRequestForm">
          <input type="hidden" name="request_id" value="<?=mpText($requestId)?>">
          <input type="hidden" name="form_action" value="reject">

          <div id="rejectStep1">
            <label class="mp-label" for="rejectReason">Why are you rejecting this request? <span>*</span></label>
            <textarea id="rejectReason" name="reject_reason" rows="5" required minlength="5" placeholder="Explain why this request cannot be accepted..."></textarea>
            <div class="mp-modal-actions"><button type="button" class="mp-cancel" id="cancelRejectModal">Cancel</button><button type="button" class="mp-btn-reject" id="rejectNext">Continue</button></div>
          </div>

          <div id="rejectStep2" style="display:none">
            <p class="mp-label">Are you sure you want to reject this request?</p>
            <div class="mp-modal-actions"><button type="button" class="mp-cancel" id="rejectBack2">Back</button><button type="button" class="mp-btn-reject" id="rejectConfirm2">Yes, continue</button></div>
          </div>

          <div id="rejectStep3" style="display:none">
            <p class="mp-label">Final confirmation: this marks the request as Rejected and saves your reason.</p>
            <div class="mp-modal-actions"><button type="button" class="mp-cancel" id="rejectBack3">Back</button><button type="submit" class="mp-btn-reject" id="saveRejectRequest">Confirm Rejection</button></div>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const modal=document.getElementById('updateModal'); if(!modal)return;
 const open=()=>{modal.style.display='flex';document.body.style.overflow='hidden';};
 const close=()=>{modal.style.display='none';document.body.style.overflow='';};
 if(modal.style.display==='flex') document.body.style.overflow='hidden';
 document.getElementById('openUpdateModal').addEventListener('click',open);
 document.getElementById('closeUpdateModal').addEventListener('click',close);
 document.getElementById('cancelUpdateModal').addEventListener('click',close);
 modal.addEventListener('click',e=>{if(e.target===modal)close();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
 document.getElementById('maintenanceUpdateForm').addEventListener('submit',()=>{const b=document.getElementById('saveMaintenanceUpdate');b.disabled=true;b.textContent='Saving...';});

 /* ---- Photo drop zone: drag a file in, click to browse, or press Enter ---- */
 const dropzone=document.getElementById('mpDropzone');
 const photoInput=document.getElementById('updatePhoto');
 const photoName=document.getElementById('mpPhotoName');
 const allowedTypes=['image/jpeg','image/png','image/webp'];
 const maxBytes=5*1024*1024;
 const setPhoto=file=>{
   if(!file){photoName.textContent='No picture selected';return;}
   if(!allowedTypes.includes(file.type)){photoInput.value='';photoName.textContent='Upload a JPG, PNG, or WEBP image.';return;}
   if(file.size>maxBytes){photoInput.value='';photoName.textContent='Photo must not exceed 5 MB.';return;}
   const dt=new DataTransfer(); dt.items.add(file); photoInput.files=dt.files;
   photoName.textContent=file.name;
 };
 dropzone.addEventListener('click',()=>photoInput.click());
 dropzone.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();photoInput.click();}});
 ['dragenter','dragover'].forEach(ev=>dropzone.addEventListener(ev,e=>{e.preventDefault();dropzone.classList.add('is-dragover');}));
 ['dragleave','drop'].forEach(ev=>dropzone.addEventListener(ev,e=>{e.preventDefault();dropzone.classList.remove('is-dragover');}));
 dropzone.addEventListener('drop',e=>setPhoto(e.dataTransfer.files[0]));
 photoInput.addEventListener('change',()=>setPhoto(photoInput.files[0]));
 // Stop the browser from opening a photo dropped outside the zone
 window.addEventListener('dragover',e=>e.preventDefault());
 window.addEventListener('drop',e=>e.preventDefault());

 const rejectModal=document.getElementById('rejectModal');
 const rejectForm=document.getElementById('rejectRequestForm');
 const rejectSteps=[document.getElementById('rejectStep1'),document.getElementById('rejectStep2'),document.getElementById('rejectStep3')];
 const showRejectStep=n=>rejectSteps.forEach((s,i)=>{s.style.display=i===n?'block':'none';});
 const openReject=()=>{showRejectStep(0);rejectModal.style.display='flex';document.body.style.overflow='hidden';};
 const closeReject=()=>{rejectModal.style.display='none';document.body.style.overflow='';};
 document.getElementById('openRejectModal').addEventListener('click',openReject);
 document.getElementById('closeRejectModal').addEventListener('click',closeReject);
 document.getElementById('cancelRejectModal').addEventListener('click',closeReject);
 rejectModal.addEventListener('click',e=>{if(e.target===rejectModal)closeReject();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')closeReject();});
 document.getElementById('rejectNext').addEventListener('click',()=>{if(rejectForm.reportValidity())showRejectStep(1);});
 document.getElementById('rejectBack2').addEventListener('click',()=>showRejectStep(0));
 document.getElementById('rejectConfirm2').addEventListener('click',()=>showRejectStep(2));
 document.getElementById('rejectBack3').addEventListener('click',()=>showRejectStep(1));
 rejectForm.addEventListener('submit',()=>{const b=document.getElementById('saveRejectRequest');b.disabled=true;b.textContent='Rejecting...';});
});
</script>
<?php renderFoot(); ?>
</html>