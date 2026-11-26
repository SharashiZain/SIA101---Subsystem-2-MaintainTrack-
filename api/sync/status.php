<?php
declare(strict_types=1);
if(session_status()===PHP_SESSION_NONE) session_name('MTSESS_admin');
require_once __DIR__.'/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['error'=>'method_not_allowed']);exit;}
if(($_SESSION['role']??'')!=='admin'){http_response_code(401);echo json_encode(['error'=>'admin_auth_required']);exit;}
require_once __DIR__.'/../../includes/supabase_client.php';
try{
    $status=supabaseRequest('/rest/v1/rpc/maintain_integration_status','POST',['p_status'=>true],SUPABASE_SECRET_KEY);
    if (!$status['success'] || !is_array($status['data'])) throw new RuntimeException('Status RPC unavailable');
    http_response_code(200);
    echo json_encode(['data'=>['service'=>'MaintainTrack integration','status'=>'online']+$status['data'],'meta'=>['checked_at'=>gmdate('c')]],JSON_UNESCAPED_SLASHES);
}catch(Throwable $error){error_log('MaintainTrack sync status failed: '.$error->getMessage());http_response_code(503);echo json_encode(['error'=>'integration_status_unavailable','meta'=>['checked_at'=>gmdate('c')]]);}
