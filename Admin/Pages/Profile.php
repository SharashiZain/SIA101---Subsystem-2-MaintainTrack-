<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$profileFields = [
    'Full name' => 'Admin User',
    'Username'  => 'admin.user',
    'Email'     => 'admin.user@qcu.edu.ph',
    'Role'      => 'Administrator',
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

            <main class="profile-card card">
                <div class="avatar-section">
                    <div class="avatar-circle">A</div>
                    <h2>Admin User</h2>
                    <p>Administrator</p>
                </div>

                <div class="form-grid">
                    <?php foreach ($profileFields as $label => $value): ?>
                        <div class="form-group">
                            <label><?= e($label) ?></label>
                            <input type="text" value="<?= e($value) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button class="btn-primary">Save changes</button>
                </div>
            </main>

            <aside class="account-card card">
                <h3>Account</h3>
                <?php foreach ($accountDetails as $label => $value): ?>
                    <div class="detail-group">
                        <span class="label"><?= e($label) ?></span>
                        <span class="value"><?= e($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </aside>

        </div>
    </div>
<?php renderFoot(); ?>
