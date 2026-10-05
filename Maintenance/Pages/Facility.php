<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$equipmentList = [
    [
        'name'        => 'Aircon Unit 02',
        'location'    => 'A2T-001 · Barangay Hall · 2nd Floor',
        'tags'        => ['Equipment', '2 yrs old', '8 Records'],
        'statusLabel' => 'Current Task:',
        'statusValue' => 'MT-C0134 · Broken Aircon',
        'actionText'  => 'View Task',
        'actionHref'  => 'Request.php',
    ],
    [
        'name'        => 'Aircon Unit 03',
        'location'    => 'A2T-001 · Barangay Hall · 2nd Floor',
        'tags'        => ['Equipment', '6 yrs old', '8 Records'],
        'statusLabel' => 'Last Serviced:',
        'statusValue' => 'Sept 8, 2026',
        'actionText'  => 'View History',
        'actionHref'  => 'Request.php',
    ],
];

renderHead('Facilities & Equipment', 'Facility.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('Facilities & Equipment', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <section class="filter-bar">
        <div class="search-input">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" placeholder="Search equipment...">
        </div>
        <button class="reset-btn" type="button">Reset</button>
        <button class="filter-btn" type="button">All Facilities
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="filter-btn" type="button">All Category
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </section>

    <section class="equip-grid">
        <?php foreach ($equipmentList as $item): ?>
            <article class="equip-card">
                <h3><?= e($item['name']) ?></h3>
                <span class="equip-loc"><?= e($item['location']) ?></span>
                <div class="equip-tags">
                    <?php foreach ($item['tags'] as $tag): ?>
                        <span class="meta-chip"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="equip-status">
                    <span><?= e($item['statusLabel']) ?></span> <?= e($item['statusValue']) ?>
                </div>
                <button class="btn-primary" type="button" onclick="window.location.href='<?= e($item['actionHref']) ?>'"><?= e($item['actionText']) ?></button>
            </article>
        <?php endforeach; ?>
    </section>

</div>
<?php renderFoot(); ?>
</html>