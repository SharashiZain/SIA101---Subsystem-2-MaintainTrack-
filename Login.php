<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/Config/supabase.php';

/**
 * MaintainTrack login using Supabase Auth.
 * Expected config: SUPABASE_URL, SUPABASE_ANON_KEY, SUPABASE_SECRET_KEY.
 * Secret key is used only by this server-side PHP file for profile lookup/linking.
 */

function loginRequest(
    string $url,
    string $method,
    array $headers,
    ?array $body = null
): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL is not enabled.'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Could not initialize request.'];
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ];

    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            curl_close($ch);
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Could not encode request.'];
        }
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $curlError ?: 'Request failed.'];
    }

    $decoded = json_decode($raw, true);
    $ok = $status >= 200 && $status < 300;

    $error = null;
    if (!$ok) {
        $error = is_array($decoded)
            ? (string) ($decoded['msg'] ?? $decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? 'Supabase request failed.')
            : ($raw !== '' ? $raw : 'Supabase request failed.');
    }

    return ['ok' => $ok, 'status' => $status, 'data' => $decoded, 'error' => $error];
}

function loginRedirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    loginRedirect('Index.php');
}

$loginValue = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($loginValue === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter your username/email and password.';
    loginRedirect('Index.php');
}

if (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY') || !defined('SUPABASE_SECRET_KEY')) {
    $_SESSION['login_error'] = 'Login service is not configured. Please contact the administrator.';
    loginRedirect('Index.php');
}

$baseUrl = rtrim(SUPABASE_URL, '/');
$anonKey = SUPABASE_ANON_KEY;
$secretKey = SUPABASE_SECRET_KEY;

/*
 * Resolve a username to its email. If the user typed an email, use it directly.
 * The secret key is only used in server-side PHP; never send it to browser JavaScript.
 */
$email = $loginValue;

if (!filter_var($loginValue, FILTER_VALIDATE_EMAIL)) {
    $lookupUrl = $baseUrl . '/rest/v1/users?username=eq.' . rawurlencode($loginValue)
        . '&select=email&limit=1';

    $lookup = loginRequest($lookupUrl, 'GET', [
        'apikey: ' . $secretKey,
        'Authorization: Bearer ' . $secretKey,
        'Accept: application/json',
    ]);

    if (!$lookup['ok'] || !is_array($lookup['data']) || empty($lookup['data'][0]['email'])) {
        $_SESSION['login_error'] = 'Invalid username or password.';
        loginRedirect('Index.php');
    }

    $email = (string) $lookup['data'][0]['email'];
}

/* Authenticate the credentials with Supabase Auth. */
$auth = loginRequest($baseUrl . '/auth/v1/token?grant_type=password', 'POST', [
    'apikey: ' . $anonKey,
    'Content-Type: application/json',
    'Accept: application/json',
], [
    'email' => $email,
    'password' => $password,
]);

if (!$auth['ok'] || !is_array($auth['data']) || empty($auth['data']['access_token']) || empty($auth['data']['user']['id'])) {
    $_SESSION['login_error'] = 'Invalid username/email or password.';
    loginRedirect('Index.php');
}

$authUserId = (string) $auth['data']['user']['id'];
$authenticatedEmail = (string) ($auth['data']['user']['email'] ?? $email);

/* Fetch the application's profile from public.users. */
$profileUrl = $baseUrl . '/rest/v1/users?email=eq.' . rawurlencode($authenticatedEmail)
    . '&select=user_id,auth_user_id,display_id,full_name,username,email,role,status&limit=1';

$profileResult = loginRequest($profileUrl, 'GET', [
    'apikey: ' . $secretKey,
    'Authorization: Bearer ' . $secretKey,
    'Accept: application/json',
]);

if (!$profileResult['ok'] || !is_array($profileResult['data']) || empty($profileResult['data'][0])) {
    $_SESSION['login_error'] = 'Your login succeeded, but your MaintainTrack profile was not found. Contact the administrator.';
    loginRedirect('Index.php');
}

$profile = $profileResult['data'][0];

/* If the profile is already linked to another Auth account, refuse access. */
$linkedAuthId = trim((string) ($profile['auth_user_id'] ?? ''));
if ($linkedAuthId !== '' && $linkedAuthId !== $authUserId) {
    $_SESSION['login_error'] = 'This profile is linked to a different login account. Contact the administrator.';
    loginRedirect('Index.php');
}

/* Do not allow inactive profiles to sign in. */
if (strcasecmp((string) ($profile['status'] ?? ''), 'Active') !== 0) {
    $_SESSION['login_error'] = 'Your account is inactive. Please contact the administrator.';
    loginRedirect('Index.php');
}

$role = strtolower((string) ($profile['role'] ?? ''));
$dashboardMap = [
    'admin' => 'pages/admin/admin_dashboard.php',
    'maintenance' => 'pages/maintenance/maintenance_dashboard.php',
    'regular' => 'pages/regular/regular_dashboard.php',
    'secretary' => 'pages/secretary/secretary_dashboard.php',
];

if (!isset($dashboardMap[$role])) {
    $_SESSION['login_error'] = 'Your account role is not configured correctly. Contact the administrator.';
    loginRedirect('Index.php');
}

/* Link the database profile to the verified Supabase Auth UID if not linked yet. */
if ($linkedAuthId === '') {
    $patchUrl = $baseUrl . '/rest/v1/users?user_id=eq.' . rawurlencode((string) $profile['user_id']);
    $patch = loginRequest($patchUrl, 'PATCH', [
        'apikey: ' . $secretKey,
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=minimal',
    ], [
        'auth_user_id' => $authUserId,
    ]);

    if (!$patch['ok']) {
        $_SESSION['login_error'] = 'Your profile could not be linked to Supabase Auth. Contact the administrator.';
        loginRedirect('Index.php');
    }
}

/* Clear old session data and establish the authenticated PHP session. */
require_once __DIR__ . '/includes/session.php';

/* Drop the temporary login-form session, then open THIS role's own session so
   logging in here never overwrites a different role that is already logged in. */
session_unset();
session_destroy();
session_name(mtSessionNameFor($role));
session_id(bin2hex(random_bytes(32)));
session_start();

$_SESSION['user'] = (string) ($profile['username'] ?? $authenticatedEmail);
$_SESSION['username'] = (string) ($profile['username'] ?? '');
$_SESSION['user_id'] = (string) ($profile['user_id'] ?? '');
$_SESSION['auth_user_id'] = $authUserId;
$_SESSION['full_name'] = (string) ($profile['full_name'] ?? '');
$_SESSION['email'] = $authenticatedEmail;
$_SESSION['role'] = $role;
$_SESSION['supabase_access_token'] = (string) $auth['data']['access_token'];
$_SESSION['supabase_refresh_token'] = (string) ($auth['data']['refresh_token'] ?? '');
unset($_SESSION['login_error']);

loginRedirect($dashboardMap[$role]);