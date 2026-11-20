<?php
declare(strict_types=1);

function maintain_worker_env(string $key, string $default = ''): string
{
    static $fileValues = null;
    if ($fileValues === null) {
        $fileValues = [];
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($envPath)) {
            $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
            if (is_array($parsed)) $fileValues = $parsed;
        }
    }

    // Explicit process variables take precedence over the project .env file.
    $value = getenv($key);
    if ($value !== false && trim($value) !== '') return trim($value);
    return trim((string) ($fileValues[$key] ?? $default));
}

function maintain_worker_log(string $level, string $message, array $context = []): void
{
    $record = ['time' => gmdate('Y-m-d\TH:i:s\Z'), 'level' => $level, 'service' => 'maintaintrack-integration-worker', 'message' => $message] + $context;
    fwrite($level === 'error' ? STDERR : STDOUT, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL);
}

function maintain_validate_lifecycle_event(array $event): void
{
    $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    if (!preg_match($uuid, (string) ($event['event_id'] ?? ''))
        || !in_array($event['event_type'] ?? null, ['lifecycle.asset.upserted','lifecycle.asset.deleted'], true)
        || ($event['source'] ?? null) !== 'LifeCycleTrack'
        || ($event['destination'] ?? null) !== 'MaintainTrack'
        || ($event['record_type'] ?? null) !== 'lifecycle_asset'
        || ($event['version'] ?? null) !== '1.0'
        || trim((string) ($event['source_id'] ?? '')) === ''
        || strtotime((string) ($event['occurred_at'] ?? '')) === false
        || !is_array($event['payload'] ?? null)
        || (string) ($event['payload']['id'] ?? '') !== (string) $event['source_id']
        || !in_array($event['payload']['entity_type'] ?? null, ['equipment','facility'], true)) {
        throw new RuntimeException('Invalid LifecycleTrack event envelope');
    }
}

function maintain_curl_json(string $url, string $method, array $body, array $headers, int $timeout): mixed
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json','Content-Type: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException($response === false ? 'HTTP request failed: ' . $error : 'Remote endpoint returned HTTP ' . $status);
    }
    $decoded = json_decode($response, true);
    if ($decoded === null && trim($response) !== 'null') throw new RuntimeException('Remote endpoint returned invalid JSON');
    return $decoded;
}

function maintain_central_request(string $path, array $body): array
{
    $base = rtrim(maintain_worker_env('CENTRAL_API_BASE_URL'), '/');
    $token = maintain_worker_env('MAINTAINTRACK_INBOX_TOKEN');
    $parts = parse_url($base);
    $localHttp = strtolower(maintain_worker_env('LOCAL_STAGING_ALLOW_HTTP', 'false')) === 'true' && is_array($parts)
        && strtolower((string) ($parts['scheme'] ?? '')) === 'http'
        && in_array(strtolower((string) ($parts['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true);
    if ((!str_starts_with($base, 'https://') && !$localHttp) || $token === '') throw new RuntimeException('Central inbox HTTPS URL/token is not configured');
    return maintain_curl_json($base . $path, 'POST', $body, ['Authorization: Bearer ' . $token], 15);
}

function maintain_supabase_apply(array $event): string
{
    $url = rtrim(maintain_worker_env('MAINTAINTRACK_SUPABASE_URL'), '/');
    $key = maintain_worker_env('MAINTAINTRACK_SUPABASE_SECRET_KEY');
    $parts = parse_url($url);
    $localHttp = strtolower(maintain_worker_env('LOCAL_STAGING_ALLOW_HTTP', 'false')) === 'true' && is_array($parts)
        && strtolower((string) ($parts['scheme'] ?? '')) === 'http'
        && in_array(strtolower((string) ($parts['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true);
    if ((!str_starts_with($url, 'https://') && !$localHttp) || $key === '') throw new RuntimeException('MaintainTrack Supabase worker credentials are not configured');
    $headers = ['apikey: ' . $key];
    if (!str_starts_with($key, 'sb_secret_')) $headers[] = 'Authorization: Bearer ' . $key;
    $result = maintain_curl_json($url . '/rest/v1/rpc/maintain_apply_lifecycle_event', 'POST', ['p_event' => $event], $headers, 20);
    if (!in_array($result, ['applied','duplicate','stale'], true)) throw new RuntimeException('MaintainTrack RPC returned an unexpected result');
    return $result;
}

function maintain_worker_once(int $count = 10): int
{
    $batch = maintain_central_request('/api/v1/inbox/MaintainTrack/read', ['count' => max(1,min(5,$count)), 'visibility_seconds' => 600]);
    $messages = $batch['messages'] ?? null;
    if (!is_array($messages)) throw new RuntimeException('Central inbox response has no message list');
    $acked = 0;
    foreach ($messages as $item) {
        $messageId = (int) ($item['msg_id'] ?? 0);
        $event = $item['event'] ?? null;
        if ($messageId < 1 || !is_array($event)) {
            maintain_worker_log('error','Malformed inbox message; leaving it unacknowledged',['msg_id'=>$messageId]);
            continue;
        }
        try {
            maintain_validate_lifecycle_event($event);
            $result = maintain_supabase_apply($event);
            $ack = maintain_central_request('/api/v1/inbox/MaintainTrack/ack',['msg_id'=>$messageId]);
            if (($ack['acknowledged'] ?? false) !== true) throw new RuntimeException('Central did not confirm inbox acknowledgement');
            maintain_worker_log('info','LifecycleTrack event processed',['event_id'=>$event['event_id'],'result'=>$result,'msg_id'=>$messageId]);
            $acked++;
        } catch (Throwable $error) {
            maintain_worker_log('error','Event processing failed; message left for visibility-timeout retry',[
                'event_id' => is_array($event) ? ($event['event_id'] ?? null) : null,
                'msg_id' => $messageId,
                'error' => $error->getMessage(),
            ]);
        }
    }
    return $acked;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    if (!function_exists('curl_init')) {
        fwrite(STDERR, "PHP cURL extension is required.\n");
        exit(1);
    }

    $running = true;
    if (function_exists('pcntl_async_signals') && defined('SIGTERM')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static function () use (&$running): void { $running = false; });
        pcntl_signal(SIGINT, static function () use (&$running): void { $running = false; });
    }
    $once = in_array('--once', $argv, true);
    $countArg = array_values(array_filter(array_slice($argv, 1), static fn($arg) => $arg !== '--once'))[0] ?? '10';
    $count = max(1,min(5,(int)$countArg));
    maintain_worker_log('info','Worker started',['mode'=>$once?'once':'continuous']);
    while ($running) {
        try {
            maintain_worker_once($count);
        } catch (Throwable $error) {
            maintain_worker_log('error','Inbox poll failed',['error'=>$error->getMessage()]);
        }
        if ($once) break;
        sleep(5);
    }
    maintain_worker_log('info','Worker stopped');
}
