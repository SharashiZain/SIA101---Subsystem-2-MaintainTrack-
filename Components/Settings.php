<?php
require_once __DIR__ . '/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$validRoles = ['admin', 'regular', 'maintenance'];
$sessionRole = strtolower(trim($_SESSION['role'] ?? ''));
$requestedRole = strtolower(trim($_GET['role'] ?? ''));
if (in_array($sessionRole, $validRoles, true)) {
    $role = $sessionRole;
} elseif (in_array($requestedRole, $validRoles, true)) {
    $role = $requestedRole;
} else {
    $role = 'admin';
}

$layoutCookie = $role . '_layout';
$fontCookie = $role . '_font_size';
$contentCookie = $role . '_content_size';
$backgroundCookie = $role . '_background';
$allowedSizes = ['small', 'medium', 'large'];
$backgroundOptions = [
    'default' => 'Default background',
    'building' => 'Barangay building',
    'cloud' => 'Blurry cloud',
    'classic' => 'Classic blue',
    'classic2' => 'Classic waves',
    'terrain' => 'Terrain map',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $layout = $_POST['layout'] ?? 'sidebar';
    $fontSize = $_POST['font_size'] ?? 'medium';
    $contentSize = $_POST['content_size'] ?? 'medium';
    $background = $_POST['background'] ?? 'default';

    if (!in_array($layout, ['sidebar', 'navbar'], true)) {
        $layout = 'sidebar';
    }
    if (!in_array($fontSize, $allowedSizes, true)) {
        $fontSize = 'medium';
    }
    if (!in_array($contentSize, $allowedSizes, true)) {
        $contentSize = 'medium';
    }
    if (!array_key_exists($background, $backgroundOptions)) {
        $background = 'default';
    }

    $cookieOptions = [
        'expires' => time() + (365 * 24 * 60 * 60),
        'path' => '/',
        'samesite' => 'Lax',
    ];
    setcookie($layoutCookie, $layout, $cookieOptions);
    setcookie($fontCookie, $fontSize, $cookieOptions);
    setcookie($contentCookie, $contentSize, $cookieOptions);
    setcookie($backgroundCookie, $background, $cookieOptions);

    header('Location: Settings.php?role=' . rawurlencode($role) . '&saved=1');
    exit;
}

$layout = $_COOKIE[$layoutCookie] ?? 'sidebar';
$fontSize = $_COOKIE[$fontCookie] ?? 'medium';
$contentSize = $_COOKIE[$contentCookie] ?? 'medium';
$background = $_COOKIE[$backgroundCookie] ?? 'default';
if (!in_array($layout, ['sidebar', 'navbar'], true)) {
    $layout = 'sidebar';
}
if (!in_array($fontSize, $allowedSizes, true)) {
    $fontSize = 'medium';
}
if (!in_array($contentSize, $allowedSizes, true)) {
    $contentSize = 'medium';
}
if (!array_key_exists($background, $backgroundOptions)) {
    $background = 'default';
}

$roleNames = [
    'admin' => 'Admin',
    'regular' => 'Regular User',
    'maintenance' => 'Maintenance',
];
$dashboardHref = rolePageUrl($role, 'Dashboard.php');
renderHead('Display Settings', 'Components/Setting.css', $role);
?>
    <main class="page settings-page">
        <a class="settings-back" href="<?= e($dashboardHref) ?>">Back to <?= e($roleNames[$role]) ?> dashboard</a>
        <?php renderPageHeader('Display Settings', $roleNames[$role] . ' preferences', $role); ?>

        <section class="admin-settings">
            <div class="settings-panel">
                <div class="settings-panel-heading">
                    <h2>Navigation and accessibility</h2>
                    <p>These preferences are saved in this browser and used across role pages.</p>
                </div>

                <form method="post" action="Settings.php?role=<?= e($role) ?>" class="settings-form">
                    <div class="settings-field">
                        <label for="layout-setting">Navigation layout</label>
                        <select id="layout-setting" name="layout">
                            <option value="sidebar" <?= $layout === 'sidebar' ? 'selected' : '' ?>>Sidebar</option>
                            <option value="navbar" <?= $layout === 'navbar' ? 'selected' : '' ?>>Top navigation</option>
                        </select>
                        <p class="settings-field-help">The sidebar shows icons only and expands to full details when you hover over it.</p>
                    </div>

                    <div class="settings-field">
                        <label for="font-size-setting">Font size</label>
                        <select id="font-size-setting" name="font_size" data-display-preference="font-size">
                            <option value="small" <?= $fontSize === 'small' ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= $fontSize === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= $fontSize === 'large' ? 'selected' : '' ?>>Large</option>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="content-size-setting">Content size</label>
                        <select id="content-size-setting" name="content_size" data-display-preference="content-size">
                            <option value="small" <?= $contentSize === 'small' ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= $contentSize === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= $contentSize === 'large' ? 'selected' : '' ?>>Large</option>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="background-setting">Page background</label>
                        <select id="background-setting" name="background" data-display-preference="background">
                            <?php foreach ($backgroundOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $background === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="settings-actions">
                        <button type="submit" class="settings-save">Save preferences</button>
                    </div>
                </form>

                <?php if (isset($_GET['saved'])): ?>
                    <p class="settings-status" role="status">Preferences saved.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
<?php renderFoot(); ?>