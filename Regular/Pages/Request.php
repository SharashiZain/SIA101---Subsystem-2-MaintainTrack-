<?php require_once '../../Components/layout.php'; ?>
<!DOCTYPE html>
<html lang="en" <?= rolePreferenceAttributes('regular') ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details</title>
    <link rel="stylesheet" href="../../Components/css/base.css">
    <link rel="stylesheet" href="../Components/css/Request.css">
</head>

<body>
<?php include '../Components/NavBar.php'; ?>
    <main class="request-page">

        <header class="top-header">
            <div>
                <h1>Request Details</h1>
                <p>Maintenance Reporting Portal • Barangay Gulod</p>
            </div>
        </header>


        <section class="request-container">

            <!-- LEFT SIDE -->
            <div class="left-column">

                <!-- REQUEST ID -->
                <div class="request-id-card">
                    <h2>Request ID</h2>
                    <span class="request-id-value">ID#0000</span>
                </div>


                <!-- MAINTENANCE DETAILS -->
                <div class="maintenance-card">

                    <h2>Maintenance Details</h2>

                    <div class="details-grid">

                        <div class="detail-box">
                            <span class="detail-label">Concern</span>
                            <span class="detail-value">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">—</span>
                        </div>

                        <div class="detail-box">
                            <span class="detail-label">Facility</span>
                            <span class="detail-value">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Equipment</span>
                            <span class="detail-value">—</span>
                        </div>

                        <div class="detail-box">
                            <span class="detail-label">Specific Area</span>
                            <span class="detail-value">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Date Reported</span>
                            <span class="detail-value">—</span>
                        </div>

                        <div class="detail-box">
                            <span class="detail-label">Technician</span>
                            <span class="detail-value">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">—</span>
                        </div>

                    </div>

                    <div class="description-box">
                        <span class="detail-label">Description</span>
                        <span class="detail-value">—</span>
                    </div>

                </div>

            </div>


            <!-- RIGHT SIDE -->
            <div class="right-column">

                <div class="right-card">
                    <div class="card-title">Attached Photo</div>
                    <div class="card-placeholder">
                        <svg viewBox="0 0 24 24" width="34" height="34">
                            <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                            <circle cx="9" cy="9.5" r="1.5"></circle>
                            <path d="M5 18l5-5.5 3 3.5 2.5-3 3.5 5"></path>
                        </svg>
                        <span>No image attached</span>
                    </div>
                </div>

                <div class="right-card small">
                    <div class="card-title">Updates</div>
                    <div class="card-placeholder">
                        <span>No updates yet</span>
                    </div>
                </div>

            </div>

        </section>

    </main>

</body>
</html>