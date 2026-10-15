<?php
/* ------------------------------------------------------------
   ROLE-SEPARATED SESSIONS
   ------------------------------------------------------------
   Problem this solves: one browser shared ONE PHP session for every
   role. Logging in as Secretary in another tab overwrote the Admin
   session, so Admin suddenly "became" Secretary (also on Settings).

   Now every role has its own session cookie:
       MTSESS_admin, MTSESS_secretary, MTSESS_regular, MTSESS_maintenance
   Which one a page uses is decided by WHERE the page lives
   (Admin/ -> admin, Secretary/ -> secretary, ...). The shared
   Components/Settings.php and Logout.php get it from ?role=...
   Logging in as one role can no longer change another role.

   Include this file (require_once) BEFORE anything calls session_start().
   ------------------------------------------------------------ */

if (!defined('MT_ROLES')) {
    define('MT_ROLES', ['admin', 'secretary', 'regular', 'maintenance']);
}

if (!function_exists('mtNormalizeRole')) {
    function mtNormalizeRole($role): string
    {
        $role = strtolower(trim((string) $role));
        if ($role === 'user') {
            $role = 'regular';
        }
        return in_array($role, MT_ROLES, true) ? $role : '';
    }
}

if (!function_exists('mtSessionNameFor')) {
    function mtSessionNameFor(string $role): string
    {
        return 'MTSESS_' . $role;
    }
}

/* Which role's session does THIS request belong to? ('' = none/shared) */
if (!function_exists('mtRoleContext')) {
    function mtRoleContext(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $folders = [
            '/Admin/'       => 'admin',
            '/Secretary/'   => 'secretary',
            '/Regular/'     => 'regular',
            '/Maintenance/' => 'maintenance',
        ];
        foreach ($folders as $marker => $role) {
            if (strpos($scriptName, $marker) !== false) {
                return $role;
            }
        }

        /* Shared pages that act for a role: Settings.php / Logout.php (?role=admin) */
        $file = strtolower(basename($scriptName));
        if ($file === 'settings.php' || $file === 'logout.php') {
            return mtNormalizeRole($_GET['role'] ?? '');
        }
        return '';
    }
}

/* Which roles are currently logged in (read-only look at their sessions)? */
if (!function_exists('mtLoggedInRoles')) {
    function mtLoggedInRoles(): array
    {
        $found = [];
        foreach (MT_ROLES as $role) {
            $cookieName = mtSessionNameFor($role);
            $sid = (string) ($_COOKIE[$cookieName] ?? '');
            if ($sid === '' || !preg_match('/^[A-Za-z0-9,-]{16,128}$/', $sid)) {
                continue;
            }

            /* A session may already be open for the current request: close it first. */
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_name($cookieName);
            session_id($sid);
            session_start(['read_and_close' => true, 'use_cookies' => false]);
            if (mtNormalizeRole($_SESSION['role'] ?? '') === $role) {
                $found[] = $role;
            }
        }
        return $found;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $mtContext = mtRoleContext();
    if ($mtContext !== '') {
        session_name(mtSessionNameFor($mtContext));
    }
    session_start();
}
