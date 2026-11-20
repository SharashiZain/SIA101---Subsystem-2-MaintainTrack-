<?php
declare(strict_types=1);

function mt_publisher_env(string $name): string
{
    static $fileValues = null;
    if ($fileValues === null) {
        $parsed = @parse_ini_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env', false, INI_SCANNER_RAW);
        $fileValues = is_array($parsed) ? $parsed : [];
    }
    $value = getenv($name);
    if ($value !== false && trim((string) $value) !== '') return trim((string) $value);
    return trim((string) ($fileValues[$name] ?? ''));
}

function mt_publisher_request(string $url, string $method, array $headers, ?array $body = null): array
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
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

function mt_publish_central_outbox(int $limit = 20): int
{
    $central = rtrim(mt_publisher_env('CENTRAL_API_BASE_URL'), '/');
    $centralToken = mt_publisher_env('MAINTAINTRACK_API_TOKEN');
    $supabase = rtrim(mt_publisher_env('MAINTAINTRACK_SUPABASE_URL'), '/');
    $secret = mt_publisher_env('MAINTAINTRACK_SUPABASE_SECRET_KEY');
    $centralParts = parse_url($central);
    $supabaseParts = parse_url($supabase);
    $localHttp = strtolower(mt_publisher_env('LOCAL_STAGING_ALLOW_HTTP')) === 'true';
    $centralLocal = $localHttp && is_array($centralParts) && strtolower((string) ($centralParts['scheme'] ?? '')) === 'http' && in_array(strtolower((string) ($centralParts['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true);
    $supabaseLocal = $localHttp && is_array($supabaseParts) && strtolower((string) ($supabaseParts['scheme'] ?? '')) === 'http' && in_array(strtolower((string) ($supabaseParts['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true);
    if ((!str_starts_with($central, 'https://') && !$centralLocal) || $centralToken === '' || (!str_starts_with($supabase, 'https://') && !$supabaseLocal) || $secret === '') {
        throw new RuntimeException('Central or MaintainTrack HTTPS worker credentials are missing');
    }
    $sbHeaders = ['apikey: ' . $secret, 'Accept: application/json', 'Content-Type: application/json'];
    if (!str_starts_with($secret, 'sb_secret_')) $sbHeaders[] = 'Authorization: Bearer ' . $secret;
    $query = http_build_query(['select' => 'event_id,payload,attempts', 'delivered_at' => 'is.null',
        'or' => '(next_attempt_at.is.null,next_attempt_at.lte.' . gmdate('c') . ')',
        'order' => 'created_at.asc', 'limit' => (string) max(1, min(50, $limit))], '', '&', PHP_QUERY_RFC3986);
    $rows = mt_publisher_request($supabase . '/rest/v1/integration_outbox?' . $query, 'GET', $sbHeaders);
    $published = 0;
    foreach ($rows as $row) {
        $id = (string) ($row['event_id'] ?? '');
        $event = $row['payload'] ?? null;
        try {
            if ($id === '' || !is_array($event)) throw new RuntimeException('Malformed local outbox row');
            mt_publisher_request($central . '/api/v1/events', 'POST', [
                'Authorization: Bearer ' . $centralToken, 'Accept: application/json', 'Content-Type: application/json',
            ], $event);
            mt_publisher_request($supabase . '/rest/v1/integration_outbox?event_id=eq.' . rawurlencode($id), 'PATCH', $sbHeaders, [
                'delivered_at' => gmdate('c'), 'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                'last_error' => null, 'next_attempt_at' => null,
            ]);
            $published++;
        } catch (Throwable $error) {
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            $delay = min(3600, 15 * (2 ** min(7, max(0, $attempts - 1))));
            if ($id !== '') {
                mt_publisher_request($supabase . '/rest/v1/integration_outbox?event_id=eq.' . rawurlencode($id), 'PATCH', $sbHeaders, [
                    'attempts' => $attempts, 'last_error' => substr($error->getMessage(), 0, 400),
                    'next_attempt_at' => gmdate('c', time() + $delay),
                ]);
            }
            error_log('MaintainTrack Central outbox publish failed: ' . $error->getMessage());
            break;
        }
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
