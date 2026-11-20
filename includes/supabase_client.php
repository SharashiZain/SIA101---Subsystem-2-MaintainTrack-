<?php
declare(strict_types=1);

/**
 * Supabase Configuration
 * Project: MaintainTrack / SIA
 * Region: Singapore
 */

$maintainTrackEnvValues = [];
$maintainTrackEnvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_readable($maintainTrackEnvPath)) {
    $parsedMaintainTrackEnv = parse_ini_file($maintainTrackEnvPath, false, INI_SCANNER_RAW);
    if (is_array($parsedMaintainTrackEnv)) $maintainTrackEnvValues = $parsedMaintainTrackEnv;
}

$maintainTrackConfigValue = static function (string $name, string $default = '') use (&$maintainTrackEnvValues): string {
    $processValue = getenv($name);
    if ($processValue !== false && trim($processValue) !== '') return trim($processValue);
    return trim((string) ($maintainTrackEnvValues[$name] ?? $default));
};

define('SUPABASE_URL', $maintainTrackConfigValue('MAINTAINTRACK_SUPABASE_URL', 'https://etlpwxatuklwiesohoeo.supabase.co'));

define(
    'SUPABASE_ANON_KEY',
    $maintainTrackConfigValue('MAINTAINTRACK_SUPABASE_ANON_KEY', 'sb_publishable_WuzMZ48r7XY-0fw0qyIywA_qr1e-6lS')
);

define(
    'SUPABASE_SECRET_KEY',
    $maintainTrackConfigValue('MAINTAINTRACK_SUPABASE_SECRET_KEY')
);

define('SUPABASE_STORAGE_BUCKET', 'report-images');



/**
 * Send a request to the Supabase REST API.
 *
 * @param string      $endpoint    API endpoint, e.g. /rest/v1/maintenance_requests
 * @param string      $method      GET, POST, PATCH, DELETE
 * @param array|null  $data        JSON request body
 * @param string|null $accessToken Supabase user access token, when authenticated
 *
 * @return array{
 *     success: bool,
 *     status: int,
 *     data: mixed,
 *     error: string|null
 * }
 */
function supabaseRequest(
    string $endpoint,
    string $method = 'GET',
    ?array $data = null,
    ?string $accessToken = null
): array {
    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'status'  => 0,
            'data'    => null,
            'error'   => 'PHP cURL extension is not enabled.'
        ];
    }

    $method = strtoupper($method);

    $allowedMethods = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'];

    if (!in_array($method, $allowedMethods, true)) {
        return [
            'success' => false,
            'status'  => 0,
            'data'    => null,
            'error'   => 'Unsupported HTTP method.'
        ];
    }

    if (
        !str_starts_with($endpoint, '/') ||
        str_starts_with($endpoint, '//') ||
        str_contains($endpoint, "\r") ||
        str_contains($endpoint, "\n")
    ) {
        return [
            'success' => false,
            'status'  => 0,
            'data'    => null,
            'error'   => 'Invalid API endpoint.'
        ];
    }

    $url = rtrim(SUPABASE_URL, '/') . $endpoint;

    /*
     * The publishable key identifies the application.
     * A signed-in user's access token is used for Authorization.
     */
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . (
            $accessToken ?: SUPABASE_ANON_KEY
        ),
        'Accept: application/json'
    ];

    if ($data !== null) {
        $jsonData = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
        );

        if ($jsonData === false) {
            return [
                'success' => false,
                'status'  => 0,
                'data'    => null,
                'error'   => 'Failed to encode request data as JSON.'
            ];
        }

        $headers[] = 'Content-Type: application/json';
    }

    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ];

    if ($data !== null) {
        $curlOptions[CURLOPT_POSTFIELDS] = $jsonData;
    }

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            'success' => false,
            'status'  => 0,
            'data'    => null,
            'error'   => 'Unable to initialize cURL.'
        ];
    }

    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'status'  => $httpCode,
            'data'    => null,
            'error'   => $curlError !== ''
                ? $curlError
                : 'Unable to connect to Supabase.'
        ];
    }

    $decoded = json_decode($response, true);

    $success = $httpCode >= 200 && $httpCode < 300;

    $error = null;

    if (!$success) {
        if (is_array($decoded)) {
            $error = $decoded['message']
                ?? $decoded['error_description']
                ?? $decoded['error']
                ?? $decoded['hint']
                ?? $response;
        } else {
            $error = $response !== ''
                ? $response
                : 'Supabase returned an HTTP error.';
        }
    }

    return [
        'success' => $success,
        'status'  => $httpCode,
        'data'    => $decoded ?? $response,
        'error'   => is_string($error) ? $error : null
    ];
}
