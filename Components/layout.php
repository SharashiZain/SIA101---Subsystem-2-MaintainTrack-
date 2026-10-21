<?php
/* ------------------------------------------------------------
   SHARED GLOBAL LAYOUT HELPERS
   ------------------------------------------------------------ */

/* Make sure the (role-separated) session is always available */
require_once __DIR__ . '/../includes/session.php';

/** Escape output... */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
/* ------------------------------------------------------------
   SHARED GLOBAL LAYOUT HELPERS (Admin, Regular, Maintenance)
   Provides renderHead(), renderPageHeader(), renderFoot(),
   statusBadge(), and openTasksLabel().
   ------------------------------------------------------------ */

/** Escape output (short helper to keep templates readable). */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('appBaseUrl')) {
    function appBaseUrl(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        foreach (['/pages/', '/components/', '/Admin/', '/Secretary/', '/Regular/', '/Maintenance/', '/Components/'] as $marker) {
            $position = stripos($scriptName, $marker);
            if ($position !== false) {
                return rtrim(substr($scriptName, 0, $position), '/');
            }
        }

        $directory = str_replace('\\', '/', dirname($scriptName));
        return $directory === '.' ? '' : rtrim($directory, '/');
    }
}

if (!function_exists('appUrl')) {
    function appUrl(string $path): string
    {
        return appBaseUrl() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('appAssetUrl')) {
    function appAssetUrl(string $path): string
    {
        $relativePath = ltrim($path, '/');
        $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $version = is_file($filePath)
            ? filemtime($filePath) . '-' . filesize($filePath)
            : '1';
        return appUrl($relativePath) . '?v=' . rawurlencode((string) $version);
    }
}

if (!function_exists('rolePageUrl')) {
    function rolePageUrl(string $role, string $page): string
    {
        $roleKey = strtolower(trim($role));
        if ($roleKey === 'user') {
            $roleKey = 'regular';
        }
        $cleanPage = strtolower(ltrim($page, '/'));
        if ($cleanPage === 'settings.php') {
            return appUrl('pages/settings.php?role=' . rawurlencode($roleKey));
        }
        if (!str_starts_with($cleanPage, $roleKey . '_')) {
            $cleanPage = $roleKey . '_' . $cleanPage;
        }
        return appUrl('pages/' . $roleKey . '/' . $cleanPage);
    }
}

if (!function_exists('roleNavigationUrl')) {
    function roleNavigationUrl(string $role, string $href): string
    {
        if (strpos($href, '/') === 0 || preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        return rolePageUrl($role, $href);
    }
}

if (!function_exists('rolePreferenceAttributes')) {
    function rolePreferenceAttributes(string $role): string
    {
        $roleKey = strtolower(trim($role));
        if ($roleKey === 'user') {
            $roleKey = 'regular';
        }
        if (!in_array($roleKey, ['admin', 'secretary', 'regular', 'maintenance'], true)) {
            $roleKey = 'admin';
        }

        $fontSize = $_COOKIE[$roleKey . '_font_size'] ?? 'medium';
        $contentSize = $_COOKIE[$roleKey . '_content_size'] ?? 'medium';
        $background = $_COOKIE[$roleKey . '_background'] ?? 'building';
        if (!in_array($fontSize, ['small', 'medium', 'large'], true)) {
            $fontSize = 'medium';
        }
        if (!in_array($contentSize, ['small', 'medium', 'large'], true)) {
            $contentSize = 'medium';
        }
        if (!in_array($background, ['default', 'building', 'cloud', 'classic', 'classic2', 'terrain'], true)) {
            $background = 'building';
        }

        return 'data-role="' . e($roleKey) . '" data-font-size="' . e($fontSize) . '" data-content-size="' . e($contentSize) . '" data-background="' . e($background) . '"';
    }
}

/** Initials from a full name: "Jose Mar Worker" -> "JW". */
if (!function_exists('roleInitials')) {
    function roleInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) {
            return '?';
        }
        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($first . $last);
    }
}

/** Current user identity by role: [full name, role label, initials, tag]. */
if (!function_exists('roleUser')) {
    function roleUser(string $role = 'admin'): array
    {
        $defaults = roleUserDefaults($role);
        $roleKey = mtNormalizeRole($role) ?: strtolower(trim($role));

        /* Show the signed-in person's own name when this role's session belongs to them.
           Falls back to the generic label otherwise (e.g. no session, or another role's page). */
        $sessionName = trim((string) ($_SESSION['full_name'] ?? ''));
        if ($sessionName !== '' && mtNormalizeRole($_SESSION['role'] ?? '') === $roleKey) {
            return [$sessionName, $defaults[1], roleInitials($sessionName), $defaults[3]];
        }
        return $defaults;
    }

    function roleUserDefaults(string $role): array
    {
        switch (strtolower(trim($role))) {
            case 'secretary':
                return ['Maria Santos', 'Secretary', 'MS', 'Secretary'];
            case 'maintenance':
                return ['Jose Mar', 'Maintenance', 'JM', 'Maintenance'];
            case 'regular':
            case 'user':
                return ['Juan Dela Cruz', 'Regular User', 'JD', 'Regular'];
            case 'admin':
            default:
                return ['Admin User', 'Administrator', 'AU', 'Admin'];
        }
    }
}

/** Render the shared profile dropdown for a top bar or sidebar. */
if (!function_exists('renderRoleProfileMenu')) {
    function renderRoleProfileMenu(string $role = 'admin', string $layout = 'topnav'): void
    {
        $role = strtolower(trim($role));
        $isSidebar = strtolower(trim($layout)) === 'sidebar';
        $menuClass = $isSidebar ? 'sidebar-user-menu' : 'profile-menu';
        $menuId = $isSidebar ? 'sidebarUserMenu' : 'profileMenu';
        $logoutClass = $isSidebar || $role === 'maintenance'
            ? 'logout-link'
            : 'profile-logout';
        $settingsHref = appUrl('pages/settings.php?role=' . rawurlencode(mtNormalizeRole($role) ?: 'admin'));
        ?>
        <div class="<?= e($menuClass) ?>" id="<?= e($menuId) ?>">
            <a href="<?= e(rolePageUrl($role, 'profile.php')) ?>">Edit Profile</a>
            <a href="<?= e($settingsHref) ?>">Settings</a>
            <a href="<?= e(appUrl('Logout.php?role=' . rawurlencode(mtNormalizeRole($role) ?: 'admin'))) ?>" class="<?= e($logoutClass) ?>">Logout</a>
        </div>
<?php
    }
}

/**
 * The retract button no longer exists (the sidebar now expands on hover).
 * Kept as an empty function so Regular/Maintenance sidebars that still call it don't break.
 */
if (!function_exists('renderSidebarRetractToggle')) {
    function renderSidebarRetractToggle(): void
    {
    }
}

/** Navigation links shared by each role's top bar and sidebar. */
if (!function_exists('roleNavigationItems')) {
    function roleNavigationItems(string $role): array
    {
        switch (strtolower(trim($role))) {
            case 'secretary':
                return [
                    ['label' => 'Dashboard', 'href' => 'Dashboard.php', 'icon' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9 21v-7h6v7"></path>'],
                    ['label' => 'Approvals', 'href' => 'Approvals.php', 'icon' => '<path d="M4 5h16v14H4z"></path><path d="M8 12l2 2 5-5"></path>', 'indicator' => 'dot'],
                    ['label' => 'History', 'href' => 'History.php', 'icon' => '<path d="M3 12a9 9 0 1 0 9-9"></path><path d="M3 4v8h8"></path>'],
                    ['label' => 'Notification', 'href' => 'Notification.php', 'icon' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>', 'indicator' => 'dot']
                ];

            case 'regular':
            case 'user':
                return [
                    ['label' => 'Dashboard', 'href' => 'Dashboard.php', 'icon' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9 21v-7h6v7"></path>'],
                    ['label' => 'Reports', 'href' => 'Report.php', 'icon' => '<path d="M4 5h16v14H4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path>'],
                    ['label' => 'History', 'href' => 'History.php', 'icon' => '<path d="M3 12a9 9 0 1 0 9-9"></path><path d="M3 4v8h8"></path>'],
                    ['label' => 'Notification', 'href' => 'Notification.php', 'icon' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>', 'indicator' => 'dot'],
                    
                ];

            case 'maintenance':
                return [
                    ['label' => 'Dashboard', 'href' => 'Dashboard.php', 'icon' => '<path d="M3 11l9-7 9 7"></path><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"></path>'],
                    ['label' => 'Facility', 'href' => 'Facility.php', 'icon' => '<rect x="5" y="4" width="14" height="17" rx="2"></rect><path d="M9 3h6v3H9z"></path><path d="M8 11h8M8 15h5"></path>'],
                    ['label' => 'Task', 'href' => 'Task.php', 'icon' => '<circle cx="12" cy="12" r="8.5"></circle><path d="M12 8v4l3 2"></path>'],
                    ['label' => 'Notification', 'href' => 'Notification.php', 'icon' => '<path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6z"></path><path d="M10 20a2 2 0 0 0 4 0"></path>'],
                ];

            case 'admin':
            default:
                return [
                    ['label' => 'Dashboard', 'href' => 'Dashboard.php', 'icon' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V21h14V9.5"></path><path d="M9 21v-7h6v7"></path>'],
                    ['label' => 'Requests', 'href' => 'Request.php', 'icon' => '<path d="M4 5h16v14H4z"></path><path d="M8 12h8"></path><path d="M12 8v8"></path>'],
                    ['label' => 'Assignments', 'href' => 'Assignment.php', 'icon' => '<circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"></path><path d="M16 11l2 2 4-4"></path>'],
                    ['label' => 'Equipment', 'href' => 'Registry.php', 'icon' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>'],
                    ['label' => 'Reports', 'href' => 'Report.php', 'icon' => '<path d="M4 5h16v14H4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path>'],
                    ['label' => 'Notifications', 'href' => 'Notification.php', 'icon' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>'],
                    ['label' => 'Users', 'href' => 'Users.php', 'icon' => '<circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"></path><circle cx="17.5" cy="9" r="2.5"></circle><path d="M17 14.5c2.6 0 4.5 1.8 4.5 4.5"></path>'],
                ];
        }
    }
}

/**
 * Finds a stylesheet under assets/css/. Looks in this order:
 *   1. the exact path given (e.g. assets/css/Settings.css)
 *   2. assets/css/<file>                       (shared files: base, uniform, ...)
 *   3. assets/css/<role>/<role>_<file>         (e.g. assets/css/admin/admin_Dashboard.css)
 *   4. assets/css/<role>/<file>
 * Each step also matches regardless of upper/lower case, so it works on Windows and Linux.
 * Returns a path relative to the project root (used with appAssetUrl()).
 */
if (!function_exists('resolveRoleCss')) {
    function resolveRoleCss(string $roleKey, string $cssBase, string $given = ''): string
    {
        $root = dirname(__DIR__);
        $cssDir = 'assets/css';
        $candidates = [];
        if ($given !== '' && strpos($given, '/') !== false) {
            $candidates[] = ltrim($given, '/');
        }
        $candidates[] = $cssDir . '/' . $cssBase;
        $candidates[] = $cssDir . '/' . $roleKey . '/' . $roleKey . '_' . $cssBase;
        $candidates[] = $cssDir . '/' . $roleKey . '/' . $cssBase;

        foreach ($candidates as $candidate) {
            if (is_file($root . '/' . $candidate)) {
                return $candidate;
            }
            $dir = dirname($candidate);
            $want = strtolower(basename($candidate));
            if (is_dir($root . '/' . $dir)) {
                foreach (scandir($root . '/' . $dir) ?: [] as $entry) {
                    if (strtolower($entry) === $want && is_file($root . '/' . $dir . '/' . $entry)) {
                        return $dir . '/' . $entry;
                    }
                }
            }
        }
        return $cssDir . '/' . $cssBase; /* not found: keeps the original path so the 404 is easy to spot */
    }
}

/**
 * Opens the document:
 * Outputs <!DOCTYPE html>, <head> with base.css & page CSS,
 * opens <body> and includes the role-specific Navbar/Sidebar.
 */
if (!function_exists('renderHead')) {
    function renderHead(string $title, ?string $pageCss = null, string $role = 'admin'): void
    {
        $roleKey = strtolower(trim($role));
        if ($roleKey === 'user') {
            $roleKey = 'regular';
        }
        if (!in_array($roleKey, ['admin', 'secretary', 'regular', 'maintenance'], true)) {
            $roleKey = 'admin';
        }

        /* Page key used by CSS (body[data-page="profile"] ...): file name without .css */
        $cssBase = basename((string) $pageCss);
        $pageKey = strtolower(preg_replace('/\.css$/i', '', $cssBase));
        $pageKey = str_replace([$roleKey . '_', 'admin_', 'secretary_', 'maintenance_', 'regular_'], '', $pageKey);
        if (in_array($pageKey, ['reg_user_dashboard', 'dashboard'], true)) {
            $pageKey = 'dashboard';
        } elseif ($pageKey === 'notificationshared') {
            $pageKey = 'notification';
        }

        $resolvedCss = resolveRoleCss($roleKey, $cssBase, (string) $pageCss);
        ?>
<!DOCTYPE html>
<html lang="en" <?= rolePreferenceAttributes($roleKey) ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(appAssetUrl($resolvedCss)) ?>">
<?php if ($roleKey !== 'admin'): ?>
    <!-- Uniform design: loaded last so Regular / Maintenance / Secretary match the Admin look -->
    <link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/uniform.css')) ?>">
<?php endif; ?>
</head>
<body data-page="<?= e($pageKey) ?>">
<?php
        // Dynamically include role-appropriate navbar/sidebar
        $navFile = __DIR__ . '/' . $roleKey . '_navbar.php';
        if (file_exists($navFile)) {
            include $navFile;
        }
    }
}

/**
 * Standardized Page Header Banner:
 * Displays title, subtitle, and dynamic user badge pill with 32px bottom margin.
 */
if (!function_exists('renderPageHeader')) {
    function renderPageHeader(string $title, ?string $subtitle = null, string $role = 'admin'): void
    {
        $roleKey = strtolower(trim($role));
        if ($subtitle === null) {
            if ($roleKey === 'maintenance') {
                $subtitle = 'Maintenance Personnel Portal · Barangay Gulod';
            } elseif ($roleKey === 'secretary') {
                $subtitle = 'Approval & Concern Portal · Barangay Gulod';
            } elseif ($roleKey === 'regular' || $roleKey === 'user') {
                $subtitle = 'Maintenance Reporting Portal · Barangay Gulod';
            } else {
                $subtitle = 'Maintenance Reporting Portal · Bautista Building, IT Computer Laboratories';
            }
        }

        [$userName, , , $tag] = roleUser($roleKey);
        ?>
        <header class="page-header">
            <div>
                <h1><?= e($title) ?></h1>
                <p><?= e($subtitle) ?></p>
            </div>
            <span class="user-pill admin-user role-<?= e(strtolower($tag)) ?>">
                <span><?= e($userName) ?></span>
                <span class="role-tag"><?= e($tag) ?></span>
            </span>
        </header>
<?php
    }
}

/** Closes the document. */
if (!function_exists('renderFoot')) {
    function renderFoot(): void
    {
        ?>
        <!-- LOGOUT CONFIRMATION MODAL -->
<div class="logout-modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
    <div class="logout-modal">
        <div class="logout-modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </div>
        <h3 class="logout-modal-title" id="logoutTitle">Log out?</h3>
        <p class="logout-modal-text">You will be returned to the login page and will need to sign in again to continue.</p>
        <div class="logout-modal-actions">
            <button type="button" class="logout-btn-cancel" id="logoutCancel">Stay signed in</button>
            <button type="button" class="logout-btn-confirm" id="logoutConfirm">Yes, log out</button>
        </div>
    </div>
</div>
</body>
</html>
<?php
    }
}

/** Colored status badge HTML pill: "In Progress" -> <span class="status in-progress">. */
if (!function_exists('statusBadge')) {
    function statusBadge(string $status): string
    {
        $class = strtolower(str_replace(' ', '-', trim($status)));
        return '<span class="status ' . e($class) . '">' . e($status) . '</span>';
    }
}

/** Helper for task count label: "1 open task" / "3 open tasks". */
if (!function_exists('openTasksLabel')) {
    function openTasksLabel(int $count): string
    {
        return $count . ' open ' . ($count === 1 ? 'task' : 'tasks');
    }
}


/* =========================================================
   ROLE GUARD
   Every page inside Admin/, Secretary/, Regular/ and Maintenance/
   may only be opened by the role that owns that folder. The role
   comes from the login session (set in Login.php), never from the
   URL or a cookie. If someone opens another role's page they are
   sent back to THEIR OWN dashboard; if nobody is logged in they
   go to the login page.
   ========================================================= */
if (!function_exists('currentSessionRole')) {
    /* Role stored in THIS request's role-specific session. It must match the
       session it lives in (MTSESS_admin may only hold role "admin"). */
    function currentSessionRole(): string
    {
        $role = mtNormalizeRole($_SESSION['role'] ?? '');
        $context = mtRoleContext();
        return ($role !== '' && $role === $context) ? $role : '';
    }
}

if (!function_exists('enforceFolderRole')) {
    function enforceFolderRole(): void
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $file = strtolower(basename($scriptName));
        $required = '';

        if (str_starts_with($file, 'admin_')) $required = 'admin';
        elseif (str_starts_with($file, 'secretary_')) $required = 'secretary';
        elseif (str_starts_with($file, 'regular_')) $required = 'regular';
        elseif (str_starts_with($file, 'maintenance_')) $required = 'maintenance';
        else {
            $folders = [
                '/admin/'       => 'admin',
                '/secretary/'   => 'secretary',
                '/regular/'     => 'regular',
                '/maintenance/' => 'maintenance',
            ];
            $lowerScript = strtolower($scriptName);
            foreach ($folders as $marker => $role) {
                if (strpos($lowerScript, $marker) !== false) {
                    $required = $role;
                    break;
                }
            }
        }

        if ($required === '') {
            return; /* shared page (settings, login, ...) - nothing to enforce */
        }

        if (currentSessionRole() === $required) {
            return;
        }

        /* Not logged in as this role. If they ARE logged in as another role,
           send them to that role's own dashboard; otherwise to the login page. */
        $others = mtLoggedInRoles();
        $target = $others
            ? rolePageUrl($others[0], 'dashboard.php')
            : appUrl('Index.php');
        header('Location: ' . $target);
        exit;
    }
}

enforceFolderRole();    