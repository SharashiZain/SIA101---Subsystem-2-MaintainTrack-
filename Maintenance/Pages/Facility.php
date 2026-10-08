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
        'category'    => 'Aircon',
        'facility'    => 'Barangay Hall',
    ],
    [
        'name'        => 'Aircon Unit 03',
        'location'    => 'A2T-001 · Barangay Hall · 2nd Floor',
        'tags'        => ['Equipment', '6 yrs old', '8 Records'],
        'statusLabel' => 'Last Serviced:',
        'statusValue' => 'Sept 8, 2026',
        'actionText'  => 'View History',
        'actionHref'  => 'Request.php',
        'category'    => 'Aircon',
        'facility'    => 'Barangay Hall',
    ],
    [
        'name'        => 'Computer Unit 01',
        'location'    => 'CMP-001 · Records Office · Ground Floor',
        'tags'        => ['Equipment', '3 yrs old', '4 Records'],
        'statusLabel' => 'Last Serviced:',
        'statusValue' => 'Aug 25, 2026',
        'actionText'  => 'View History',
        'actionHref'  => 'Request.php',
        'category'    => 'Computer',
        'facility'    => 'Records Office',
    ],
    [
        'name'        => 'Light Fixture A1',
        'location'    => 'LT-012 · Covered Court · Main Area',
        'tags'        => ['Facility', '1 yr old', '2 Records'],
        'statusLabel' => 'Last Serviced:',
        'statusValue' => 'July 15, 2026',
        'actionText'  => 'View History',
        'actionHref'  => 'Request.php',
        'category'    => 'Electrical',
        'facility'    => 'Covered Court',
    ],
];

renderHead('Facilities & Equipment', 'Facility.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('Facilities & Equipment', 'Maintenance Personnel Portal • Barangay Gulod', 'maintenance'); ?>

    <section class="filter-bar">
        <div class="search-input">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" placeholder="Search equipment..." id="facilitySearch">
        </div>
        <button class="reset-btn" type="button" id="facilityReset">Reset</button>
        
        <select class="filter-select" id="facilityFilter">
            <option value="">All Facilities</option>
            <option value="Barangay Hall">Barangay Hall</option>
            <option value="Records Office">Records Office</option>
            <option value="Covered Court">Covered Court</option>
            <option value="Multi-Purpose Hall">Multi-Purpose Hall</option>
        </select>

        <select class="filter-select" id="categoryFilter">
            <option value="">All Category</option>
            <option value="Aircon">Aircon</option>
            <option value="Computer">Computer</option>
            <option value="Electrical">Electrical</option>
            <option value="Plumbing">Plumbing</option>
        </select>
    </section>

    <section class="equip-grid" id="equipGrid">
        <?php foreach ($equipmentList as $item): ?>
            <article class="equip-card" 
                     data-category="<?= e($item['category']) ?>" 
                     data-facility="<?= e($item['facility']) ?>">
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

    <div id="facilityNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
        <p style="font-size: 15px; font-weight: 600;">No equipment found matching your filters.</p>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('facilitySearch');
        const facilityFilter = document.getElementById('facilityFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const resetBtn = document.getElementById('facilityReset');
        const noResults = document.getElementById('facilityNoResults');
        const cards = document.querySelectorAll('.equip-card');

        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const facility = facilityFilter.value;
            const category = categoryFilter.value;
            let visible = 0;

            cards.forEach(function (card) {
                const text = card.textContent.toLowerCase();
                const cardFacility = card.dataset.facility || '';
                const cardCategory = card.dataset.category || '';

                const matchesSearch = text.includes(query);
                const matchesFacility = !facility || cardFacility === facility;
                const matchesCategory = !category || cardCategory === category;

                if (matchesSearch && matchesFacility && matchesCategory) {
                    card.style.display = 'flex';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            noResults.style.display = visible === 0 ? 'block' : 'none';
        }

        searchInput.addEventListener('input', applyFilters);
        facilityFilter.addEventListener('change', applyFilters);
        categoryFilter.addEventListener('change', applyFilters);

        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            facilityFilter.value = '';
            categoryFilter.value = '';
            applyFilters();
        });
    });
</script>
<?php renderFoot(); ?>
</html>