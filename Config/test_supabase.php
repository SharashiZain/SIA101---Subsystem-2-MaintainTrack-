<?php
// Temporary MaintainTrack diagnostic page.
// Save as: C:\xampp\htdocs\Sia\Config\test_supabase.php
// Remove this file after troubleshooting.

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>MaintainTrack Diagnostic</title>';
echo '<style>body{font:15px Arial,sans-serif;background:#f3f6fa;padding:24px;color:#203040}main{max-width:850px;margin:auto;background:white;padding:24px;border-radius:10px}pre{white-space:pre-wrap;overflow-wrap:anywhere}.ok{color:#16733a}.bad{color:#b42318}.box{padding:12px;background:#f4f7fa;border-radius:6px;margin:10px 0}</style>';
echo '</head><body><main><h1>MaintainTrack PHP Diagnostic</h1>';

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo '<div class="box bad"><strong>Fatal PHP error detected:</strong><pre>'
            . htmlspecialchars(print_r($error, true), ENT_QUOTES, 'UTF-8')
            . '</pre></div>';
    }
    echo '</main></body></html>';
});

echo '<div class="box ok">STEP 1: PHP is running and this file is being executed.</div>';
echo '<div class="box">PHP version: ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</div>';
echo '<div class="box">Current file: <code>' . htmlspecialchars(__FILE__, ENT_QUOTES, 'UTF-8') . '</code></div>';
echo '<div class="box">cURL extension: ' . (function_exists('curl_init') ? '<span class="ok">Enabled</span>' : '<span class="bad">Not enabled</span>') . '</div>';

$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'supabase.php';
echo '<div class="box">Config path: <code>' . htmlspecialchars($configPath, ENT_QUOTES, 'UTF-8') . '</code></div>';

if (!is_file($configPath)) {
    echo '<div class="box bad">STEP 2 FAILED: supabase.php was not found in this folder. Check the file location and filename.</div>';
    exit;
}

echo '<div class="box ok">STEP 2: supabase.php file exists.</div>';

try {
    require_once $configPath;
    echo '<div class="box ok">STEP 3: supabase.php loaded without a thrown exception.</div>';
} catch (Throwable $e) {
    echo '<div class="box bad"><strong>STEP 3 FAILED:</strong><pre>'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</pre></div>';
    exit;
}

$checks = [
    'SUPABASE_URL' => defined('SUPABASE_URL'),
    'SUPABASE_ANON_KEY' => defined('SUPABASE_ANON_KEY'),
    'SUPABASE_SECRET_KEY' => defined('SUPABASE_SECRET_KEY'),
];

foreach ($checks as $name => $exists) {
    echo '<div class="box">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ': '
        . ($exists ? '<span class="ok">Defined</span>' : '<span class="bad">MISSING</span>')
        . '</div>';
}

if (defined('SUPABASE_URL')) {
    echo '<div class="box">Supabase URL: <code>'
        . htmlspecialchars((string) SUPABASE_URL, ENT_QUOTES, 'UTF-8')
        . '</code></div>';
}

if (function_exists('curl_init') && defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    echo '<h2>Step 4: API connectivity test</h2>';
    $url = rtrim((string) SUPABASE_URL, '/') . '/rest/v1/users?select=user_id&limit=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo '<div class="box">HTTP status: <strong>' . $status . '</strong></div>';
    if ($body === false || $curlError !== '') {
        echo '<div class="box bad">cURL error: <pre>' . htmlspecialchars($curlError, ENT_QUOTES, 'UTF-8') . '</pre></div>';
    } else {
        $decoded = json_decode($body, true);
        if ($status >= 200 && $status < 300) {
            echo '<div class="box ok">The API request succeeded. This query may still return zero rows if RLS hides them.</div>';
            echo '<div class="box">Returned response:<pre>' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $body, ENT_QUOTES, 'UTF-8') . '</pre></div>';
        } else {
            echo '<div class="box bad">The API request returned an error. Response:<pre>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</pre></div>';
        }
    }
}

echo '<p><strong>Security:</strong> This diagnostic does not print the secret key. Delete this file after troubleshooting.</p>';
