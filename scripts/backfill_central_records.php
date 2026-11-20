<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function maintain_backfill_env(string $name): string
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($path)) foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$key,$value] = explode('=',$line,2); $key=trim($key); $value=trim($value);
            if (strlen($value)>=2 && (($value[0]==='"' && str_ends_with($value,'"')) || ($value[0]==="'" && str_ends_with($value,"'")))) $value=substr($value,1,-1);
            $values[$key]=$value;
        }
    }
    $process=getenv($name);
    return trim($process!==false && trim($process)!=='' ? $process : (string)($values[$name]??''));
}

function maintain_backfill_request(string $method,string $path,?array $body=null): array
{
    $base=rtrim(maintain_backfill_env('MAINTAINTRACK_SUPABASE_URL'),'/');
    $key=maintain_backfill_env('MAINTAINTRACK_SUPABASE_SECRET_KEY');
    $parts=parse_url($base);$localHttp=strtolower(maintain_backfill_env('LOCAL_STAGING_ALLOW_HTTP'))==='true'&&is_array($parts)&&strtolower((string)($parts['scheme']??''))==='http'&&in_array(strtolower((string)($parts['host']??'')),['127.0.0.1','localhost','::1'],true);
    if ((!str_starts_with($base,'https://')&&!$localHttp) || $key==='') throw new RuntimeException('MaintainTrack server-side Supabase credentials are missing');
    $headers=['apikey: '.$key,'Accept: application/json','Content-Type: application/json'];
    if (!str_starts_with($key,'sb_secret_')) $headers[]='Authorization: Bearer '.$key;
    $ch=curl_init($base.'/'.$path);
    $opts=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];
    if ($body!==null) $opts[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    curl_setopt_array($ch,$opts); $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $error=curl_error($ch); curl_close($ch);
    if ($raw===false) throw new RuntimeException('MaintainTrack Supabase request failed: '.$error);
    if ($status<200 || $status>=300) throw new RuntimeException('MaintainTrack Supabase returned HTTP '.$status.': '.substr((string)$raw,0,600));
    $decoded=json_decode($raw,true); if (json_last_error()!==JSON_ERROR_NONE) throw new RuntimeException('MaintainTrack Supabase returned invalid JSON');
    return is_array($decoded)?$decoded:['value'=>$decoded];
}

function maintain_backfill_uuid(string $name): string
{
    $hash=sha1(hex2bin('d38347c39dfc5ee7a91e4b7c50ce2d10').$name);
    $hash[12]='5'; $hash[16]=dechex((hexdec($hash[16])&3)|8);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(substr($hash,0,32),4));
}

function maintain_backfill_job(string $job,int $limit): int
{
    $defs=[
        'maintain_requests'=>['table'=>'maintenance_requests','id'=>'request_id','type'=>'maintenance.work_order.upserted','record'=>'maintenance_request'],
        'maintain_updates'=>['table'=>'maintenance_updates','id'=>'update_id','type'=>'maintenance.result.recorded','record'=>'maintenance_update'],
    ];
    if (!isset($defs[$job])) throw new InvalidArgumentException('Unknown backfill job');
    $d=$defs[$job];
    $checkpoint=maintain_backfill_request('GET','rest/v1/maintain_central_backfill_checkpoint?job_name=eq.'.rawurlencode($job).'&select=last_source_id,status&limit=1');
    if (!$checkpoint) maintain_backfill_request('POST','rest/v1/rpc/maintain_reset_central_backfill',['p_job_name'=>$job]);
    $cursor=$checkpoint[0]['last_source_id']??null;
    $query='rest/v1/'.rawurlencode($d['table']).'?select=*&order='.rawurlencode($d['id']).'.asc&limit='.$limit;
    if ($cursor!==null && $cursor!=='') $query.='&'.rawurlencode($d['id']).'=gt.'.rawurlencode((string)$cursor);
    $rows=maintain_backfill_request('GET',$query); $events=[];
    foreach ($rows as $row) {
        $id=(string)($row[$d['id']]??'');
        if ($id==='') throw new RuntimeException('A source row is missing its stable ID');
        if ($d['record']==='maintenance_request') {
            $fields=['display_id','facility_id','external_facility_id','external_facility_name','external_equipment_id','external_equipment_name','specific_area','equipment_id','category','equipment_category','status','approval_status','priority','date_reported','date_assigned','date_started','date_completed'];
            $payload=['id'=>$id]; foreach ($fields as $field) if (array_key_exists($field,$row)) $payload[$field]=$row[$field];
        } else {
            $payload=['id'=>$id]; foreach (['request_id','status','title','created_at'] as $field) if (array_key_exists($field,$row)) $payload[$field]=$row[$field];
            if (strtolower((string)($row['status']??''))==='completed' && array_key_exists('update_text',$row)) $payload['completion_outcome']=$row['update_text'];
        }
        $at=(string)($row['updated_at']??$row['created_at']??$row['date_completed']??$row['date_reported']??gmdate('c'));
        if (strtotime($at)===false) $at=gmdate('c');
        if ($d['record']==='maintenance_request') $payload['updated_at']=$at;
        $events[]=['source_id'=>$id,'event'=>[
            'event_id'=>maintain_backfill_uuid('MaintainTrack|'.$d['record'].'|'.$id.'|'.$at.'|'.(string)($GLOBALS['maintainBackfillRunId']??'')),
            'event_type'=>$d['type'],'source'=>'MaintainTrack','destination'=>null,'version'=>'1.0',
            'correlation_id'=>maintain_backfill_uuid('MaintainTrack|backfill|'.$job.'|'.$id),
            'occurred_at'=>$at,'source_id'=>$id,'record_type'=>$d['record'],'payload'=>$payload,
        ]];
    }
    $completed=count($rows)<$limit; $last=$rows?(string)end($rows)[$d['id']]:$cursor;
    maintain_backfill_request('POST','rest/v1/rpc/maintain_enqueue_central_backfill_batch',[
        'p_job_name'=>$job,'p_events'=>$events,'p_last_source_id'=>$last,'p_completed'=>$completed,
    ]);
    fwrite(STDOUT,sprintf("%s: scanned %d snapshot(s)%s.\n",$job,count($rows),$completed?' (complete)':''));
    return count($rows);
}

try {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required');
    $args=array_slice($argv,1); $reset=in_array('--reconcile',$args,true); $limit=100;
    $GLOBALS['maintainBackfillRunId']=$reset?bin2hex(random_bytes(16)):'';
    foreach($args as $arg) if(preg_match('/^--limit=(\d+)$/',$arg,$m)) $limit=max(1,min(500,(int)$m[1]));
    $jobs=['maintain_requests','maintain_updates'];
    if($reset) foreach($jobs as $job) maintain_backfill_request('POST','rest/v1/rpc/maintain_reset_central_backfill',['p_job_name'=>$job]);
    foreach($jobs as $job) do {$n=maintain_backfill_job($job,$limit);} while($n===$limit);
    fwrite(STDOUT,"MaintainTrack snapshot backfill enqueued. The scheduled publisher will deliver pending events to Central.\n");
} catch(Throwable $error) {
    error_log('MaintainTrack Central backfill failed: '.$error->getMessage());
    fwrite(STDERR,'MaintainTrack Central backfill failed: '.$error->getMessage()."\n");
    exit(1);
}
