<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Personnel Dashboard</title>
    <link rel="stylesheet" href="../Components/css/Maintenance.css">
</head>

<body>
<?php include '../Components/NavBar.php'; ?>
    <main class="dashboard-page">

        <!-- HEADER -->
        <header class="top-header">
            <div class="page-heading">
                <h1>Maintenance Personnel Dashboard</h1>
                <p>Maintenance Reporting Portal • Barangay Gulod</p>
            </div>
        </header>

        <!-- STATISTICS -->
        <section class="stats-grid">

            <div class="stat-card">
                <span class="stat-title">My Assigned Tasks</span>
                <strong class="stat-number">10</strong>
                <span class="stat-description">Pending / In-Progress</span>
            </div>

            <div class="stat-card">
                <span class="stat-title">Total Requests</span>
                <strong class="stat-number total">10</strong>
                <span class="stat-description">This Month</span>
            </div>

            <div class="stat-card">
                <span class="stat-title">Completed</span>
                <strong class="stat-number completed">5</strong>
                <span class="stat-description">This Month</span>
            </div>

            <div class="stat-card">
                <span class="stat-title">Pending</span>
                <strong class="stat-number pending">5</strong>
                <span class="stat-description">All Pending requests</span>
            </div>

        </section>

        <!-- LOWER CONTENT -->
        <section class="dashboard-grid">

            <!-- RECENT REQUESTS -->
            <div class="dashboard-panel requests-panel">

                <div class="panel-header">
                    <h2>Recent Maintenance Requests</h2>
                </div>

                <div class="request-tools">

                    <div class="search-box">
                        <input type="text" placeholder="Search requests...">
                        <span>⌕</span>
                    </div>

                    <select>
                        <option>Filter</option>
                        <option>Pending</option>
                        <option>Assigned</option>
                        <option>In Progress</option>
                        <option>Completed</option>
                    </select>

                    <button class="reset-button">
                        Reset
                    </button>

                    <button class="view-button">
                        View all
                    </button>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ISSUE/DESCRIPTION</th>
                                <th>LOCATION</th>
                                <th>STATUS</th>
                                <th>DATE REPORTED</th>
                            </tr>
                        </thead>

                        <tbody>

                            <tr>
                                <td>MT-00124</td>
                                <td>
                                    PC Won't turn on
                                    <small>Computer unit #1</small>
                                </td>
                                <td>Lab 1 (B1)</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>
                                    Sept 19, 2026
                                    <small>9:00 AM</small>
                                </td>
                            </tr>

                            <tr>
                                <td>MT-00127</td>
                                <td>
                                    Broken Aircon
                                    <small>Air Conditioning unit #3</small>
                                </td>
                                <td>Lab 3 (B1)</td>
                                <td><span class="status-badge status-assigned">Assigned</span></td>
                                <td>
                                    Sept 16, 2026
                                    <small>8:45 AM</small>
                                </td>
                            </tr>

                            <tr>
                                <td>MT-00124</td>
                                <td>
                                    PC Won't turn on
                                    <small>Computer unit #1</small>
                                </td>
                                <td>Lab 1 (B1)</td>
                                <td><span class="status-badge status-progress">In Progress</span></td>
                                <td>
                                    Sept 19, 2026
                                    <small>8:00 AM</small>
                                </td>
                            </tr>

                            <tr>
                                <td>MT-00124</td>
                                <td>
                                    PC Won't turn on
                                    <small>Computer unit #1</small>
                                </td>
                                <td>Lab 1 (B1)</td>
                                <td><span class="status-badge status-progress">In Progress</span></td>
                                <td>
                                    Sept 19, 2026
                                    <small>8:00 AM</small>
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- RECENT ACTIVITIES -->
            <div class="dashboard-panel activity-panel">

                <div class="panel-header">
                    <h2>Recent Activities</h2>
                </div>

                <div class="activity-list">

                    <div class="activity-item">

                        <div class="activity-icon completed-icon">
                            ✓
                        </div>

                        <div class="activity-content">
                            <strong>
                                Request MT-00124 mark as completed.
                            </strong>

                            <span>
                                Computer unit #1
                            </span>
                        </div>

                        <time>9:00 AM</time>

                    </div>

                    <div class="activity-item">

                        <div class="activity-icon assigned-icon">
                            👥
                        </div>

                        <div class="activity-content">
                            <strong>
                                Request MT-00127 assigned to you.
                            </strong>

                            <span>
                                Air Conditioning unit #3
                            </span>
                        </div>

                        <time>8:45 AM</time>

                    </div>

                    <div class="activity-item">

                        <div class="activity-icon progress-icon">
                            ↻
                        </div>

                        <div class="activity-content">
                            <strong>
                                Request MT-00124 updated to In-Progress.
                            </strong>

                            <span>
                                Air Conditioning unit #3
                            </span>
                        </div>

                        <time>7:47 AM</time>

                    </div>

                </div>

            </div>

        </section>

    </main>

</body>
</html>