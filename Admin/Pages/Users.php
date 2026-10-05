<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$users = [
    ['name' => 'Admin User',      'role' => 'Admin',            'status' => 'Active', 'lastActive' => 'Sept 19, 2026'],
    ['name' => 'Juan Dela Cruz',  'role' => 'IT Support Staff', 'status' => 'Active', 'lastActive' => 'Sept 19, 2026'],
    ['name' => 'Maria Santos',    'role' => 'IT Support Staff', 'status' => 'Active', 'lastActive' => 'Sept 19, 2026'],
    ['name' => 'Carlo Reyes',     'role' => 'IT Support Staff', 'status' => 'Active', 'lastActive' => 'Sept 18, 2026'],
];

renderHead('User & Role Management', 'Users.css');
?>
    <div class="container">

        <?php renderPageHeader('User & Role Management'); ?>

        <main class="card border-highlight">
            <div class="card-header">
                <div class="search-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" placeholder="Search users" class="search-bar">
                </div>
                <button class="add-btn">+ Add User</button>
            </div>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="font-bold"><?= e($user['name']) ?></td>
                            <td class="text-gray"><?= e($user['role']) ?></td>
                            <td class="text-green font-bold"><?= e($user['status']) ?></td>
                            <td class="text-gray"><?= e($user['lastActive']) ?></td>
                            <td><button class="btn-edit">Edit</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

    </div>
<?php renderFoot(); ?>
