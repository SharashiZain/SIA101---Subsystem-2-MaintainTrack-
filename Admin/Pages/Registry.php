<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Registry</title>
    <link rel="stylesheet" href="../Components/css/Registry.css">
</head>
<body>
    <?php include '../Components/NavBar.php'; ?>
    <div class="container">
        <header>
            <div class="title-container">
                <h1>Equipment Registry</h1>
                <p>Maintenance Reporting Portal</p>
            </div>
            <div class="user-info">
                <span>Admin User</span>
            </div>
        </header>

        <main class="card">
            <div class="card-header">
                <input type="text" placeholder="Search equipment ID or type" class="search-bar">
                <button class="add-btn">+ Add Equipment</button>
            </div>

            <table class="equipment-table">
                <thead>
                    <tr>
                        <th>Equipment ID</th>
                        <th>Type</th>
                        <th>Lab</th>
                        <th>Status</th>
                        <th>Total Repairs</th>
                        <th>Recurring</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="font-bold">PC-L1-01</td>
                        <td class="font-bold">Computer Unit</td>
                        <td class="text-gray">Lab 1 (B1)</td>
                        <td class="text-gray">Active</td>
                        <td class="text-gray">4</td>
                        <td><span class="badge badge-recurring">Recurring</span></td>
                    </tr>
                    <tr>
                        <td class="font-bold">AC-L3-02</td>
                        <td class="font-bold">Air Conditioning</td>
                        <td class="text-gray">Lab 3 (B1)</td>
                        <td class="text-gray">Active</td>
                        <td class="text-gray">2</td>
                        <td class="text-gray">—</td>
                    </tr>
                    <tr>
                        <td class="font-bold">Router-L2-01</td>
                        <td class="font-bold">Networking</td>
                        <td class="text-gray">Lab 2 (B1)</td>
                        <td class="text-gray">Active</td>
                        <td class="text-gray">1</td>
                        <td class="text-gray">—</td>
                    </tr>
                    <tr>
                        <td class="font-bold">PC-L1-07</td>
                        <td class="font-bold">Computer Unit</td>
                        <td class="text-gray">Lab 1 (B1)</td>
                        <td class="text-gray">Active</td>
                        <td class="text-gray">0</td>
                        <td class="text-gray">—</td>
                    </tr>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>