<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assignments & Personnel</title>

    <link rel="stylesheet" href="../Components/css/Assignment.css">

</head>


<body>
<?php include '../Components/NavBar.php'; ?>
<div class="page">


    <!-- HEADER -->

    <header class="page-header">

        <div>

            <h1>
                Assignments & Personnel
            </h1>

            <p>
                Maintenance Reporting Portal
            </p>

        </div>


        <span class="admin-user">
            Admin User
        </span>

    </header>



    <!-- CONTENT -->

    <div class="assignment-grid">


        <!-- IT SUPPORT STAFF -->

        <section class="assignment-card">

            <h2>
                IT Support Staff
            </h2>


            <div class="staff-list">


                <!-- JONES -->

                <div class="staff-item">

                    <div class="staff-info">

                        <div class="profile-circle"></div>

                        <div>

                            <strong>
                                Jones
                            </strong>

                            <small>
                                3 open tasks
                            </small>

                        </div>

                    </div>


                    <button class="workload-button">
                        View workload
                    </button>

                </div>



                <!-- MARCO -->

                <div class="staff-item">

                    <div class="staff-info">

                        <div class="profile-circle"></div>

                        <div>

                            <strong>
                                Marco
                            </strong>

                            <small>
                                2 open tasks
                            </small>

                        </div>

                    </div>


                    <button class="workload-button">
                        View workload
                    </button>

                </div>



                <!-- LEBRON -->

                <div class="staff-item">

                    <div class="staff-info">

                        <div class="profile-circle"></div>

                        <div>

                            <strong>
                                LeBron
                            </strong>

                            <small>
                                1 open task
                            </small>

                        </div>

                    </div>


                    <button class="workload-button">
                        View workload
                    </button>

                </div>


            </div>

        </section>



        <!-- UNASSIGNED REQUESTS -->

        <section class="assignment-card">

            <h2>
                Unassigned Requests
            </h2>


            <div class="unassigned-list">


                <!-- REQUEST 1 -->

                <div class="unassigned-item">

                    <div>

                        <strong>
                            MT-00133
                        </strong>

                        <small>
                            No internet connection
                        </small>

                    </div>


                    <button class="assign-button">
                        Assign / Reassign
                    </button>

                </div>



                <!-- REQUEST 2 -->

                <div class="unassigned-item">

                    <div>

                        <strong>
                            MT-00135
                        </strong>

                        <small>
                            Flickering lights
                        </small>

                    </div>


                    <button class="assign-button">
                        Assign / Reassign
                    </button>

                </div>


            </div>

        </section>


    </div>

</div>

</body>

</html>