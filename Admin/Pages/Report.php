<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="stylesheet" href="../Components/css/Report.css">
</head>
<body>
    <?php include '../Components/NavBar.php'; ?>
    <div class="container">
        <header>
            <div class="title-container">
                <h1>Reports</h1>
                <p>Maintenance Reporting Portal</p>
            </div>
            <div class="user-info">
                <span>Admin User</span>
            </div>
        </header>

        <section class="report-type-section">
            <h2 class="section-title">Report type</h2>
            <div class="report-cards">
                <div class="report-card">
                    <h3>Summary</h3>
                    <p>Overview of requests, statuses, and activity</p>
                    <button class="btn-select">Select</button>
                </div>
                <div class="report-card">
                    <h3>Status</h3>
                    <p>Request counts by current status</p>
                    <button class="btn-select">Select</button>
                </div>
                <div class="report-card">
                    <h3>Recurring Issues</h3>
                    <p>Equipment with repeat maintenance concerns</p>
                    <button class="btn-select">Select</button>
                </div>
                <div class="report-card">
                    <h3>Completed Activities</h3>
                    <p>Completed requests and logged actions</p>
                    <button class="btn-select">Select</button>
                </div>
            </div>
        </section>

        <div class="main-content">
            <aside class="parameters-panel card">
                <h2>Parameters</h2>
                <div class="form-group">
                    <label>Period</label>
                    <input type="text" value="September 2026" readonly>
                </div>
                <div class="form-group">
                    <label>Laboratory</label>
                    <input type="text" value="All labs" readonly>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" value="All categories" readonly>
                </div>
                <button class="btn-primary full-width">Generate report</button>
            </aside>

            <main class="preview-panel card">
                <h2>Preview</h2>
                <div class="skeleton-container">
                    <div class="skeleton-row"><div class="sk-box"></div><div class="sk-box"></div><div class="sk-box"></div></div>
                    <div class="skeleton-row"><div class="sk-box"></div><div class="sk-box"></div><div class="sk-box"></div></div>
                    <div class="skeleton-row"><div class="sk-box"></div><div class="sk-box"></div><div class="sk-box"></div></div>
                    <div class="skeleton-row"><div class="sk-box"></div><div class="sk-box"></div><div class="sk-box"></div></div>
                    <div class="skeleton-row"><div class="sk-box"></div><div class="sk-box"></div><div class="sk-box"></div></div>
                    <div class="skeleton-row"><div class="sk-box"></div><div class="sk-box"></div><div class="sk-box"></div></div>
                    <div class="skeleton-row"><div class="sk-box"></div><div class="sk-box"></div><div class="sk-box"></div></div>
                </div>
                <div class="action-footer">
                    <button class="btn-secondary">Export PDF</button>
                    <button class="btn-secondary">Export CSV</button>
                    <button class="btn-primary">Print</button>
                </div>
            </main>
        </div>
    </div>
</body>
</html>