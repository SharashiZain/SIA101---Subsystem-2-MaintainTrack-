<?php
require_once __DIR__ . '/layout.php';

/* =========================================================
   ROLE — the logged-in session is the ONLY source of truth.
   Each role (admin / secretary / regular / maintenance) has its
   own set of cookies, so editing Settings as one role can never
   change another role's look. No guessing: a missing or unknown
   role is sent back to the login page instead of "regular".
========================================================= */
$allowedRoles = ['admin', 'secretary', 'regular', 'maintenance'];

/* Settings is always opened for ONE role: Settings.php?role=admin. That role's
   own session must be logged in. We never fall back to another role. */
$role = currentSessionRole();
if ($role === '') {
    $others = mtLoggedInRoles();
    header('Location: ' . ($others
        ? appUrl('Components/Settings.php?role=' . rawurlencode($others[0]))
        : appUrl('Index.php')));
    exit;
}

/* Only accept known option values (never trust raw POST/cookie text). */
$allowedSizes       = ['small', 'medium', 'large'];
$allowedLayouts     = ['sidebar', 'topnav'];
$allowedBackgrounds = ['default', 'building', 'cloud', 'classic', 'classic2', 'terrain'];
$pick = static function ($value, array $allowed, string $fallback) {
    return in_array($value, $allowed, true) ? $value : $fallback;
};

[$userName, $roleLabel, $initials] = roleUser($role);

/* =========================================================
   HANDLE SAVE (do this BEFORE reading, so POST values win)
========================================================= */
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fontSize    = $pick($_POST['font_size']    ?? '', $allowedSizes,       'medium');
    $contentSize = $pick($_POST['content_size'] ?? '', $allowedSizes,       'medium');
    $layout      = $pick($_POST['layout']       ?? '', $allowedLayouts,     'sidebar');
    $background  = $pick($_POST['background']   ?? '', $allowedBackgrounds, 'building');

    $expire = time() + (60 * 60 * 24 * 365);

    setcookie($role . '_font_size',    $fontSize,    $expire, '/');
    setcookie($role . '_content_size', $contentSize, $expire, '/');
    setcookie($role . '_layout',       $layout,      $expire, '/');
    setcookie($role . '_background',   $background,  $expire, '/');

    /* Sync in-memory $_COOKIE so this request sees the new values */
    $_COOKIE[$role . '_font_size']    = $fontSize;
    $_COOKIE[$role . '_content_size'] = $contentSize;
    $_COOKIE[$role . '_layout']       = $layout;
    $_COOKIE[$role . '_background']   = $background;

    $message = 'Preferences saved.';
} else {
    $fontSize    = $pick($_COOKIE[$role . '_font_size']    ?? '', $allowedSizes,       'medium');
    $contentSize = $pick($_COOKIE[$role . '_content_size'] ?? '', $allowedSizes,       'medium');
    $layout      = $pick($_COOKIE[$role . '_layout']       ?? '', $allowedLayouts,     'sidebar');
    $background  = $pick($_COOKIE[$role . '_background']   ?? '', $allowedBackgrounds, 'building');
}

renderHead('Display Settings', 'Components/css/Settings.css', $role);
?>
<div class="page">

    <?php renderPageHeader('Display Settings', strtoupper($roleLabel) . ' PREFERENCES', $role); ?>

    <?php if ($message): ?>
        <div class="success-message"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="?role=<?= e(rawurlencode($role)) ?>" class="panel">
        <div class="panel-head">
            <h2>Navigation and accessibility</h2>
        </div>
        <p>These preferences are saved in this browser and used across your pages.</p>

        <div class="settings-grid">
            <div class="modal-field">
                <label for="layout">Navigation layout</label>
                <select name="layout" id="layout">
                    <option value="sidebar" <?= $layout === 'sidebar' ? 'selected' : '' ?>>Sidebar</option>
                    <option value="topnav"  <?= $layout === 'topnav'  ? 'selected' : '' ?>>Top navigation</option>
                </select>
            </div>

            <div class="modal-field">
                <label for="font_size">Font size</label>
                <select name="font_size" id="font_size">
                    <option value="small"  <?= $fontSize === 'small'  ? 'selected' : '' ?>>Small</option>
                    <option value="medium" <?= $fontSize === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="large"  <?= $fontSize === 'large'  ? 'selected' : '' ?>>Large</option>
                </select>
            </div>

            <div class="modal-field">
                <label for="content_size">Content size</label>
                <select name="content_size" id="content_size">
                    <option value="small"  <?= $contentSize === 'small'  ? 'selected' : '' ?>>Small</option>
                    <option value="medium" <?= $contentSize === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="large"  <?= $contentSize === 'large'  ? 'selected' : '' ?>>Large</option>
                </select>
            </div>
        </div>

        <div class="modal-field settings-field-full">
            <label for="background">Page background</label>
            <select name="background" id="background">
                <option value="default"  <?= $background === 'default'  ? 'selected' : '' ?>>Default</option>
                <option value="building" <?= $background === 'building' ? 'selected' : '' ?>>Barangay building</option>
                <option value="cloud"    <?= $background === 'cloud'    ? 'selected' : '' ?>>Blurry cloud</option>
                <option value="classic"  <?= $background === 'classic'  ? 'selected' : '' ?>>Classic blue</option>
                <option value="classic2" <?= $background === 'classic2' ? 'selected' : '' ?>>Classic waves</option>
                <option value="terrain"  <?= $background === 'terrain'  ? 'selected' : '' ?>>Terrain map</option>
            </select>
        </div>

        <div class="settings-actions">
            <button type="submit" class="modal-save">Save preferences</button>
        </div>
    </form>

</div>
<?php renderFoot(); ?>