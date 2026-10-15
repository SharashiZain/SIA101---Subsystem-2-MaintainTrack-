<?php
require_once __DIR__ . '/../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$profileFields = [
    ['label' => 'Full name', 'value' => 'Maria Santos',              'id' => 'secretaryFullName', 'type' => 'text'],
    ['label' => 'Username',  'value' => 'secretary',                 'id' => 'secretaryUsername', 'type' => 'text'],
    ['label' => 'Email',     'value' => 'secretary@qcu.edu.ph',      'id' => 'secretaryEmail',    'type' => 'email'],
    ['label' => 'Role',      'value' => 'Secretary',                 'id' => 'secretaryRole',     'type' => 'text'],
];

$accountDetails = [
    'Role'     => 'Secretary',
    'Status'   => 'Active',
    'Coverage' => 'Barangay Gulod - Secretary Office',
    'Access'   => 'Approval & Concern Management',
];

renderHead('Profile & Account', 'Profile.css', 'secretary');
?>
<div class="container">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('Profile & Account', null, 'secretary'); ?>

    <div class="content-layout">

        <!-- LEFT CARD: PROFILE SUMMARY -->
        <main class="profile-card card">
            <div class="avatar-section">
                <div class="avatar-circle" id="avatarCircle">MS</div>
                <h2 id="profileDisplayName">Maria Santos</h2>
                <p id="profileDisplayRole">Secretary</p>
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
    </div><!-- /.one-card -->
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

<!-- =========================================================
     SYSTEM MODAL (validation + success)
========================================================= -->
<div class="system-modal-overlay" id="systemModal" role="dialog" aria-modal="true" style="display: none;">
    <div class="system-modal">
        <div class="system-modal-icon" id="systemModalIcon"></div>
        <h3 class="system-modal-title" id="systemModalTitle">Title</h3>
        <p class="system-modal-text" id="systemModalText">Message</p>
        <div class="system-modal-actions" id="systemModalActions"></div>
    </div>
</div>

<script>
    /* ==========================================
       SYSTEM MODAL HELPER
       ========================================== */
    window.SystemModal = (function () {
        const overlay = document.getElementById('systemModal');
        const iconBox = document.getElementById('systemModalIcon');
        const titleEl = document.getElementById('systemModalTitle');
        const textEl = document.getElementById('systemModalText');
        const actionsEl = document.getElementById('systemModalActions');

        const icons = {
            question: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            warning:  '<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            success:  '<svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            danger:   '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
        };

        function open(options) {
            const { icon = 'question', title = '', text = '', confirmText = 'OK', cancelText = null, confirmClass = 'system-btn-confirm', onConfirm = () => {}, onCancel = () => {} } = options;

            iconBox.className = 'system-modal-icon icon-' + icon;
            iconBox.innerHTML = icons[icon] || icons.question;
            titleEl.textContent = title;
            textEl.textContent = text;
            actionsEl.innerHTML = '';

            if (cancelText) {
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'system-btn system-btn-cancel';
                cancelBtn.textContent = cancelText;
                cancelBtn.addEventListener('click', function () { close(); onCancel(); });
                actionsEl.appendChild(cancelBtn);
            }

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'system-btn ' + confirmClass;
            confirmBtn.textContent = confirmText;
            confirmBtn.addEventListener('click', function () { close(); onConfirm(); });
            actionsEl.appendChild(confirmBtn);

            overlay.style.display = 'flex';
        }

        function close() { overlay.style.display = 'none'; }

        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display === 'flex') close();
        });

        return { open, close };
    })();

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
            const fullName = document.getElementById('secretaryFullName').value.trim();
            const email = document.getElementById('secretaryEmail').value.trim();

            if (!fullName || !email) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'Missing required fields',
                    text: 'Full name and email are required.',
                    confirmText: 'OK'
                });
                return;
            }

            /* Simple email validation */
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'Invalid email',
                    text: 'Please enter a valid email address.',
                    confirmText: 'OK'
                });
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

            SystemModal.open({
                icon: 'success',
                title: 'Profile updated',
                text: 'Your profile has been updated successfully!',
                confirmText: 'OK'
            });
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
                SystemModal.open({
                    icon: 'warning',
                    title: 'Missing fields',
                    text: 'Please fill in all password fields.',
                    confirmText: 'OK'
                });
                return;
            }

            if (newPass.length < 6) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'Password too short',
                    text: 'New password must be at least 6 characters.',
                    confirmText: 'OK'
                });
                return;
            }

            if (newPass !== confirm) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'Passwords do not match',
                    text: 'New passwords do not match.',
                    confirmText: 'OK'
                });
                return;
            }

            SystemModal.open({
                icon: 'success',
                title: 'Password updated',
                text: 'Your password has been updated successfully!',
                confirmText: 'OK'
            });
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
</html>