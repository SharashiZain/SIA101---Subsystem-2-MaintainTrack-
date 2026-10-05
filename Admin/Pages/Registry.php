<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$equipment = [
    ['id' => 'PC-L1-01',     'type' => 'Computer Unit',   'lab' => 'Lab 1 (B1)', 'status' => 'Active', 'repairs' => 4, 'recurring' => true],
    ['id' => 'AC-L3-02',     'type' => 'Air Conditioning', 'lab' => 'Lab 3 (B1)', 'status' => 'Active', 'repairs' => 2, 'recurring' => false],
    ['id' => 'Router-L2-01', 'type' => 'Networking',      'lab' => 'Lab 2 (B1)', 'status' => 'Active', 'repairs' => 1, 'recurring' => false],
    ['id' => 'PC-L1-07',     'type' => 'Computer Unit',   'lab' => 'Lab 1 (B1)', 'status' => 'Active', 'repairs' => 0, 'recurring' => false],
];

renderHead('Equipment Registry', 'Registry.css');
?>
    <div class="container">

        <?php renderPageHeader('Equipment Registry'); ?>

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
                    <?php foreach ($equipment as $item): ?>
                        <tr>
                            <td class="font-bold"><?= e($item['id']) ?></td>
                            <td class="font-bold"><?= e($item['type']) ?></td>
                            <td class="text-gray"><?= e($item['lab']) ?></td>
                            <td class="text-gray"><?= e($item['status']) ?></td>
                            <td class="text-gray"><?= e($item['repairs']) ?></td>
                            <?php if ($item['recurring']): ?>
                                <td><span class="badge badge-recurring">Recurring</span></td>
                            <?php else: ?>
                                <td class="text-gray">—</td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

    </div>
<?php renderFoot(); ?>
