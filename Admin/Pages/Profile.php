<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile & Account</title>
    <link rel="stylesheet" href="../Components/css/Profile.css">
</head>
<body>
    <?php include '../Components/NavBar.php'; ?>
    <div class="container">
        <header>
            <div class="title-container">
                <h1>Profile & Account</h1>
                <p>Maintenance Reporting Portal</p>
            </div>
            <div class="user-info">
                <span>Admin User</span>
            </div>
        </header>

        <div class="content-layout">
            <main class="profile-card card">
                <div class="avatar-section">
                    <div class="avatar-circle">A</div>
                    <h2>Admin User</h2>
                    <p>Administrator</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full name</label>
                        <input type="text" value="Admin User">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="admin.user">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" value="admin.user@qcu.edu.ph">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="Administrator">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn-primary">Save changes</button>
                </div>
            </main>

            <aside class="account-card card">
                <h3>Account</h3>
                
                <div class="detail-group">
                    <span class="label">Role</span>
                    <span class="value">Administrator</span>
                </div>
                
                <div class="detail-group">
                    <span class="label">Status</span>
                    <span class="value">Active</span>
                </div>
                
                <div class="detail-group">
                    <span class="label">Coverage</span>
                    <span class="value">Bautista Building - IT Labs</span>
                </div>
                
                <div class="detail-group">
                    <span class="label">Access</span>
                    <span class="value">Manual assignment</span>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>