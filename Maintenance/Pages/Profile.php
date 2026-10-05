<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$fields = [
    ['label' => 'Full Name',      'value' => 'Jose Mar'],
    ['label' => 'Username',       'value' => 'josemar'],
    ['label' => 'Email Address',  'value' => 'josemar@email.com', 'type' => 'email'],
    ['label' => 'Contact Number', 'value' => '0912 345 6789'],
];

$accountInfo = [
    'User ID' => 'MT-2026-001',
    'Role'    => 'Maintenance Personnel',
    'Status'  => 'Active',
];

$recentActivities = [
    ['title' => 'Maintenance Request Updated',  'desc' => 'Updated request MR-2026-001', 'time' => 'Today'],
    ['title' => 'Maintenance Request Assigned', 'desc' => 'Assigned to task MT-10001',   'time' => 'Yesterday'],
];

renderHead('Profile', 'Profile.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('Profile', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <div class="profile-layout">
        <section class="profile-card">
            <div class="avatar-lg">JM</div>
            <h2>Jose Mar</h2>
            <span class="profile-role">Maintenance Personnel</span>
            <div class="profile-btns">
                <button class="btn-light" type="button">Edit Profile</button>
                <button class="btn-light" type="button">Change Password</button>
            </div>
        </section>

        <section class="panel">
            <h3 class="section-label">Account Details</h3>
            <div class="form-grid">
                <?php foreach ($fields as $field): ?>
                    <div class="field">
                        <label><?= e($field['label']) ?></label>
                        <input type="<?= e($field['type'] ?? 'text') ?>" value="<?= e($field['value']) ?>" placeholder="<?= e($field['label']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-actions">
                <button class="btn-primary" type="button">Save</button>
                <button class="btn-outline" type="button">Cancel</button>
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
<?php renderFoot(); ?>
</html>