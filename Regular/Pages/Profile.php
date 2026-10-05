<?php require_once '../../Components/layout.php'; ?>
<!DOCTYPE html>
<html lang="en" <?= rolePreferenceAttributes('regular') ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profile</title>

    <link rel="stylesheet" href="../../Components/css/base.css">
    <link rel="stylesheet" href="../Components/css/Profile.css">
</head>

<body>
<?php include '../Components/NavBar.php'; ?>
    <main class="profile-page">

        <header class="top-header">
            <div>
                <h1>Profile</h1>
                <p>Maintenance Reporting Portal • Barangay Gulod</p>
            </div>
        </header>


        <section class="profile-container">

            <div class="profile-grid">

                <div class="left-column">

                    <section class="profile-card profile-summary">

                        <div class="profile-avatar">
                            <span class="avatar-initials">MJ</span>
                        </div>

                        <div class="profile-name">
                            Mang Juan
                        </div>

                        <div class="profile-role">
                            Maintenance Staff
                        </div>

                        <div class="profile-actions">
                            <button type="button">
                                Edit profile
                            </button>

                            <button type="button">
                                Change Password
                            </button>
                        </div>

                    </section>


                    <section class="profile-card account-information">

                        <h2>Account Information</h2>

                        <div class="information-row">
                            <span>User ID</span>
                            <strong>MT-2026-001</strong>
                        </div>

                        <div class="information-row">
                            <span>Role</span>
                            <strong>Maintenance Staff</strong>
                        </div>

                        <div class="information-row">
                            <span>Account<br>Status</span>
                            <strong class="status-active">Active</strong>
                        </div>

                    </section>

                </div>


                <div class="right-column">

                    <section class="profile-card account-details">

                        <h2>Account Details</h2>

                        <div class="form-grid">

                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" value="" placeholder="Enter full name">
                            </div>

                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" value="" placeholder="Enter username">
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" value="" placeholder="name@example.com">
                            </div>

                            <div class="form-group">
                                <label for="contact">Contact Number</label>
                                <input type="text" id="contact" value="" placeholder="09XX XXX XXXX">
                            </div>

                        </div>

                        <div class="details-actions">
                            <button type="button">Cancel</button>
                            <button type="button">Save Changes</button>
                        </div>

                    </section>


                    <section class="profile-card recent-activity">

                        <h2>Recent Activity</h2>

                        <div class="activity-list">

                            <div class="activity-item">

                                <div>
                                    <strong>Maintenance Request Updated</strong>
                                    <p>Updated request MR-2026-001</p>
                                </div>

                                <span>Today</span>

                            </div>


                            <div class="activity-item">

                                <div>
                                    <strong>Maintenance Request Updated</strong>
                                    <p>Updated request MR-2026-001</p>
                                </div>

                                <span>Today</span>

                            </div>

                        </div>

                    </section>

                </div>

            </div>

        </section>

    </main>

</body>
</html>