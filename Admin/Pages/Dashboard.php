<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrator Dashboard</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="../Components/css/Dashboard.css">
</head>

<body>
<?php include '../Components/NavBar.php'; ?>
    <div class="dashboard">

        <!-- HEADER -->
        <header class="topbar">

            <div>
                <h1>Administrator Dashboard</h1>

                <p>
                    Maintenance Reporting Portal · Bautista Building, IT Computer Laboratories
                </p>
            </div>

            <div class="admin-user">
                Admin User
            </div>

        </header>


        <!-- STATISTICS -->
        <section class="stats">

            <div class="stat-card">
                <span>Total Requests</span>
                <strong>10</strong>
                <small>This Month</small>
            </div>

            <div class="stat-card">
                <span>Pending / Unassigned</span>
                <strong class="orange">5</strong>
                <small>All Pending</small>
            </div>

            <div class="stat-card">
                <span>In Progress</span>
                <strong class="purple">3</strong>
                <small>Active Requests</small>
            </div>

            <div class="stat-card">
                <span>Completed</span>
                <strong class="green">2</strong>
                <small>This Month</small>
            </div>

        </section>


        <!-- SECONDARY STATISTICS -->
        <section class="secondary-stats">

            <div class="wide-stat">

                <div>
                    <span>Avg. Response Time</span>
                    <strong>2h 18m</strong>
                </div>

                <small>
                    Based on all available requests
                </small>

            </div>


            <div class="wide-stat">

                <div>
                    <span>Recurring-Issue Equipment</span>
                    <strong class="orange">3</strong>
                </div>

                <small>
                    units with repeat repairs
                </small>

            </div>

        </section>


        <!-- MAIN CONTENT -->
        <div class="content-grid">


            <!-- MAINTENANCE REQUESTS -->
            <section class="panel requests-panel">

                <div class="panel-title">

                    <h2>
                        Recent Maintenance Requests
                    </h2>

                </div>


                <!-- TOOLBAR -->
                <div class="toolbar">

                    <input
                        type="text"
                        placeholder="Search requests"
                    >

                    <button>
                        Filter
                    </button>

                    <a href="#">
                        Reset
                    </a>

                    <a href="#" class="view-all">
                        View all
                    </a>

                </div>


                <!-- TABLE -->
                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>ISSUE / DESCRIPTION</th>

                                <th>EQUIPMENT</th>

                                <th>LAB</th>

                                <th>CATEGORY</th>

                                <th>STATUS</th>

                            </tr>

                        </thead>


                        <tbody>

                            <tr>

                                <td>MT-00131</td>

                                <td>
                                    <b>PC won't turn on</b>
                                </td>

                                <td>
                                    Computer Unit #1
                                </td>

                                <td>
                                    Lab 1 (01)
                                </td>

                                <td>
                                    Hardware
                                </td>

                                <td>
                                    <span class="status in-progress">
                                        In Progress
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>MT-00132</td>

                                <td>
                                    <b>Broken aircon</b>
                                </td>

                                <td>
                                    Air Conditioning Unit #2
                                </td>

                                <td>
                                    Lab 3 (01)
                                </td>

                                <td>
                                    Facility
                                </td>

                                <td>
                                    <span class="status assigned">
                                        Assigned
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>MT-00133</td>

                                <td>
                                    <b>No internet connection</b>
                                </td>

                                <td>
                                    Switch #2
                                </td>

                                <td>
                                    Lab 2 (01)
                                </td>

                                <td>
                                    Network
                                </td>

                                <td>
                                    <span class="status pending">
                                        Pending
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>MT-00134</td>

                                <td>
                                    <b>Software won't launch</b>
                                </td>

                                <td>
                                    Computer Unit #7
                                </td>

                                <td>
                                    Lab 1 (01)
                                </td>

                                <td>
                                    Software
                                </td>

                                <td>
                                    <span class="status completed">
                                        Completed
                                    </span>
                                </td>

                            </tr>


                            <tr>

                                <td>MT-00135</td>

                                <td>
                                    <b>Flickering lights</b>
                                </td>

                                <td>
                                    Lab 4 Lights
                                </td>

                                <td>
                                    Lab 4 (01)
                                </td>

                                <td>
                                    Electrical
                                </td>

                                <td>
                                    <span class="status pending">
                                        Pending
                                    </span>
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </section>



            <!-- RECENT ACTIVITIES -->
            <aside class="panel activities-panel">

                <h2>
                    Recent Activities
                </h2>


                <div class="activity">

                    <b>
                        Request MT-0034 completed
                    </b>

                    <small>
                        9:10 AM
                    </small>

                </div>


                <div class="activity">

                    <b>
                        Request MT-0032 assigned to Maria
                    </b>

                    <small>
                        8:45 AM
                    </small>

                </div>


                <div class="activity">

                    <b>
                        Request MT-0031 updated
                    </b>

                    <small>
                        7:45 AM
                    </small>

                </div>

            </aside>

        </div>

    </div>

</body>

</html>