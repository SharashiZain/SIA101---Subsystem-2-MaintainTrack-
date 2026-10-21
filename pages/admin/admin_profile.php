<?php
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/profile_api.php';

/* Save requests from this page (fetch ?action=...) are answered here and exit */
profileHandleRequest();

/* Account data from the users table, falling back to the login session */
$user = profileCurrentUser(false);
$loadError = $user ? '' : 'Could not load your account from the database.';
$fullName = $user['full_name'] ?? ($_SESSION['full_name'] ?? '');
$username = $user['username'] ?? ($_SESSION['username'] ?? '');
$email    = $user['email'] ?? ($_SESSION['email'] ?? '');
$roleText = $user['role'] ?? 'Administrator';

$initials = '';
foreach (preg_split('/\s+/', trim($fullName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: '?';

$profileFields = [
    ['label' => 'Full name', 'value' => $fullName, 'id' => 'adminFullName', 'type' => 'text'],
    ['label' => 'Username',  'value' => $username, 'id' => 'adminUsername', 'type' => 'text'],
    ['label' => 'Email',     'value' => $email,    'id' => 'adminEmail',    'type' => 'email'],
    ['label' => 'Role',      'value' => $roleText, 'id' => 'adminRole',     'type' => 'text'],
];
$accountDetails = [
    'Role'     => $roleText,
    'Status'   => $user['status'] ?? 'Active',
    'Coverage' => 'Bautista Building - IT Labs',
    'Access'   => 'Manual assignment',
];
$profileConfig = [
    'fullId'     => 'adminFullName',
    'usernameId' => 'adminUsername',
    'emailId'    => 'adminEmail',
    'editable'   => ['adminFullName', 'adminUsername', 'adminEmail'],
];

renderHead('Profile & Account', 'Profile.css');
?>
<div class="container">

    <div class="one-card">
          <?php renderPageHeader('Profile & Account'); ?>
    <div class="content-layout">

        <!-- LEFT CARD: PROFILE SUMMARY -->
        <main class="profile-card card">
            <div class="avatar-section">
                <div class="avatar-circle" id="avatarCircle"><?= e($initials) ?></div>
                <h2 id="profileDisplayName"><?= e($fullName) ?></h2>
                <p id="profileDisplayRole"><?= e($roleText) ?></p>
            </div>

            <div class="profile-actions">
                <button type="button" class="btn-light" id="editProfileBtn">Edit Profile</button>
                <button type="button" class="btn-light" id="changePasswordBtn">Change Password</button>
            </div>
        </main>

        <!-- RIGHT CARD: ACCOUNT DETAILS -->
        <aside class="account-card card">
            <h3>Account Details</h3>

            <div class="form-grid" id="profileFormGrid">
                <?php foreach ($profileFields as $field): ?>
                    <div class="form-group">
                        <label for="<?= e($field['id']) ?>"><?= e($field['label']) ?></label>
                        <input
                            type="<?= e($field['type']) ?>"
                            id="<?= e($field['id']) ?>"
                            value="<?= e($field['value']) ?>"
                            readonly
                        >
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions" id="profileFormActions" style="display: none;">
                <button type="button" class="btn-primary" id="saveProfileBtn">Save Changes</button>
                <button type="button" class="btn-outline" id="cancelProfileBtn">Cancel</button>
            </div>

            <h3 style="margin-top: 32px;">Account Information</h3>
            <?php foreach ($accountDetails as $label => $value): ?>
                <div class="detail-group">
                    <span class="label"><?= e($label) ?></span>
                    <span class="value"><?= e($value) ?></span>
                </div>
            <?php endforeach; ?>
        </aside>

    </div>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="static-modal" id="passwordModal" style="display: none;">
    <div class="static-modal-content">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button type="button" class="modal-close" id="passwordModalClose">✕</button>
        </div>

        <div class="modal-field">
            <label for="currentPassword">Current Password</label>
            <input type="password" id="currentPassword" placeholder="Enter current password">
        </div>

        <div class="modal-field">
            <label for="newPassword">New Password</label>
            <input type="password" id="newPassword" placeholder="Enter new password">
        </div>

        <div class="modal-field">
            <label for="confirmPassword">Confirm New Password</label>
            <input type="password" id="confirmPassword" placeholder="Confirm new password">
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="passwordModalCancel">Cancel</button>
            <button type="button" class="modal-save" id="passwordModalSave">Update Password</button>
        </div>
    </div>
</div>

<script type="application/json" id="profileConfig"><?= json_encode($profileConfig, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(appAssetUrl('assets/js/ProfileEdit.js')) ?>"></script>
<?php renderFoot(); ?>