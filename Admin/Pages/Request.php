<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Maintenance Requests</title>

    <link rel="stylesheet" href="../Components/css/Request.css">
</head>

<body>

<div class="page">
<?php include '../Components/NavBar.php'; ?>
    <header class="page-header">

        <div>
            <h1>Maintenance Requests</h1>
            <p>Maintenance Reporting Portal</p>
        </div>

        <span class="admin-user">
            Admin User
        </span>

    </header>


    <section class="request-panel">

        <div class="filter-bar">

            <input
                type="text"
                placeholder="Search ID, issue, equipment"
            >

            <button>Status</button>

            <button>Category</button>

            <button>Lab</button>

            <button>Date range</button>

            <button class="new-request">
                + New Request
            </button>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ISSUE / DESCRIPTION</th>
                        <th>EQUIPMENT</th>
                        <th>LOCATION</th>
                        <th>CATEGORY</th>
                        <th>STATUS</th>
                        <th>ASSIGNED TO</th>
                        <th>DATE REPORTED</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>MT-00131</td>
                        <td><b>PC won't turn on</b></td>
                        <td>Computer Unit #1</td>
                        <td>Lab 1 (01)</td>
                        <td>Hardware</td>

                        <td>
                            <span class="status in-progress">
                                In Progress
                            </span>
                        </td>

                        <td>Juan Dela Cruz</td>
                        <td>Sept 19, 2026 9:30 AM</td>
                    </tr>


                    <tr>
                        <td>MT-00132</td>
                        <td><b>Broken aircon</b></td>
                        <td>Air Conditioning Unit #3</td>
                        <td>Lab 3 (01)</td>
                        <td>Facility</td>

                        <td>
                            <span class="status assigned">
                                Assigned
                            </span>
                        </td>

                        <td>Maria Santos</td>
                        <td>Sept 19, 2026 8:45 AM</td>
                    </tr>


                    <tr>
                        <td>MT-00133</td>
                        <td><b>No internet connection</b></td>
                        <td>Switch #2</td>
                        <td>Lab 2 (01)</td>
                        <td>Network</td>

                        <td>
                            <span class="status pending">
                                Pending
                            </span>
                        </td>

                        <td>Unassigned</td>
                        <td>Sept 19, 2026 8:30 AM</td>
                    </tr>


                    <tr>
                        <td>MT-00134</td>
                        <td><b>Software won't launch</b></td>
                        <td>Computer Unit #7</td>
                        <td>Lab 1 (01)</td>
                        <td>Software</td>

                        <td>
                            <span class="status completed">
                                Completed
                            </span>
                        </td>

                        <td>Carlo Reyes</td>
                        <td>Sept 18, 2026 4:10 PM</td>
                    </tr>


                    <tr>
                        <td>MT-00135</td>
                        <td><b>Flickering lights</b></td>
                        <td>Lab 4 Lights</td>
                        <td>Lab 4 (01)</td>
                        <td>Electrical</td>

                        <td>
                            <span class="status pending">
                                Pending
                            </span>
                        </td>

                        <td>Unassigned</td>
                        <td>Sept 18, 2026 2:25 PM</td>
                    </tr>

                </tbody>

            </table>

        </div>


        <div class="empty-lines">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>


        <div class="table-footer">

            <span>
                1–5 of 12 requests
            </span>

            <div class="pagination">

                <button>‹</button>
                <button class="active">1</button>
                <button>2</button>
                <button>›</button>

            </div>

        </div>

    </section>

</div>

</body>
</html>