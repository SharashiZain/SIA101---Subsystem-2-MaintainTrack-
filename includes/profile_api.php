<?php
/* =========================================================
   PROFILE API — shared server helpers for the Admin, Regular and
   Maintenance profile pages (Secretary keeps its own copy in
   pages/secretary_profile.php).

   Pages call profileHandleRequest() once near the top. It answers
   fetch() calls made by ProfileEdit.js:
     POST ?action=update_profile    {fullName, username, email}
     POST ?action=change_password   {current, newPass, confirm}
   The account is always the one in the logged-in session.
========================================================= */

if (!function_exists('profileRest')) {
    /* Supabase REST / Auth call. Server-side only; the key never reaches the browser. */
    function profileRest(string $path, string $method = 'GET', ?array $body = null, array $extraHeaders = [], ?string $apiKey = null): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'data' => null];
        }

        $headers = array_merge([
            'apikey: ' . ($apiKey ?? SUPABASE_SECRET_KEY),
            'Accept: application/json',
        ], $extraHeaders);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init(rtrim(SUPABASE_URL, '/') . $path);
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => $status, 'data' => null];
        }

        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'data'   => json_decode($response, true),
        ];
    }
}

if (!function_exists('profileCurrentUser')) {
    /* The signed-in user's row, looked up by the session's user_id only. */
    function profileCurrentUser(bool $withSecrets = false): ?array
    {
        $sessionUser = $_SESSION['user_id'] ?? null;
        if (!is_string($sessionUser) || !preg_match('/^[0-9a-f-]{36}$/i', $sessionUser)) {
            return null;
        }

        $columns = 'user_id,full_name,username,email,role,status,auth_user_id'
                 . ($withSecrets ? ',password_hash' : '');

        $res = profileRest('/rest/v1/users?select=' . $columns . '&user_id=eq.' . rawurlencode($sessionUser) . '&limit=1');
        if ($res['ok'] && is_array($res['data']) && !empty($res['data'][0])) {
            return $res['data'][0];
        }
        return null;
    }
}

if (!function_exists('profileVerifyPassword')) {
    /* Checks the stored bcrypt hash (or an old plain-text value), then Supabase Auth. */
    function profileVerifyPassword(array $user, string $password): bool
    {
        $hash = (string) ($user['password_hash'] ?? '');

        if ($hash !== '') {
            if (preg_match('/^\$2[aby]\$/', $hash)) {
                if (password_verify($password, $hash)) return true;
            } elseif (hash_equals($hash, $password)) {
                return true;
            }
        }

        if (!empty($user['auth_user_id']) && !empty($user['email'])) {
            $res = profileRest('/auth/v1/token?grant_type=password', 'POST', [
                'email'    => $user['email'],
                'password' => $password,
            ], [], SUPABASE_ANON_KEY);
            if ($res['ok']) return true;
        }

        return false;
    }
}

if (!function_exists('profileJson')) {
    function profileJson(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('profileHandleRequest')) {
    /* Answers the page's own fetch() calls. Does nothing for normal page loads. */
    function profileHandleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_GET['action'])) {
            return;
        }

        $action = (string) $_GET['action'];
        $in     = json_decode(file_get_contents('php://input'), true) ?: [];
        $user   = profileCurrentUser(true);

        if (!$user) {
            profileJson(401, ['ok' => false, 'error' => 'Could not find your account. Please sign in again.']);
        }

        /* ---------- Update profile ---------- */
        if ($action === 'update_profile') {
            $fullName = trim((string) ($in['fullName'] ?? ''));
            $username = trim((string) ($in['username'] ?? ''));
            $email    = trim((string) ($in['email'] ?? ''));

            if ($fullName === '' || $email === '') {
                profileJson(400, ['ok' => false, 'error' => 'Full name and email are required.']);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                profileJson(400, ['ok' => false, 'error' => 'Please enter a valid email address.']);
            }
            if (!preg_match('/^[A-Za-z0-9._-]{3,}$/', $username)) {
                profileJson(400, ['ok' => false, 'error' => 'Username must be at least 3 characters (letters, numbers, dot, dash or underscore).']);
            }

            $res = profileRest(
                '/rest/v1/users?user_id=eq.' . rawurlencode($user['user_id']),
                'PATCH',
                ['full_name' => $fullName, 'username' => $username, 'email' => $email, 'updated_at' => gmdate('c')],
                ['Prefer: return=representation']
            );

            if (!$res['ok']) {
                if ($res['status'] === 409) {
                    profileJson(409, ['ok' => false, 'error' => 'That username or email is already used by another account.']);
                }
                profileJson(500, ['ok' => false, 'error' => 'Could not save your profile. Please try again.']);
            }

            /* Keep the sign-in email in step when the account uses Supabase Auth */
            $warning = null;
            if (!empty($user['auth_user_id']) && strcasecmp($email, (string) $user['email']) !== 0) {
                $authRes = profileRest('/auth/v1/admin/users/' . rawurlencode($user['auth_user_id']), 'PUT', [
                    'email'         => $email,
                    'email_confirm' => true,
                ]);
                if (!$authRes['ok']) {
                    $warning = 'Profile saved, but your sign-in email could not be updated. Keep signing in with your previous email.';
                }
            }

            /* Keep the session name/email in step with the saved values */
            $_SESSION['full_name'] = $fullName;
            $_SESSION['username']  = $username;
            $_SESSION['email']     = $email;

            profileJson(200, ['ok' => true, 'warning' => $warning]);
        }

        /* ---------- Change password ---------- */
        if ($action === 'change_password') {
            $current = (string) ($in['current'] ?? '');
            $newPass = (string) ($in['newPass'] ?? '');
            $confirm = (string) ($in['confirm'] ?? '');

            if ($current === '' || $newPass === '' || $confirm === '') {
                profileJson(400, ['ok' => false, 'error' => 'Please fill in all password fields.']);
            }
            if (strlen($newPass) < 6) {
                profileJson(400, ['ok' => false, 'error' => 'New password must be at least 6 characters.']);
            }
            if ($newPass !== $confirm) {
                profileJson(400, ['ok' => false, 'error' => 'New passwords do not match.']);
            }
            if ($newPass === $current) {
                profileJson(400, ['ok' => false, 'error' => 'New password must be different from the current one.']);
            }
            if (!profileVerifyPassword($user, $current)) {
                profileJson(403, ['ok' => false, 'error' => 'Your current password is incorrect.']);
            }

            /* Supabase Auth first (if the account uses it), then the users table */
            $warning = null;
            if (!empty($user['auth_user_id'])) {
                $authRes = profileRest('/auth/v1/admin/users/' . rawurlencode($user['auth_user_id']), 'PUT', ['password' => $newPass]);
                if (!$authRes['ok']) {
                    $warning = 'Password saved to your account record, but the sign-in service did not accept the change.';
                }
            }

            $res = profileRest(
                '/rest/v1/users?user_id=eq.' . rawurlencode($user['user_id']),
                'PATCH',
                ['password_hash' => password_hash($newPass, PASSWORD_DEFAULT), 'updated_at' => gmdate('c')],
                ['Prefer: return=representation']
            );

            if (!$res['ok']) {
                profileJson(500, ['ok' => false, 'error' => 'Could not update your password. Please try again.']);
            }

            profileJson(200, ['ok' => true, 'warning' => $warning]);
        }

        profileJson(400, ['ok' => false, 'error' => 'Unknown action.']);
    }
}