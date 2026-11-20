<?php
declare(strict_types=1);
if(session_status()===PHP_SESSION_NONE) session_name('MTSESS_admin');
require_once __DIR__.'/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['error'=>'method_not_allowed']);exit;}
if(($_SESSION['role']??'')!=='admin'){http_response_code(401);echo json_encode(['error'=>'admin_auth_required']);exit;}
require_once __DIR__.'/../../includes/supabase_client.php';
function mtIntegrationCount(string $table): int{
    $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.rawurlencode($table).'?select=event_id&limit=1000';
    $key=SUPABASE_SECRET_KEY;$headers=['apikey: '.$key,'Accept: application/json','Prefer: count=exact'];
    if(!str_starts_with($key,'sb_secret_'))$headers[]='Authorization: Bearer '.$key;
    $h=curl_init($url);curl_setopt_array($h,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);$error=curl_error($h);curl_close($h);
    if($raw===false||$status<200||$status>=300)throw new RuntimeException($error!==''?$error:'Supabase HTTP '.$status);
    $rows=json_decode((string)$raw,true);return is_array($rows)?count($rows):0;
}
try{
    $outbox=mtIntegrationCount('integration_outbox');
    $processed=mtIntegrationCount('integration_processed_events');
    http_response_code(200);echo json_encode(['data'=>['service'=>'MaintainTrack integration','status'=>'online','outbox_rows_sampled'=>$outbox,'processed_remote_events_sampled'=>$processed],'meta'=>['checked_at'=>gmdate('c'),'sample_limit'=>1000]],JSON_UNESCAPED_SLASHES);
}catch(Throwable $error){error_log('MaintainTrack sync status failed: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>'integration_status_unavailable','meta'=>['checked_at'=>gmdate('c')]]);}
