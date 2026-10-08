<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$profileFields = [
    ['label' => 'Full name', 'value' => 'Admin User',              'id' => 'adminFullName', 'type' => 'text'],
    ['label' => 'Username',  'value' => 'admin.user',              'id' => 'adminUsername', 'type' => 'text'],
    ['label' => 'Email',     'value' => 'admin.user@qcu.edu.ph',   'id' => 'adminEmail',    'type' => 'email'],
    ['label' => 'Role',      'value' => 'Administrator',           'id' => 'adminRole',     'type' => 'text'],
];

$accountDetails = [
    'Role'     => 'Administrator',
    'Status'   => 'Active',
    'Coverage' => 'Bautista Building - IT Labs',
    'Access'   => 'Manual assignment',
];

renderHead('Profile & Account', 'Profile.css');
?>
<div class="container">

    <?php renderPageHeader('Profile & Account'); ?>

    <div class="content-layout">

        <!-- LEFT CARD: PROFILE SUMMARY -->
        <main class="profile-card card">
            <div class="avatar-section">
                <div class="avatar-circle" id="avatarCircle">A</div>
                <h2 id="profileDisplayName">Admin User</h2>
                <p id="profileDisplayRole">Administrator</p>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {

        /* ==========================================
           ELEMENTS
           ========================================== */
        const editBtn = document.getElementById('editProfileBtn');
        const saveBtn = document.getElementById('saveProfileBtn');
        const cancelBtn = document.getElementById('cancelProfileBtn');
        const formActions = document.getElementById('profileFormActions');
        const profileInputs = document.querySelectorAll('#profileFormGrid input');

        const profileDisplayName = document.getElementById('profileDisplayName');
        const profileDisplayRole = document.getElementById('profileDisplayRole');
        const avatarCircle = document.getElementById('avatarCircle');

        /* Original values for cancel */
        let originalValues = {};

        /* ==========================================
           EDIT PROFILE
           ========================================== */
        editBtn.addEventListener('click', function () {
            /* Save current values for cancel */
            originalValues = {};
            profileInputs.forEach(function (input) {
                originalValues[input.id] = input.value;
                input.removeAttribute('readonly');
                input.style.borderColor = '#4a86c8';
                input.style.background = '#ffffff';
            });

            formActions.style.display = 'flex';
            editBtn.style.display = 'none';
            editBtn.textContent = 'Editing...';
        });

        /* ==========================================
           SAVE CHANGES
           ========================================== */
        saveBtn.addEventListener('click', function () {
            const fullName = document.getElementById('adminFullName').value.trim();
            const email = document.getElementById('adminEmail').value.trim();

            if (!fullName || !email) {
                alert('Full name and email are required.');
                return;
            }

            /* Simple email validation */
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }

            /* Update display name and avatar */
            profileDisplayName.textContent = fullName;
            avatarCircle.textContent = getInitials(fullName);

            /* Lock inputs again */
            profileInputs.forEach(function (input) {
                input.setAttribute('readonly', true);
                input.style.borderColor = '';
                input.style.background = '';
            });

            formActions.style.display = 'none';
            editBtn.style.display = 'inline-flex';
            editBtn.textContent = 'Edit Profile';

            alert('Profile updated successfully!');
        });

        /* ==========================================
           CANCEL EDIT
           ========================================== */
        cancelBtn.addEventListener('click', function () {
            profileInputs.forEach(function (input) {
                if (originalValues[input.id] !== undefined) {
                    input.value = originalValues[input.id];
                }
                input.setAttribute('readonly', true);
                input.style.borderColor = '';
                input.style.background = '';
            });

            formActions.style.display = 'none';
            editBtn.style.display = 'inline-flex';
            editBtn.textContent = 'Edit Profile';
        });

        /* ==========================================
           CHANGE PASSWORD MODAL
           ========================================== */
        const passwordModal = document.getElementById('passwordModal');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const passwordModalClose = document.getElementById('passwordModalClose');
        const passwordModalCancel = document.getElementById('passwordModalCancel');
        const passwordModalSave = document.getElementById('passwordModalSave');

        function openPasswordModal() {
            passwordModal.style.display = 'flex';
        }

        function closePasswordModal() {
            passwordModal.style.display = 'none';
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        }

        changePasswordBtn.addEventListener('click', openPasswordModal);
        passwordModalClose.addEventListener('click', closePasswordModal);
        passwordModalCancel.addEventListener('click', closePasswordModal);

        passwordModalSave.addEventListener('click', function () {
            const current = document.getElementById('currentPassword').value;
            const newPass = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmPassword').value;

            if (!current || !newPass || !confirm) {
                alert('Please fill in all password fields.');
                return;
            }

            if (newPass.length < 6) {
                alert('New password must be at least 6 characters.');
                return;
            }

            if (newPass !== confirm) {
                alert('New passwords do not match.');
                return;
            }

            alert('Password updated successfully!');
            closePasswordModal();
        });

        /* ==========================================
           CLOSE MODAL ON OUTSIDE CLICK
           ========================================== */
        passwordModal.addEventListener('click', function (e) {
            if (e.target === passwordModal) closePasswordModal();
        });

        /* ==========================================
           ESCAPE KEY CLOSES MODAL
           ========================================== */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closePasswordModal();
        });

        /* ==========================================
           HELPERS
           ========================================== */
        function getInitials(name) {
            return name
                .split(' ')
                .filter(function (n) { return n.length > 0; })
                .map(function (n) { return n[0]; })
                .join('')
                .toUpperCase()
                .slice(0, 2);
        }
    });
</script>
<?php renderFoot(); ?>