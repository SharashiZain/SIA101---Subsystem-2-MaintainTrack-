<?php
/* layout.php loads includes/session.php, which starts the secretary's own session.
   (Do not call session_start() before it, or the page falls back to the shared
   PHPSESSID, the role check fails and the page bounces to the login.) */
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../Components/layout.php';

/* =========================================================
   Supabase helper (server-side only)
   Uses the secret key by default so row-level security does not block
   this page. The key stays in PHP and is never sent to the browser.
========================================================= */
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

/* =========================================================
   Who is signed in?
   Uses $_SESSION['user_id'] (the users.user_id UUID). If your login stores
   it under a different key, change it here. When there is no session user,
   it falls back to the first active Secretary account (development only).
========================================================= */
function profileCurrentUser(bool $withSecrets = false): ?array
{
    $columns = 'user_id,full_name,username,email,role,status,coverage,access_type,auth_user_id'
             . ($withSecrets ? ',password_hash' : '');

    $sessionUser = $_SESSION['user_id'] ?? null;
    if (is_string($sessionUser) && preg_match('/^[0-9a-f-]{36}$/i', $sessionUser)) {
        $res = profileRest('/rest/v1/users?select=' . $columns . '&user_id=eq.' . rawurlencode($sessionUser) . '&limit=1');
    } else {
        $res = profileRest('/rest/v1/users?select=' . $columns . '&role=ilike.Secretary&status=eq.Active&order=created_at.asc&limit=1');
    }

    if ($res['ok'] && is_array($res['data']) && !empty($res['data'][0])) {
        return $res['data'][0];
    }
    return null;
}

/* Checks the table hash (bcrypt, or an old plain-text value), then Supabase Auth */
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

function profileJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/* =========================================================
   Actions (called by the page via fetch)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
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

        // Keep the sign-in email in step if this account uses Supabase Auth
        $warning = null;
        if (!empty($user['auth_user_id']) && strcasecmp($email, (string) $user['email']) !== 0) {
            $authRes = profileRest('/auth/v1/admin/users/' . rawurlencode($user['auth_user_id']), 'PUT', [
                'email' => $email,
                'email_confirm' => true,
            ]);
            if (!$authRes['ok']) {
                $warning = 'Profile saved, but your sign-in email could not be updated. Keep signing in with your previous email.';
            }
        }

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

        // Supabase Auth first (if this account uses it), then the users table
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

/* =========================================================
   Page data (loaded from the users table)
========================================================= */
$user = profileCurrentUser(false);
$loadError = $user ? '' : 'Could not load your account from the database.';

$fullName = $user['full_name'] ?? '';
$roleText = $user['role'] ?? 'Secretary';

$profileFields = [
    ['label' => 'Full name', 'value' => $fullName,                  'id' => 'secretaryFullName', 'type' => 'text',  'locked' => false],
    ['label' => 'Username',  'value' => $user['username'] ?? '',    'id' => 'secretaryUsername', 'type' => 'text',  'locked' => false],
    ['label' => 'Email',     'value' => $user['email'] ?? '',       'id' => 'secretaryEmail',    'type' => 'email', 'locked' => false],
    ['label' => 'Role',      'value' => $roleText,                  'id' => 'secretaryRole',     'type' => 'text',  'locked' => true], // roles are not self-editable
];

$accountDetails = [
    'Role'     => $roleText,
    'Status'   => $user['status'] ?? '—',
    'Coverage' => !empty($user['coverage']) ? $user['coverage'] : 'Barangay Gulod - Secretary Office',
    'Access'   => !empty($user['access_type']) ? $user['access_type'] : 'Approval & Concern Management',
];

/* Avatar initials, e.g. "Maria Santos" -> "MS" */
$initials = '';
foreach (preg_split('/\s+/', trim($fullName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: '?';

renderHead('Profile & Account', 'Profile.css', 'secretary');
?>
<div class="container">

    <!-- ONE CONTAINER: header + all page content live in one card -->
    <div class="one-card">

    <?php renderPageHeader('Profile & Account', null, 'secretary'); ?>

    <?php if ($loadError): ?>
        <div style="padding: 14px 18px; margin-bottom: 14px; border-radius: 10px; background: #fee2e2; color: #991b1b; font-size: 13px; font-weight: 600;">
            <?= e($loadError) ?>
        </div>
    <?php endif; ?>

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
                            <?= $field['locked'] ? 'data-locked="1"' : '' ?>
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
           SERVER CALL (saves to the database)
           ========================================== */
        async function postAction(action, payload) {
            const url = new URL(window.location.href);
            url.search = '';
            url.searchParams.set('action', action);

            try {
                const res = await fetch(url.toString(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.ok) {
                    return { ok: false, error: data.error || 'Something went wrong. Please try again.' };
                }
                return { ok: true, warning: data.warning || null };
            } catch (err) {
                return { ok: false, error: 'Could not reach the server. Please try again.' };
            }
        }

        /* ==========================================
           EDIT PROFILE
           ========================================== */
        editBtn.addEventListener('click', function () {
            /* Save current values for cancel */
            originalValues = {};
            profileInputs.forEach(function (input) {
                originalValues[input.id] = input.value;
                if (input.dataset.locked) return; // Role stays read-only
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
        saveBtn.addEventListener('click', async function () {
            const fullName = document.getElementById('secretaryFullName').value.trim();
            const username = document.getElementById('secretaryUsername').value.trim();
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

            saveBtn.disabled = true;
            const result = await postAction('update_profile', { fullName: fullName, username: username, email: email });
            saveBtn.disabled = false;

            if (!result.ok) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'Could not save profile',
                    text: result.error,
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
                text: result.warning || 'Your profile has been updated successfully!',
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

        passwordModalSave.addEventListener('click', async function () {
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

            passwordModalSave.disabled = true;
            const result = await postAction('change_password', { current: current, newPass: newPass, confirm: confirm });
            passwordModalSave.disabled = false;

            if (!result.ok) {
                SystemModal.open({
                    icon: 'warning',
                    title: 'Could not update password',
                    text: result.error,
                    confirmText: 'OK'
                });
                return;
            }

            closePasswordModal();
            SystemModal.open({
                icon: 'success',
                title: 'Password updated',
                text: result.warning || 'Your password has been updated successfully!',
                confirmText: 'OK'
            });
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