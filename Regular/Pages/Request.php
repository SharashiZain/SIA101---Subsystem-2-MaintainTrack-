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

            <div class="left-column">

                <div class="request-id-card">
                    <h2>Request ID</h2>
                    <span class="request-id-value" id="requestId">ID#0000</span>
                </div>

                <div class="maintenance-card">

                    <div class="maintenance-header">
                        <h2>Maintenance Details</h2>
                        <button type="button" class="edit-details-btn" id="editDetailsBtn">✎ Edit Details</button>
                    </div>

                    <div class="details-grid">
                        <div class="detail-box">
                            <span class="detail-label">Concern</span>
                            <span class="detail-value" id="detailConcern">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Category</span>
                            <span class="detail-value" id="detailCategory">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Facility</span>
                            <span class="detail-value" id="detailFacility">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Equipment</span>
                            <span class="detail-value" id="detailEquipment">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Specific Area</span>
                            <span class="detail-value" id="detailArea">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Date Reported</span>
                            <span class="detail-value" id="detailDate">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Technician</span>
                            <span class="detail-value" id="detailTechnician">—</span>
                        </div>
                        <div class="detail-box">
                            <span class="detail-label">Status</span>
                            <span class="detail-value" id="detailStatus">—</span>
                        </div>
                    </div>

                    <div class="description-box">
                        <span class="detail-label">Description</span>
                        <span class="detail-value" id="detailDescription">—</span>
                    </div>

                    <div class="edit-form" id="editForm" style="display: none;">
                        <div class="edit-grid">
                            <div class="edit-field">
                                <label for="editConcern">Concern</label>
                                <input type="text" id="editConcern" placeholder="Enter concern">
                            </div>
                            <div class="edit-field">
                                <label for="editCategory">Category</label>
                                <select id="editCategory">
                                    <option value="">Select category</option>
                                    <option value="Electrical">Electrical</option>
                                    <option value="Plumbing">Plumbing</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Facility">Facility</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="edit-field">
                                <label for="editFacility">Facility</label>
                                <select id="editFacility">
                                    <option value="">Select facility</option>
                                    <option value="Barangay Hall">Barangay Hall</option>
                                    <option value="Records Office">Records Office</option>
                                    <option value="Multi-Purpose Hall">Multi-Purpose Hall</option>
                                    <option value="Covered Court">Covered Court</option>
                                </select>
                            </div>
                            <div class="edit-field">
                                <label for="editEquipment">Equipment</label>
                                <input type="text" id="editEquipment" placeholder="Enter equipment">
                            </div>
                            <div class="edit-field">
                                <label for="editArea">Specific Area</label>
                                <input type="text" id="editArea" placeholder="Enter area">
                            </div>
                            <div class="edit-field">
                                <label for="editDate">Date Reported</label>
                                <input type="date" id="editDate">
                            </div>
                            <div class="edit-field">
                                <label for="editTechnician">Technician</label>
                                <input type="text" id="editTechnician" placeholder="Enter technician">
                            </div>
                            <div class="edit-field">
                                <label for="editStatus">Status</label>
                                <select id="editStatus">
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div class="edit-description">
                            <label for="editDescription">Description</label>
                            <textarea id="editDescription" placeholder="Enter description"></textarea>
                        </div>
                    </div>

                    <div class="submit-edit-actions" id="submitEditActions" style="display: none;">
                        <button type="button" class="cancel-edit-btn" id="cancelEditBtn">Cancel</button>
                        <button type="button" class="submit-edit-btn" id="submitEditBtn">Submit Changes</button>
                    </div>

                </div>

            </div>

            <div class="right-column">
                <div class="right-card">
                    <div class="card-title">Attached Photo</div>
                    <div class="card-placeholder" id="photoPlaceholder">
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
                    <div class="card-placeholder" id="updatesPlaceholder">
                        <span>No updates yet</span>
                    </div>
                </div>
            </div>

        </section>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const params = new URLSearchParams(window.location.search);
            const requestId = params.get('id') || 'MT-00124';

            document.getElementById('requestId').textContent = 'ID#' + requestId;

            const staticData = {
                'MT-00124': {
                    concern: 'Broken Aircon',
                    category: 'Facility',
                    facility: 'Barangay Hall',
                    equipment: 'Air Conditioning Unit #2',
                    area: '2nd Floor',
                    date: '2026-09-10',
                    technician: 'Pedro Santos',
                    status: 'In Progress',
                    description: 'Aircon unit is not cooling properly and making unusual noise. Needs inspection and possible repair.',
                },
                'MT-00122': {
                    concern: 'Printer Not Working',
                    category: 'Equipment',
                    facility: 'Barangay Hall',
                    equipment: 'Printer',
                    area: 'Records Office',
                    date: '2026-09-09',
                    technician: 'Pending Assignment',
                    status: 'Pending',
                    description: 'Printer is not responding to print commands. Paper feed may be jammed.',
                },
                'MT-00118': {
                    concern: 'Broken Light',
                    category: 'Electrical',
                    facility: 'Covered Court',
                    equipment: 'Light Fixture',
                    area: 'Main Court',
                    date: '2026-09-07',
                    technician: 'Carlo Reyes',
                    status: 'Completed',
                    description: 'Light fixture flickering and eventually stopped working. Replaced ballast and bulbs.',
                },
            };

            const data = staticData[requestId] || staticData['MT-00124'];

            function formatDate(dateStr) {
                if (!dateStr) return '—';
                const d = new Date(dateStr);
                if (isNaN(d)) return dateStr;
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }

            function fillView() {
                document.getElementById('detailConcern').textContent = data.concern;
                document.getElementById('detailCategory').textContent = data.category;
                document.getElementById('detailFacility').textContent = data.facility;
                document.getElementById('detailEquipment').textContent = data.equipment;
                document.getElementById('detailArea').textContent = data.area;
                document.getElementById('detailDate').textContent = formatDate(data.date);
                document.getElementById('detailTechnician').textContent = data.technician;
                document.getElementById('detailStatus').textContent = data.status;
                document.getElementById('detailDescription').textContent = data.description;
            }

            function fillEdit() {
                document.getElementById('editConcern').value = data.concern;
                document.getElementById('editCategory').value = data.category;
                document.getElementById('editFacility').value = data.facility;
                document.getElementById('editEquipment').value = data.equipment;
                document.getElementById('editArea').value = data.area;
                document.getElementById('editDate').value = data.date;
                document.getElementById('editTechnician').value = data.technician;
                document.getElementById('editStatus').value = data.status;
                document.getElementById('editDescription').value = data.description;
            }

            fillView();

            const editBtn = document.getElementById('editDetailsBtn');
            const editForm = document.getElementById('editForm');
            const submitActions = document.getElementById('submitEditActions');
            const detailBoxes = document.querySelectorAll('.detail-box, .description-box');

            editBtn.addEventListener('click', function () {
                fillEdit();
                editForm.style.display = 'block';
                submitActions.style.display = 'flex';
                detailBoxes.forEach(b => b.style.display = 'none');
                editBtn.style.display = 'none';
            });

            document.getElementById('cancelEditBtn').addEventListener('click', function () {
                editForm.style.display = 'none';
                submitActions.style.display = 'none';
                detailBoxes.forEach(b => b.style.display = 'flex');
                editBtn.style.display = 'inline-flex';
            });

            document.getElementById('submitEditBtn').addEventListener('click', function () {
                const concern = document.getElementById('editConcern').value.trim();
                const category = document.getElementById('editCategory').value;
                const facility = document.getElementById('editFacility').value;
                const description = document.getElementById('editDescription').value.trim();

                if (!concern || !category || !facility || !description) {
                    alert('Please fill in all required fields.');
                    return;
                }

                data.concern = concern;
                data.category = category;
                data.facility = facility;
                data.equipment = document.getElementById('editEquipment').value.trim();
                data.area = document.getElementById('editArea').value.trim();
                data.date = document.getElementById('editDate').value;
                data.technician = document.getElementById('editTechnician').value.trim();
                data.status = document.getElementById('editStatus').value;
                data.description = description;

                fillView();

                editForm.style.display = 'none';
                submitActions.style.display = 'none';
                detailBoxes.forEach(b => b.style.display = 'flex');
                editBtn.style.display = 'inline-flex';

                alert('Request details updated successfully!');
            });
        });
    </script>

</body>
</html>