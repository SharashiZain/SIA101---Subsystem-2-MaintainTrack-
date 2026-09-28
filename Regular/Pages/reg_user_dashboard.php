<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard</title>
    <link rel="stylesheet" href="../Components/css/reg_user_dashboard.css">
    <link rel="stylesheet" href="../Components/css/Navbar.css">

</head>

<body class="dashboard-page">
<?php include '../Components/NavBar.php'; ?>
<div class="app">

    <!-- =========================
         SIDEBAR
    ========================== -->
  
    

    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="main-content">

        <!-- TOP HEADER -->
        <header class="top-header">
            <div class="page-heading">
                <h1>
                    My Dashboard
                </h1>
                <p>
                    Maintenance Reporting Portal • Barangay Gulod
                </p>
            </div>
        </header>


        <!-- =========================
             WELCOME BANNER
        ========================== -->

        <section class="welcome-card">

            <div class="welcome-content">

                <h2>
                    Good Morning, Juan!
                </h2>

                <p>
                    May maintenance concern? Report it here and track its progress.
                </p>

                <button
                    type="button"
                    class="report-button"
                    id="reportButton"
                >
                    <span>＋</span>
                    Report a Maintenance Concern
                </button>

            </div>

        </section>



        <!-- =========================
             STAT CARDS
        ========================== -->

        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-content">

                    <span class="stat-title">
                        MY REQUESTS
                    </span>

                    <strong class="stat-number">
                        5
                    </strong>

                    <span class="stat-description">
                        All submitted requests
                    </span>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-content">

                    <span class="stat-title">
                        PENDING
                    </span>

                    <strong class="stat-number pending-number">
                        1
                    </strong>

                    <span class="stat-description">
                        Waiting for assignment
                    </span>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-content">

                    <span class="stat-title">
                        IN PROGRESS
                    </span>

                    <strong class="stat-number progress-number">
                        1
                    </strong>

                    <span class="stat-description">
                        Currently being handled
                    </span>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-content">

                    <span class="stat-title">
                        COMPLETED
                    </span>

                    <strong class="stat-number completed-number">
                        3
                    </strong>

                    <span class="stat-description">
                        Successfully resolved
                    </span>

                </div>

            </div>

        </section>



        <!-- =========================
             LOWER DASHBOARD
        ========================== -->

        <section class="dashboard-grid">


            <!-- =========================
                 RECENT REQUESTS
            ========================== -->

            <div class="dashboard-panel recent-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            My Recent Requests
                        </h2>

                    </div>

                    <span class="total-count">
                        3 total
                    </span>

                </div>


                <div class="request-list">


                    <!-- REQUEST 1 -->
                    <div class="request-card">

                        <div class="request-top">

                            <strong>
                                Broken Aircon
                            </strong>

                            <span class="status-badge in-progress">
                                IN PROGRESS
                            </span>

                        </div>

                        <p>
                            Reported Sep 10, 2026 • Assigned to Pedro Santos
                        </p>

                        <small>
                            MT-00124 • Barangay Hall – 2nd Floor
                        </small>

                    </div>



                    <!-- REQUEST 2 -->
                    <div class="request-card">

                        <div class="request-top">

                            <strong>
                                Printer Not Working
                            </strong>

                            <span class="status-badge pending">
                                PENDING
                            </span>

                        </div>

                        <p>
                            Reported Sep 9, 2026 • Waiting for assignment
                        </p>

                        <small>
                            MT-00122 • Barangay Hall – Records Office
                        </small>

                    </div>



                    <!-- REQUEST 3 -->
                    <div class="request-card">

                        <div class="request-top">

                            <strong>
                                Broken Light
                            </strong>

                            <span class="status-badge completed">
                                COMPLETED
                            </span>

                        </div>

                        <p>
                            Reported Sep 7, 2026 • Completed Sep 8, 2026
                        </p>

                        <small>
                            MT-00118 • Covered Court
                        </small>

                    </div>

                </div>

            </div>



            <!-- =========================
                 REQUEST STATUS SNAPSHOT
            ========================== -->

            <div class="dashboard-panel snapshot-panel">

                <div class="panel-header">

                    <h2>
                        Request Status Snapshot
                    </h2>

                </div>


                <div class="snapshot-list">


                    <!-- SNAPSHOT 1 -->
                    <div class="snapshot-card">

                        <div class="snapshot-title">
                            MT-00124 • Broken Aircon
                        </div>

                        <div class="snapshot-info">
                            In Progress • Assigned to Pedro Santos
                        </div>

                        <div class="progress-track">

                            <div
                                class="progress-fill"
                                style="width: 78%;"
                            ></div>

                        </div>

                    </div>



                    <!-- SNAPSHOT 2 -->
                    <div class="snapshot-card">

                        <div class="snapshot-title">
                            MT-00122 • Printer Not Working
                        </div>

                        <div class="snapshot-info">
                            Pending • Waiting for assignment
                        </div>

                        <div class="progress-track">

                            <div
                                class="progress-fill pending-progress"
                                style="width: 25%;"
                            ></div>

                        </div>

                    </div>



                    <!-- SNAPSHOT 3 -->
                    <div class="snapshot-card">

                        <div class="snapshot-title">
                            MT-00118 • Broken Light
                        </div>

                        <div class="snapshot-info">
                            Completed • Repair finished
                        </div>

                        <div class="progress-track">

                            <div
                                class="progress-fill completed-progress"
                                style="width: 100%;"
                            ></div>

                        </div>

                    </div>

                </div>

            </div>



            <!-- =========================
                 HOW TO REPORT
            ========================== -->

            <div class="dashboard-panel instructions-panel">

                <div class="panel-header">

                    <h2>
                        How to Report a Maintenance Concern
                    </h2>

                </div>


                <div class="instruction-list">


                    <div class="instruction-step">

                        <div class="step-number">
                            1
                        </div>

                        <div class="step-content">

                            <strong>
                                Identify the concern
                            </strong>

                            <p>
                                Observe the facility or equipment that needs maintenance.
                            </p>

                        </div>

                    </div>



                    <div class="instruction-step">

                        <div class="step-number">
                            2
                        </div>

                        <div class="step-content">

                            <strong>
                                Click "Report a Maintenance Concern"
                            </strong>

                            <p>
                                Start a new maintenance request from the dashboard.
                            </p>

                        </div>

                    </div>



                    <div class="instruction-step">

                        <div class="step-number">
                            3
                        </div>

                        <div class="step-content">

                            <strong>
                                Select the facility and equipment
                            </strong>

                            <p>
                                Choose the affected facility, area, and equipment.
                            </p>

                        </div>

                    </div>



                    <div class="instruction-step">

                        <div class="step-number">
                            4
                        </div>

                        <div class="step-content">

                            <strong>
                                Describe the problem
                            </strong>

                            <p>
                                Provide a clear description of the maintenance concern.
                            </p>

                        </div>

                    </div>



                    <div class="instruction-step">

                        <div class="step-number">
                            5
                        </div>

                        <div class="step-content">

                            <strong>
                                Submit the request
                            </strong>

                            <p>
                                Review the details and submit your maintenance request.
                            </p>

                        </div>

                    </div>



                    <div class="instruction-step">

                        <div class="step-number">
                            6
                        </div>

                        <div class="step-content">

                            <strong>
                                Track the request
                            </strong>

                            <p>
                                Monitor the status and maintenance updates through your dashboard.
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>


<script src="reg_user_dashboard.js"></script>

</body>
</html>