<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/environment.php';

function mt_publisher_request(string $url, string $method, array $headers, ?array $body = null): mixed
{
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required');
    $handle = curl_init($url);
    $options = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($handle, $options);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException($response === false ? $error : 'HTTP ' . $status);
    }
    return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
}

function mt_publish_central_outbox(int $limit = 20): int
{
    $central = rtrim(maintaintrack_env('CENTRAL_API_BASE_URL'), '/');
    $centralToken = maintaintrack_env('MAINTAINTRACK_API_TOKEN');
    $supabase = rtrim(maintaintrack_env('MAINTAINTRACK_SUPABASE_URL'), '/');
    $secret = maintaintrack_env('MAINTAINTRACK_SUPABASE_SECRET_KEY');
    $centralParts = parse_url($central);
    $supabaseParts = parse_url($supabase);
    $localHttp = strtolower(maintaintrack_env('LOCAL_STAGING_ALLOW_HTTP')) === 'true';
    $centralLocal = $localHttp && is_array($centralParts) && strtolower((string) ($centralParts['scheme'] ?? '')) === 'http' && in_array(strtolower((string) ($centralParts['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true);
    $supabaseLocal = $localHttp && is_array($supabaseParts) && strtolower((string) ($supabaseParts['scheme'] ?? '')) === 'http' && in_array(strtolower((string) ($supabaseParts['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true);
    if ((!str_starts_with($central, 'https://') && !$centralLocal) || $centralToken === '' || (!str_starts_with($supabase, 'https://') && !$supabaseLocal) || $secret === '') {
        throw new RuntimeException('Central or MaintainTrack HTTPS worker credentials are missing');
    }
    $sbHeaders = ['apikey: ' . $secret, 'Accept: application/json', 'Content-Type: application/json'];
    if (!str_starts_with($secret, 'sb_secret_')) $sbHeaders[] = 'Authorization: Bearer ' . $secret;
    $workerId = vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4));
    $rpc = $supabase . '/rest/v1/rpc/';
    $published = 0;
    for ($index=0; $index<max(1,min(50,$limit)); $index++) {
        $claimed = mt_publisher_request($rpc . 'maintain_claim_outbox', 'POST', $sbHeaders, [
            'p_worker'=>$workerId, 'p_limit'=>1,
        ]);
        if (!is_array($claimed)) throw new RuntimeException('Unexpected maintain outbox claim result');
        if ($claimed === []) break;
        $row = $claimed[0];
        $id = (string) ($row['event_id'] ?? '');
        $event = $row['payload'] ?? null;
        $failure = null;
        try {
            if ($id === '' || !is_array($event)) throw new RuntimeException('Malformed local outbox row');
            $result = mt_publisher_request($central . '/api/v1/events', 'POST', [
                'Authorization: Bearer ' . $centralToken, 'Accept: application/json', 'Content-Type: application/json',
            ], $event);
            if (!is_array($result) || ($result['accepted']??false)!==true) throw new RuntimeException('Central did not confirm event acceptance');
        } catch (Throwable $error) {
            $failure = substr($error->getMessage(),0,400);
            error_log('MaintainTrack Central outbox publish failed: ' . $failure);
        }
        $saved = mt_publisher_request($rpc . 'maintain_finish_outbox','POST',$sbHeaders,[
            'p_worker'=>$workerId,'p_event'=>$id,'p_error'=>$failure,
        ]);
        if ($saved !== true) throw new RuntimeException('MaintainTrack outbox lease could not be completed');
        if ($failure !== null) break;
        $published++;
    }
    return $published;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $count = mt_publish_central_outbox(isset($argv[1]) ? (int) $argv[1] : 20);
        fwrite(STDOUT, 'Published ' . $count . " MaintainTrack event(s).\n");
    } catch (Throwable $error) {
        error_log('MaintainTrack Central publisher failed: ' . $error->getMessage());
        fwrite(STDERR, "MaintainTrack publisher failed. Check the PHP error log.\n");
        exit(1);
    }
}
