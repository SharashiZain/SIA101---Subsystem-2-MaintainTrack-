<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance History</title>
    <link rel="stylesheet" href="../Components/css/History.css">
</head>

<body>
<?php include '../Components/NavBar.php'; ?>
    <main class="history-page">

        <header class="top-header">
            <div>
                <h1>Maintenance History</h1>
                <p>Maintenance Reporting Portal • Barangay Gulod</p>
            </div>
        </header>


        <section class="history-card">

            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-title">My Request</div>
                    <div class="stat-number">10</div>
                    <div class="stat-description">All submitted requests</div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <path d="M8 4h8a2 2 0 0 1 2 2v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V6a2 2 0 0 1 2-2z"></path>
                            <path d="M9 9h6M9 13h6M9 17h3"></path>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Complete</div>
                    <div class="stat-number complete">3</div>
                    <div class="stat-description">All Completed requests</div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M8 12.5l3 3 5-6"></path>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">In Progress</div>
                    <div class="stat-number progress">5</div>
                    <div class="stat-description">All In Progress requests</div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <path d="M20 12a8 8 0 1 1-2.3-5.7"></path>
                            <path d="M20 4v4h-4"></path>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Pending</div>
                    <div class="stat-number pending">2</div>
                    <div class="stat-description">All Pending requests</div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 7v5l3 2"></path>
                        </svg>
                    </div>
                </div>

            </div>


            <div class="history-controls">

                <div class="search-box">
                    <input type="text" placeholder="Search requests...">
                    <span class="search-icon">⌕</span>
                </div>

                <select class="filter-select">
                    <option>Filter</option>
                    <option>Complete</option>
                    <option>In Progress</option>
                    <option>Pending</option>
                </select>

                <button class="reset-button">
                    Reset
                </button>

                <button class="request-button">
                    Submit Maintenance Request
                </button>

            </div>


            <div class="request-list">

                <div class="request-card">

                    <div class="request-title">
                        ID#0000 - BROKEN PRINTER
                    </div>

                    <div class="request-details">
                        Admin Office - 2nd Floor
                    </div>

                    <div class="request-tags">

                        <span class="tag">
                            <span class="tag-icon"></span>
                            Printer
                        </span>

                        <span class="tag">
                            Sept 11, 2001
                        </span>

                        <span class="tag">
                            Technician:
                            <strong>NOE KO ALAM</strong>
                        </span>

                    </div>

                </div>

            </div>

        </section>

    </main>

</body>
</html>