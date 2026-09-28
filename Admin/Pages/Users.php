<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User & Role Management</title>
    <link rel="stylesheet" href="../Components/css/Users.css">
</head>
<body>
    <?php include '../Components/NavBar.php'; ?>
    <div class="container">
        <header>
            <div class="title-container">
                <h1>User & Role Management</h1>
                <p>Maintenance Reporting Portal</p>
            </div>
            <div class="user-info">
                <span>Admin User</span>
            </div>
        </header>

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
                    <tr>
                        <td class="font-bold">Admin User</td>
                        <td class="text-gray">Admin</td>
                        <td class="text-green font-bold">Active</td>
                        <td class="text-gray">Sept 19, 2026</td>
                        <td><button class="btn-edit">Edit</button></td>
                    </tr>
                    <tr>
                        <td class="font-bold">Juan Dela Cruz</td>
                        <td class="text-gray">IT Support Staff</td>
                        <td class="text-green font-bold">Active</td>
                        <td class="text-gray">Sept 19, 2026</td>
                        <td><button class="btn-edit">Edit</button></td>
                    </tr>
                    <tr>
                        <td class="font-bold">Maria Santos</td>
                        <td class="text-gray">IT Support Staff</td>
                        <td class="text-green font-bold">Active</td>
                        <td class="text-gray">Sept 19, 2026</td>
                        <td><button class="btn-edit">Edit</button></td>
                    </tr>
                    <tr>
                        <td class="font-bold">Carlo Reyes</td>
                        <td class="text-gray">IT Support Staff</td>
                        <td class="text-green font-bold">Active</td>
                        <td class="text-gray">Sept 18, 2026</td>
                        <td><button class="btn-edit">Edit</button></td>
                    </tr>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>