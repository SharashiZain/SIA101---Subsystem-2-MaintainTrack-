<?php
require_once '../../Components/layout.php';

/* ---- Data arrays (Updated for Regular User) ---- */
$profileFields = [
    ['label' => 'Full Name',      'value' => 'Juan Dela Cruz',           'id' => 'profileFullName', 'type' => 'text'],
    ['label' => 'Username',       'value' => 'juandc',                   'id' => 'profileUsername', 'type' => 'text'],
    ['label' => 'Email Address',  'value' => 'juan.delacruz@email.com',  'id' => 'profileEmail',    'type' => 'email'],
    ['label' => 'Contact Number', 'value' => '0912 345 6789',            'id' => 'profileContact',  'type' => 'text'],
];

$accountInfo = [
    'User ID' => 'RU-2026-001', // Changed from MT to RU (Regular User)
    'Role'    => 'Regular User', // Changed from Maintenance Staff
    'Status'  => 'Active',
];

$recentActivities = [
    ['title' => 'Maintenance Request Submitted', 'desc' => 'Submitted request MR-2026-005', 'time' => 'Today'],
    ['title' => 'Maintenance Request Updated',   'desc' => 'Updated request MR-2026-001',   'time' => 'Yesterday'],
];

renderHead('Profile', 'Profile.css', 'regular');
?>
<div class="page">

    <?php renderPageHeader('Profile', 'Maintenance Reporting Portal • Barangay Gulod', 'regular'); ?>

    <div class="profile-layout">
        <section class="profile-card">
            <div class="avatar-lg" id="avatarCircle">JD</div> <!-- Changed from MJ to JD -->
            <h2 id="profileDisplayName">Juan Dela Cruz</h2> <!-- Changed from Mang Juan -->
            <span class="profile-role" id="profileDisplayRole">Regular User</span> <!-- Changed from Maintenance Staff -->
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
        const editBtn = document.getElementById('editProfileBtn');
        const saveBtn = document.getElementById('saveProfileBtn');
        const cancelBtn = document.getElementById('cancelProfileBtn');
        const formActions = document.getElementById('profileFormActions');
        const profileInputs = document.querySelectorAll('.form-grid input');
        const profileDisplayName = document.getElementById('profileDisplayName');
        const avatarCircle = document.getElementById('avatarCircle');

        let originalValues = {};

        editBtn.addEventListener('click', function () {
            originalValues = {};
            profileInputs.forEach(function (input) {
                originalValues[input.id] = input.value;
                input.removeAttribute('readonly');
                input.style.borderColor = '#4a86c8';
                input.style.background = '#ffffff';
            });
            formActions.style.display = 'flex';
            editBtn.style.display = 'none';
        });

        saveBtn.addEventListener('click', function () {
            const fullName = document.getElementById('profileFullName').value.trim();
            const email = document.getElementById('profileEmail').value.trim();

            if (!fullName || !email) {
                alert('Full name and email are required.');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }

            profileDisplayName.textContent = fullName;
            avatarCircle.textContent = getInitials(fullName);

            profileInputs.forEach(function (input) {
                input.setAttribute('readonly', true);
                input.style.borderColor = '';
                input.style.background = '';
            });

            formActions.style.display = 'none';
            editBtn.style.display = 'inline-flex';
            alert('Profile updated successfully!');
        });

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
        });

        /* Change Password Modal */
        const passwordModal = document.getElementById('passwordModal');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const passwordModalClose = document.getElementById('passwordModalClose');
        const passwordModalCancel = document.getElementById('passwordModalCancel');
        const passwordModalSave = document.getElementById('passwordModalSave');

        changePasswordBtn.addEventListener('click', function () {
            passwordModal.style.display = 'flex';
        });

        function closePasswordModal() {
            passwordModal.style.display = 'none';
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        }

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

        passwordModal.addEventListener('click', function (e) {
            if (e.target === passwordModal) closePasswordModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closePasswordModal();
        });

        function getInitials(name) {
            return name.split(' ').filter(n => n.length > 0).map(n => n[0]).join('').toUpperCase().slice(0, 2);
        }
    });
</script>
<?php renderFoot(); ?>
</html>