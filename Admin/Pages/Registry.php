<?php
declare(strict_types=1);

require_once '../../Components/layout.php';
require_once '../../Config/supabase.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* Registry server-side Supabase helper. Keep the secret key in Config/supabase.php only. */
function registryRequest(string $method, string $endpoint, ?array $payload = null, array $extraHeaders = []): array {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
        return ['success'=>false,'status'=>0,'data'=>null,'error'=>'Missing Supabase configuration or PHP cURL.'];
    }
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
    $headers = ['apikey: '.SUPABASE_SECRET_KEY,'Authorization: Bearer '.SUPABASE_SECRET_KEY,'Content-Type: application/json','Accept: application/json'];
    foreach ($extraHeaders as $header) $headers[] = $header;
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30]);
    if ($payload !== null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_SLASHES));
    $body=curl_exec($ch); $curlError=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body===false || $curlError!=='') return ['success'=>false,'status'=>$status,'data'=>null,'error'=>$curlError ?: 'Request failed'];
    $decoded=json_decode($body,true);
    return ['success'=>$status>=200 && $status<300,'status'=>$status,'data'=>$decoded,'error'=>($status<200 || $status>=300) ? (is_array($decoded)?($decoded['message']??$decoded['hint']??$decoded['details']??$body):$body) : null];
}
function registryEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function registryGet(string $endpoint): array {
    $r=registryRequest('GET',$endpoint);
    return ($r['success'] && is_array($r['data'])) ? $r['data'] : [];
}
function registryUpload(string $bucket,string $path,string $tmp,string $mime): array {
    $url=rtrim(SUPABASE_URL,'').'/storage/v1/object/'.rawurlencode($bucket).'/'.implode('/',array_map('rawurlencode',explode('/',$path)));
    $bytes=file_get_contents($tmp);
    if ($bytes===false) return ['success'=>false,'error'=>'Cannot read uploaded image.'];
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_POSTFIELDS=>$bytes,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SECRET_KEY,'Authorization: Bearer '.SUPABASE_SECRET_KEY,'Content-Type: '.$mime,'x-upsert: false'],CURLOPT_TIMEOUT=>60]);
    $body=curl_exec($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body===false || $err!=='') return ['success'=>false,'error'=>$err ?: 'Storage upload failed.'];
    if ($status<200 || $status>=300) { $j=json_decode($body,true); return ['success'=>false,'error'=>is_array($j)?($j['message']??$j['error']??$body):$body]; }
    return ['success'=>true];
}
$types=['Computer'=>'PC','Aircon'=>'AC','Printer'=>'PR','Light'=>'LT','Switch'=>'SW','Router'=>'RT','Projector'=>'PJ','Other'=>'OT'];
$success=$_SESSION['registry_success']??''; unset($_SESSION['registry_success']); $error='';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_equipment') {
    $name=trim((string)($_POST['equipment_name']??'')); $type=trim((string)($_POST['equipment_type']??''));
    $category=trim((string)($_POST['equipment_category']??'')); $facilityId=trim((string)($_POST['facility_id']??''));
    $floorId=trim((string)($_POST['floor_id']??'')); $roomId=trim((string)($_POST['room_id']??''));
    $date=trim((string)($_POST['registration_date']??date('Y-m-d')));
    if ($name==='' || !isset($types[$type]) || !in_array($category,['Electrical','Non Electrical'],true) || !$facilityId || !$floorId || !$roomId || !$date) {
        $error='Please complete all required fields.';
    }
    $facility=$floor=$room=null;
    if ($error==='') {
        $a=registryRequest('GET','facilities?select=facility_id,name,code&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $b=registryRequest('GET','facility_floors?select=floor_id,facility_id,name,code&floor_id=eq.'.rawurlencode($floorId).'&facility_id=eq.'.rawurlencode($facilityId).'&limit=1');
        $c=registryRequest('GET','facility_rooms?select=room_id,floor_id,name,code&room_id=eq.'.rawurlencode($roomId).'&floor_id=eq.'.rawurlencode($floorId).'&limit=1');
        if (!$a['success'] || !$b['success'] || !$c['success']) $error='Missing database columns/tables. Run the Registry SQL setup first.';
        else { $facility=$a['data'][0]??null; $floor=$b['data'][0]??null; $room=$c['data'][0]??null; if (!$facility||!$floor||!$room) $error='Please select a valid Facility, Floor, and Room combination.'; }
    }
    $imagePath=null;
    if ($error==='' && isset($_FILES['equipment_picture']) && $_FILES['equipment_picture']['error']!==UPLOAD_ERR_NO_FILE) {
        $file=$_FILES['equipment_picture'];
        if ($file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024) $error='Image must upload correctly and be no larger than 5 MB.';
        else {
            $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
            $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($extensions[$mime])) $error='Picture must be JPG, PNG, or WEBP.';
            else $imagePath='equipment/'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];
            if ($error==='' && $imagePath!==null) {
                $upload=registryUpload('equipment-images',$imagePath,$file['tmp_name'],$mime);
                if (!$upload['success']) $error='Storage upload failed: '.($upload['error']??'Unknown error');
            }
        }
    }
    if ($error==='') {
        $typeCode=$types[$type]; $facilityCode=strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)$facility['code']));
        $roomCode=strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)$room['code']));
        if ($facilityCode==='' || $roomCode==='') $error='Add a short code to the selected facility and room first (example: BH and SO).';
        else {
            $prefix=$typeCode.'-'.$facilityCode.'-'.$roomCode.'-';
            $existing=registryRequest('GET','equipment?select=display_id&display_id=like.'.rawurlencode($prefix.'*').'&limit=1000');
            if (!$existing['success']) $error='Could not generate Equipment ID: '.($existing['error']??'Unknown error');
            else {
                $highest=0;
                foreach (($existing['data']??[]) as $row) if (preg_match('/-(\d+)$/',(string)($row['display_id']??''),$m)) $highest=max($highest,(int)$m[1]);
                $displayId=$prefix.str_pad((string)($highest+1),2,'0',STR_PAD_LEFT);
                $payload=['display_id'=>$displayId,'name'=>$name,'type'=>$type,'category'=>$category,'facility_id'=>$facilityId,'floor_id'=>$floorId,'room_id'=>$roomId,'location'=>$room['name'],'specific_area'=>$floor['name'],'registration_date'=>$date,'image_path'=>$imagePath,'status'=>'Active'];
                $saved=registryRequest('POST','equipment',$payload,['Prefer: return=representation']);
                if ($saved['success']) { $_SESSION['registry_success']='Equipment '.$displayId.' registered successfully.'; header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit; }
                $error='Could not save equipment: '.($saved['error']??'Unknown error');
            }
        }
    }
}
$facResult=registryRequest('GET','facilities?select=facility_id,name,code&order=name.asc');
$floorResult=registryRequest('GET','facility_floors?select=floor_id,facility_id,name,code&order=name.asc');
$roomResult=registryRequest('GET','facility_rooms?select=room_id,floor_id,name,code&order=name.asc');
$facilities=($facResult['success'] && is_array($facResult['data'])) ? $facResult['data'] : [];
$floors=($floorResult['success'] && is_array($floorResult['data'])) ? $floorResult['data'] : [];
$rooms=($roomResult['success'] && is_array($roomResult['data'])) ? $roomResult['data'] : [];
$equipResult=registryRequest('GET','equipment?select=equipment_id,display_id,name,type,category,registration_date,image_path,facilities(name),facility_floors(name),facility_rooms(name)&order=created_at.desc');
$equipment=($equipResult['success']&&is_array($equipResult['data']))?$equipResult['data']:[];
if (!$facResult['success'] && $error==='') $error='Unable to load facilities: '.($facResult['error']??'Check facilities table and permissions.');
if (!$floorResult['success'] && $error==='') $error='Unable to load floors: '.($floorResult['error']??'Check facility_floors table and permissions.');
if (!$roomResult['success'] && $error==='') $error='Unable to load rooms: '.($roomResult['error']??'Check facility_rooms table and permissions.');
if (!$equipResult['success'] && $error==='') $error='Unable to load equipment: '.($equipResult['error']??'Check equipment table columns and permissions.');

renderHead('Equipment Registry','Registry.css');
?>
<div class="container">

<main class="card">
  <?php renderPageHeader('Equipment Registry'); ?>
  <div class="card-header">
    <div class="search-wrapper"><span class="search-icon">🔍</span><input id="registrySearch" class="search-bar" placeholder="Search ID, name, facility, floor, room"></div>
    <select class="registry-filter" id="registryTypeFilter"><option value="">All Types</option><?php foreach($types as $label=>$code): ?><option><?=registryEscape($label)?></option><?php endforeach; ?></select>
    <select class="registry-filter" id="registryFacilityFilter"><option value="">All Labs</option><?php foreach($facilities as $f): ?><option value="<?=registryEscape($f['name'])?>"><?=registryEscape($f['name'])?></option><?php endforeach; ?></select>
    <select class="registry-filter" id="registryCategoryFilter"><option value="">All Categories</option><option>Electrical</option><option>Non Electrical</option></select>
    <button class="reset-btn" type="button" id="registryReset">Reset</button><button class="add-btn" type="button" id="addEquipmentBtn">+ Add Equipment</button>
  </div>
  <?php if($error!==''): ?><div class="registry-alert error" id="registryServerError" style="display:none"><?=registryEscape($error)?></div><?php endif; ?>
  <div style="overflow-x:auto"><table class="equipment-table"><thead><tr><th>Equipment ID</th><th>Name</th><th>Type</th><th>Category</th><th>Facility</th><th>Floor</th><th>Room</th><th>Registration Date</th><th>Picture</th></tr></thead>
  <tbody id="equipmentTableBody">
  <?php foreach($equipment as $item): ?>
  <tr data-type="<?=registryEscape($item['type']??'')?>" data-category="<?=registryEscape($item['category']??'')?>" data-facility="<?=registryEscape($item['facilities']['name']??'')?>">
    <td class="font-bold"><?=registryEscape($item['display_id']??'')?></td><td><?=registryEscape($item['name']??'')?></td><td><?=registryEscape($item['type']??'')?></td><td><?=registryEscape($item['category']??'')?></td>
    <td><?=registryEscape($item['facilities']['name']??'')?></td><td><?=registryEscape($item['facility_floors']['name']??'')?></td><td><?=registryEscape($item['facility_rooms']['name']??'')?></td><td><?=registryEscape($item['registration_date']??'')?></td>
    <td><?php if(!empty($item['image_path'])): ?><a target="_blank" rel="noopener" href="<?=registryEscape(rtrim(SUPABASE_URL,'/').'/storage/v1/object/public/equipment-images/'.$item['image_path'])?>">View</a><?php else: ?>—<?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div id="registryNoResults" style="display:none;text-align:center;padding:30px">No equipment found.</div>
  <div class="table-footer registry-pagination-wrap">
    <span id="registryCount"><?=count($equipment)?> items shown</span>
    <div class="registry-pagination-controls">
      <label for="registryPageSize">Rows per page</label>
      <select id="registryPageSize" aria-label="Rows per page"><option value="5">5</option><option value="10" selected>10</option><option value="20">20</option><option value="50">50</option></select>
      <button type="button" id="registryPrevPage" class="registry-page-btn" aria-label="Previous page">Previous</button>
      <div id="registryPageNumbers" class="registry-page-numbers" aria-label="Pagination pages"></div>
      <button type="button" id="registryNextPage" class="registry-page-btn" aria-label="Next page">Next</button>
    </div>
  </div>
</main></div>

<div class="static-modal" id="addEquipmentModal" style="display:none"><div class="static-modal-content registry-modal-content">
  <div class="modal-header"><h3>Register Equipment</h3><button type="button" class="modal-close" id="addEquipmentClose">✕</button></div>
  <form method="post" enctype="multipart/form-data" id="equipmentForm"><input type="hidden" name="action" value="add_equipment">
  <div class="registry-grid">
    <div class="modal-field full"><label>Equipment ID (Automatic)</label><input id="previewId" readonly value="Select type, facility, and room"><small>Example: PC-BH-SO-01</small></div>
    <div class="modal-field"><label for="equipment_name">Equipment Name *</label><input id="equipment_name" name="equipment_name" required maxlength="100" placeholder="e.g. Dell OptiPlex"></div>
    <div class="modal-field"><label for="equipment_type">Equipment Type *</label><select id="equipment_type" name="equipment_type" required><option value="">Select type</option><?php foreach($types as $label=>$code): ?><option value="<?=registryEscape($label)?>" data-code="<?=registryEscape($code)?>"><?=registryEscape($label)?> (<?=registryEscape($code)?>)</option><?php endforeach; ?></select></div>
    <div class="modal-field"><label for="equipment_category">Equipment Category *</label><select id="equipment_category" name="equipment_category" required><option value="">Select category</option><option>Electrical</option><option>Non Electrical</option></select></div>
    <div class="modal-field"><label for="facility_id">Facility *</label><select id="facility_id" name="facility_id" required><option value="">Select facility</option><?php foreach($facilities as $f): ?><option value="<?=registryEscape($f['facility_id'])?>" data-code="<?=registryEscape($f['code']??'')?>"><?=registryEscape($f['name'])?> (<?=registryEscape($f['code']??'NO CODE')?>)</option><?php endforeach; ?></select></div>
    <div class="modal-field"><label for="floor_id">Floor *</label><select id="floor_id" name="floor_id" required disabled><option value="">Select facility first</option></select><small id="floorHelp">Choose a facility first.</small></div>
    <div class="modal-field"><label for="room_id">Room *</label><select id="room_id" name="room_id" required disabled><option value="">Select floor first</option></select><small id="roomHelp">Choose a floor first.</small></div>
    <div class="modal-field"><label for="registration_date">Date of Registration *</label><input type="date" id="registration_date" name="registration_date" value="<?=date('Y-m-d')?>" required></div>
    <div class="modal-field full"><label for="equipment_picture">Equipment Picture (Optional)</label><input type="file" id="equipment_picture" name="equipment_picture" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WEBP; max 5 MB. Needs public Supabase Storage bucket named equipment-images.</small></div>
  </div><div class="modal-actions"><button type="button" class="modal-cancel" id="addEquipmentCancel">Cancel</button><button type="submit" class="modal-save">Register Equipment</button></div>
  </form>
</div></div>

<!-- SYSTEM MODAL: same validation/success/error style used in Users.php -->
<div class="system-modal-overlay" id="registrySystemModal" role="dialog" aria-modal="true" style="display:none;">
  <div class="system-modal">
    <div class="system-modal-icon" id="registrySystemModalIcon"></div>
    <h3 class="system-modal-title" id="registrySystemModalTitle">Notice</h3>
    <p class="system-modal-text" id="registrySystemModalText"></p>
    <div class="system-modal-actions" id="registrySystemModalActions"></div>
  </div>
</div>

<style>
.registry-alert{margin:15px;padding:12px 15px;border-radius:8px}.registry-alert.success{background:#e8f7ee;color:#17663a}.registry-alert.error{background:#fff0ef;color:#9b2525}
.registry-modal-content{width:min(760px,94vw);max-height:90vh;overflow-y:auto}.registry-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.registry-grid .full{grid-column:1/-1}.registry-grid small{display:block;color:#687985;font-size:12px;margin-top:5px}
@media(max-width:650px){.registry-grid{grid-template-columns:1fr}.registry-grid .full{grid-column:auto}}
.registry-pagination-wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 16px}
.registry-pagination-controls{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}
.registry-pagination-controls label{font-size:12px;color:#687985}
.registry-pagination-controls select{min-height:32px;border:1px solid #d9e1e8;border-radius:7px;padding:4px 8px;background:#fff;color:#334155}
.registry-page-btn,.registry-page-number{min-height:32px;min-width:34px;border:1px solid #d9e1e8;border-radius:7px;padding:5px 10px;background:#fff;color:#334155;font-size:12px;cursor:pointer}
.registry-page-btn:disabled{opacity:.45;cursor:not-allowed}
.registry-page-numbers{display:flex;gap:5px;align-items:center}
.registry-page-number.active{background:#1769aa;border-color:#1769aa;color:#fff;font-weight:700}
.registry-page-ellipsis{padding:0 3px;color:#687985}
@media(max-width:700px){.registry-pagination-wrap{align-items:flex-start}.registry-pagination-controls{justify-content:flex-start;width:100%}}
/* System modal styling matched to Users.php */
.system-modal-overlay{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,35,55,.42);backdrop-filter:blur(3px)}
.system-modal{width:100%;max-width:380px;padding:26px 24px 22px;border-radius:16px;background:#fff;box-shadow:0 18px 50px rgba(15,35,55,.22);text-align:center;animation:registryModalIn .18s ease-out}
.system-modal-icon{width:48px;height:48px;margin:0 auto 15px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.system-modal-icon svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.system-modal-icon.icon-success{background:#e4f8ec;color:#16834a}.system-modal-icon.icon-warning{background:#fff2c7;color:#c75b00}.system-modal-icon.icon-danger{background:#ffebeb;color:#c62828}.system-modal-icon.icon-question{background:#e8f2ff;color:#1769aa}
.system-modal-title{margin:0 0 8px;color:#153c66;font-size:17px;font-weight:800}
.system-modal-text{margin:0 0 20px;color:#687b8d;font-size:13px;line-height:1.55;overflow-wrap:anywhere}
.system-modal-actions{display:flex;justify-content:center;gap:10px}
.system-btn{min-width:100px;min-height:39px;border:0;border-radius:10px;padding:9px 16px;font:inherit;font-size:12px;font-weight:800;cursor:pointer}
.system-btn-confirm{background:#1769aa;color:#fff;box-shadow:0 5px 12px rgba(23,105,170,.2)}
.system-btn-cancel{background:#eef2f6;color:#43566a}
@keyframes registryModalIn{from{opacity:0;transform:translateY(7px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
</style>
<script>
const RegistrySystemModal = (() => {
 const overlay=document.getElementById('registrySystemModal');
 const iconBox=document.getElementById('registrySystemModalIcon');
 const titleEl=document.getElementById('registrySystemModalTitle');
 const textEl=document.getElementById('registrySystemModalText');
 const actions=document.getElementById('registrySystemModalActions');
 const icons={
  success:'<svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
  warning:'<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
  danger:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>'
 };
 function close(){overlay.style.display='none'}
 function open({icon='warning',title='Notice',text='',confirmText='OK',onConfirm=()=>{}}){
  iconBox.className='system-modal-icon icon-'+icon;iconBox.innerHTML=icons[icon]||icons.warning;
  titleEl.textContent=title;textEl.textContent=text;actions.innerHTML='';
  const btn=document.createElement('button');btn.type='button';btn.className='system-btn system-btn-confirm';btn.textContent=confirmText;
  btn.addEventListener('click',()=>{close();onConfirm()});actions.appendChild(btn);overlay.style.display='flex';
 }
 overlay.addEventListener('click',e=>{if(e.target===overlay)close()});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
 return {open,close};
})();
document.addEventListener('DOMContentLoaded',()=>{
 const floors=<?=json_encode($floors,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>, rooms=<?=json_encode($rooms,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
 const facility=document.getElementById('facility_id'), floor=document.getElementById('floor_id'), room=document.getElementById('room_id'), type=document.getElementById('equipment_type'), modal=document.getElementById('addEquipmentModal');
 const search=document.getElementById('registrySearch'), typeFilter=document.getElementById('registryTypeFilter'), categoryFilter=document.getElementById('registryCategoryFilter');
 function fill(select,placeholder,items,key){select.innerHTML='';select.add(new Option(placeholder,''));items.forEach(x=>{const o=new Option(x.name+(x.code?' ('+x.code+')':''),String(x[key]??''));o.dataset.code=x.code||'';select.add(o)});select.disabled=items.length===0}
 function floorsChanged(){
   const facilityId=String(facility.value||'').trim();
   const matching=floors.filter(x=>String(x.facility_id||'').trim()===facilityId);
   fill(floor,matching.length?'Select floor':'No floors found for this facility',matching,'floor_id');
   fill(room,'Select floor first',[],'room_id');
   document.getElementById('floorHelp').textContent=matching.length ? matching.length+' floor(s) available.' : 'No floors linked to this facility. Check facility_floors.facility_id.';
   document.getElementById('roomHelp').textContent='Choose a floor first.';
   preview()
 }
 function roomsChanged(){
   const floorId=String(floor.value||'').trim();
   const matching=rooms.filter(x=>String(x.floor_id||'').trim()===floorId);
   fill(room,matching.length?'Select room':'No rooms found for this floor',matching,'room_id');
   document.getElementById('roomHelp').textContent=matching.length ? matching.length+' room(s) available.' : 'No rooms linked to this floor. Check facility_rooms.floor_id.';
   preview()
 }
 function preview(){let tc=type.selectedOptions[0]?.dataset.code||'TYPE',fc=facility.selectedOptions[0]?.dataset.code||'FAC',rc=room.selectedOptions[0]?.dataset.code||'ROOM';document.getElementById('previewId').value=tc+'-'+fc+'-'+rc+'-## (automatic)'}
 facility.addEventListener('change',floorsChanged);floor.addEventListener('change',roomsChanged);room.addEventListener('change',preview);type.addEventListener('change',preview);
 const equipmentForm=document.getElementById('equipmentForm');
 equipmentForm.addEventListener('submit',e=>{
   const requiredFields=[
     ['equipment_name','Equipment Name'],['equipment_type','Equipment Type'],
     ['equipment_category','Equipment Category'],['facility_id','Facility'],
     ['floor_id','Floor'],['room_id','Room'],['registration_date','Date of Registration']
   ];
   for(const [id,label] of requiredFields){
     const field=document.getElementById(id);
     if(!field.value.trim()){
       e.preventDefault();
       RegistrySystemModal.open({icon:'warning',title:'Missing required fields',text:'Please select or complete the '+label+' field before registering equipment.'});
       field.focus();
       return;
     }
   }
   const picture=document.getElementById('equipment_picture').files[0];
   if(picture){
     const allowed=['image/jpeg','image/png','image/webp'];
     if(!allowed.includes(picture.type)){
       e.preventDefault();
       RegistrySystemModal.open({icon:'warning',title:'Invalid image format',text:'Please upload a JPG, PNG, or WEBP image.'});
       return;
     }
     if(picture.size>5*1024*1024){
       e.preventDefault();
       RegistrySystemModal.open({icon:'warning',title:'Image too large',text:'The equipment picture must be 5 MB or smaller.'});
       return;
     }
   }
   const submitBtn=equipmentForm.querySelector('button[type="submit"]');
   submitBtn.disabled=true;submitBtn.textContent='Registering...';
 });
 let currentPage=1;
 const pageSizeSelect=document.getElementById('registryPageSize');
 const prevPageBtn=document.getElementById('registryPrevPage');
 const nextPageBtn=document.getElementById('registryNextPage');
 const pageNumbers=document.getElementById('registryPageNumbers');
 function renderPagination(totalPages){
   pageNumbers.innerHTML='';
   const addPage=(page,label=String(page),active=false)=>{
     const btn=document.createElement('button');btn.type='button';btn.className='registry-page-number'+(active?' active':'');btn.textContent=label;
     btn.setAttribute('aria-label','Page '+page);if(active)btn.setAttribute('aria-current','page');
     btn.addEventListener('click',()=>{currentPage=page;filterRows(false)});pageNumbers.appendChild(btn);
   };
   if(totalPages<=1){prevPageBtn.disabled=true;nextPageBtn.disabled=true;return;}
   let pages=[];
   if(totalPages<=7){for(let i=1;i<=totalPages;i++)pages.push(i)}
   else {
     pages=[1];
     const start=Math.max(2,currentPage-1),end=Math.min(totalPages-1,currentPage+1);
     if(start>2)pages.push('…');
     for(let i=start;i<=end;i++)pages.push(i);
     if(end<totalPages-1)pages.push('…');
     pages.push(totalPages);
   }
   pages.forEach(page=>{if(page==='…'){const span=document.createElement('span');span.className='registry-page-ellipsis';span.textContent='…';pageNumbers.appendChild(span)}else addPage(page,String(page),page===currentPage)});
   prevPageBtn.disabled=currentPage<=1;nextPageBtn.disabled=currentPage>=totalPages;
 }
 const facilityFilter=document.getElementById('registryFacilityFilter');
 function filterRows(resetPage=true){
   if(resetPage)currentPage=1;
   const q=search.value.toLowerCase().trim();
   const rows=Array.from(document.querySelectorAll('#equipmentTableBody tr'));
   const matched=rows.filter(r=>r.textContent.toLowerCase().includes(q)&&(!typeFilter.value||r.dataset.type===typeFilter.value)&&(!categoryFilter.value||r.dataset.category===categoryFilter.value)&&(!facilityFilter.value||r.dataset.facility===facilityFilter.value));
   const pageSize=parseInt(pageSizeSelect.value,10)||10;
   const totalPages=Math.max(1,Math.ceil(matched.length/pageSize));
   if(currentPage>totalPages)currentPage=totalPages;
   const start=(currentPage-1)*pageSize,end=start+pageSize;
   rows.forEach(r=>r.style.display='none');
   matched.forEach((r,index)=>{if(index>=start&&index<end)r.style.display=''});
   document.getElementById('registryNoResults').style.display=matched.length?'none':'block';
   document.getElementById('registryCount').textContent=matched.length===0?'0 items shown':'Showing '+(start+1)+'–'+Math.min(end,matched.length)+' of '+matched.length+' item'+(matched.length===1?'':'s');
   renderPagination(totalPages);
 }
 [search,typeFilter,categoryFilter,facilityFilter].forEach(el=>el.addEventListener(el===search?'input':'change',()=>filterRows(true)));
 pageSizeSelect.addEventListener('change',()=>filterRows(true));
 prevPageBtn.addEventListener('click',()=>{if(currentPage>1){currentPage--;filterRows(false)}});
 nextPageBtn.addEventListener('click',()=>{const rows=Array.from(document.querySelectorAll('#equipmentTableBody tr'));const q=search.value.toLowerCase().trim();const count=rows.filter(r=>r.textContent.toLowerCase().includes(q)&&(!typeFilter.value||r.dataset.type===typeFilter.value)&&(!categoryFilter.value||r.dataset.category===categoryFilter.value)&&(!facilityFilter.value||r.dataset.facility===facilityFilter.value)).length;const pages=Math.max(1,Math.ceil(count/(parseInt(pageSizeSelect.value,10)||10)));if(currentPage<pages){currentPage++;filterRows(false)}});
 document.getElementById('registryReset').addEventListener('click',()=>{search.value='';typeFilter.value='';categoryFilter.value='';facilityFilter.value='';pageSizeSelect.value='10';filterRows(true)});
 filterRows();
 function close(){modal.style.display='none';document.getElementById('equipmentForm').reset();floor.innerHTML='<option value="">Select facility first</option>';floor.disabled=true;room.innerHTML='<option value="">Select floor first</option>';room.disabled=true;document.getElementById('floorHelp').textContent='Choose a facility first.';document.getElementById('roomHelp').textContent='Choose a floor first.';preview()}
 document.getElementById('addEquipmentBtn').addEventListener('click',()=>{modal.style.display='flex';floorsChanged();preview()});
 document.getElementById('addEquipmentClose').addEventListener('click',close);document.getElementById('addEquipmentCancel').addEventListener('click',close);
 modal.addEventListener('click',e=>{if(e.target===modal)close()});document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
 const successMessage=<?=json_encode($success,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
 const serverError=<?=json_encode($error,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
 if(successMessage){RegistrySystemModal.open({icon:'success',title:'Equipment registered successfully',text:successMessage})}
 else if(serverError){RegistrySystemModal.open({icon:'danger',title:'Registration failed',text:serverError})}
});
</script>
<?php renderFoot(); ?>
</html>
