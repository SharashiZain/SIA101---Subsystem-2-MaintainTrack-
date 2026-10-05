<?php require_once '../../Components/layout.php'; ?>
<!DOCTYPE html>
<html lang="en" <?= rolePreferenceAttributes('regular') ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaintainTrack | Report</title>
    <link rel="stylesheet" href="../../Components/css/base.css">
    <link rel="stylesheet" href="../Components/css/Report.css">
</head>

<body>
<?php include '../Components/NavBar.php'; ?>
    <main class="page">

        <header class="top-header">
            <div>
                <h1>Report</h1>
                <p>Maintenance Reporting Portal · Barangay Gulod</p>
            </div>
        </header>


        <form class="report-card">

            <div class="section-title">
                Concern Details
            </div>

            <div class="form-row">

                <div class="form-group">
                    <label for="concern">Maintenance Concern</label>
                    <input
                        type="text"
                        id="concern"
                        name="concern"
                        placeholder="e.g Broken Aircon"
                    >
                </div>

                <div class="form-group">
                    <label for="category">Category</label>

                    <select id="category" name="category">
                        <option value=""></option>
                        <option value="electrical">Electrical</option>
                        <option value="plumbing">Plumbing</option>
                        <option value="equipment">Equipment</option>
                        <option value="facility">Facility</option>
                        <option value="other">Other</option>
                    </select>
                </div>

            </div>


            <div class="section-title">
                Facility & Equipment Reference
            </div>


            <div class="form-row">

                <div class="form-group">
                    <label for="facility">Facility</label>

                    <select id="facility" name="facility">
                        <option value=""></option>
                        <option value="barangay-hall">Barangay Hall</option>
                        <option value="records-office">Records Office</option>
                        <option value="multipurpose-hall">Multi-Purpose Hall</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="equipment">Equipment</label>

                    <select id="equipment" name="equipment">
                        <option value=""></option>
                        <option value="aircon">Air Conditioner</option>
                        <option value="printer">Printer</option>
                        <option value="computer">Computer</option>
                        <option value="light">Light</option>
                    </select>
                </div>

            </div>


            <div class="form-row">

                <div class="form-group">
                    <label for="area">Specific Area</label>

                    <select id="area" name="area">
                        <option value=""></option>
                        <option value="first-floor">First Floor</option>
                        <option value="second-floor">Second Floor</option>
                        <option value="office">Office</option>
                        <option value="hallway">Hallway</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">Date</label>

                    <div class="date-input">
                        <input
                            type="date"
                            id="date"
                            name="date"
                        >
                    </div>
                </div>

            </div>


            <div class="form-group description-group">
                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                ></textarea>
            </div>


            <div class="form-group upload-group">
                <label for="image">Upload an image</label>

                <label class="upload-box" for="image">

                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept="image/*"
                    >

                    <div class="upload-icon">
                        <svg viewBox="0 0 24 24">
                            <rect x="5" y="3" width="14" height="18" rx="2"></rect>
                            <circle cx="9" cy="9" r="1.5"></circle>
                            <path d="M6.5 18l4.5-5 2.5 3 2-2.5 2 4.5"></path>
                        </svg>
                    </div>

                    <div class="upload-text">
                        <strong>Click to upload</strong>
                        <span>Attach a photo of the problem (optional)</span>
                    </div>

                </label>
            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="cancel-button"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="submit-button"
                >
                    Submit
                </button>

            </div>

        </form>

    </main>

</body>
</html>