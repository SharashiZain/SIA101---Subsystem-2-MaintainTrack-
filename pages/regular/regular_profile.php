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
$roleText = $user['role'] ?? 'Regular User';

$initials = '';
foreach (preg_split('/\s+/', trim($fullName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: '?';

$profileFields = [
    ['label' => 'Full Name',      'value' => $fullName,                   'id' => 'profileFullName', 'type' => 'text'],
    ['label' => 'Username',       'value' => $username,                   'id' => 'profileUsername', 'type' => 'text'],
    ['label' => 'Email Address',  'value' => $email,                      'id' => 'profileEmail',    'type' => 'email'],
    ['label' => 'Contact Number', 'value' => $user['contact_number'] ?? '', 'id' => 'profileContact',  'type' => 'text'],
];
$accountInfo = [
    'User ID' => $user['user_id'] ?? '—',
    'Role'    => $roleText,
    'Status'  => $user['status'] ?? 'Active',
];
$recentActivities = [];
$profileConfig = [
    'fullId'     => 'profileFullName',
    'usernameId' => 'profileUsername',
    'emailId'    => 'profileEmail',
    'editable'   => ['profileFullName', 'profileUsername', 'profileEmail'],
];

renderHead('Profile', 'Profile.css', 'regular');
?>
<div class="page">

    <!-- ONE CONTAINER: banner + all profile sections -->
    <div class="profile-wrap">

    <?php renderPageHeader('Profile', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <div class="profile-layout">
        <section class="profile-card">
            <div class="avatar-lg" id="avatarCircle"><?= e($initials) ?></div>
            <h2 id="profileDisplayName"><?= e($fullName) ?></h2>
            <span class="profile-role" id="profileDisplayRole"><?= e($roleText) ?></span>
            <div class="profile-btns">
                <button class="btn-light" type="button" id="editProfileBtn">Edit Profile</button>
                <button class="btn-light" type="button" id="changePasswordBtn">Change Password</button>
            </div>
        </section>

        <section class="panel">
            <h3 class="section-label">Account Details</h3>
            <div class="form-grid">
                <?php foreach ($profileFields as $field): ?>
                    <div class="field">
                        <label for="<?= e($field['id']) ?>"><?= e($field['label']) ?></label>
                        <input 
                            type="<?= e($field['type']) ?>" 
                            id="<?= e($field['id']) ?>" 
                            value="<?= e($field['value']) ?>" 
                            placeholder="<?= e($field['label']) ?>"
                            readonly
                        >
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-actions" id="profileFormActions" style="display: none;">
                <button class="btn-primary" type="button" id="saveProfileBtn">Save</button>
                <button class="btn-outline" type="button" id="cancelProfileBtn">Cancel</button>
            </div>
        </section>

        <section class="info-card">
            <h3 class="section-label">Account Information</h3>
            <?php foreach ($accountInfo as $label => $val): ?>
                <div class="info-row">
                    <span><?= e($label) ?></span>
                    <strong><?= e($val) ?></strong>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="info-card">
            <h3 class="section-label">Recent Activity</h3>
            <?php foreach ($recentActivities as $act): ?>
                <div class="activity-row">
                    <div>
                        <strong><?= e($act['title']) ?></strong>
                        <span><?= e($act['desc']) ?></span>
                    </div>
                    <span class="activity-time"><?= e($act['time']) ?></span>
                </div>
            <?php endforeach; ?>
        </section>
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
</html>