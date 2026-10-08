<?php
require_once '../../Components/layout.php';

/* ---- Data arrays ---- */
$stats = [
    ['label' => 'My Task',     'value' => 12, 'filter' => ''],
    ['label' => 'Pending',     'value' => 4,  'filter' => 'pending'],
    ['label' => 'In Progress', 'value' => 3,  'filter' => 'progress'],
    ['label' => 'Completed',   'value' => 5,  'filter' => 'completed'],
];

$tasks = [
    [
        'title'    => 'Broken Aircon',
        'priority' => 'High',
        'tagClass' => 'tag-high',
        'ref'      => 'MT-10101 · Barangay Hall · 3rd Floor',
        'desc'     => 'Aircon unit is making unusual rattling noises and fails to produce cold air. Requires immediate inspection.',
        'chip'     => 'Aircon',
        'date'     => 'Sept 11, 2026',
        'status'   => 'pending',
        'facility' => 'Barangay Hall',
        'id'       => 'MT-10101',
    ],
    [
        'title'    => 'Broken Aircon',
        'priority' => 'Medium',
        'tagClass' => 'tag-mid',
        'ref'      => 'MT-10102 · Barangay Hall · 2nd Floor',
        'desc'     => 'Routine filter replacement and coolant level check needed for continuous operation.',
        'chip'     => 'Aircon',
        'date'     => 'Sept 11, 2026',
        'status'   => 'progress',
        'facility' => 'Barangay Hall',
        'id'       => 'MT-10102',
    ],
    [
        'title'    => 'Broken Aircon',
        'priority' => 'Low',
        'tagClass' => 'tag-low',
        'ref'      => 'MT-10103 · Barangay Hall · Ground Floor',
        'desc'     => 'Minor cosmetic damage on vent cover, no operational malfunction reported.',
        'chip'     => 'Aircon',
        'date'     => 'Sept 11, 2026',
        'status'   => 'completed',
        'facility' => 'Barangay Hall',
        'id'       => 'MT-10103',
    ],
];

renderHead('My Tasks', 'Task.css', 'maintenance');
?>
<div class="page">

    <?php renderPageHeader('My Tasks', 'Maintenance Personnel Portal • Barangay Gulod', 'maintenance'); ?>

    <!-- STAT CARDS (Clickable) -->
    <section class="stat-grid">
        <?php foreach ($stats as $item): ?>
            <div class="stat-card" data-filter="<?= e($item['filter']) ?>" style="cursor: pointer;">
                <span class="stat-label"><?= e($item['label']) ?></span>
                <span class="stat-value"><?= e($item['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- FILTER BAR -->
    <section class="filter-bar">
        <div class="search-input">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" placeholder="Search task..." id="taskSearch">
        </div>
        
        <select class="filter-select" id="taskStatusFilter">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="progress">In Progress</option>
            <option value="completed">Completed</option>
        </select>

        <select class="filter-select" id="taskFacilityFilter">
            <option value="">All Facilities</option>
            <option value="Barangay Hall">Barangay Hall</option>
            <option value="Records Office">Records Office</option>
            <option value="Multi-Purpose Hall">Multi-Purpose Hall</option>
            <option value="Covered Court">Covered Court</option>
        </select>

        <button class="reset-btn" type="button" id="taskReset">Reset</button>
    </section>

    <!-- TASK LIST -->
    <section class="task-list" id="taskList">
        <?php foreach ($tasks as $task): ?>
            <article class="task-card" 
                     data-status="<?= e($task['status']) ?>" 
                     data-facility="<?= e($task['facility']) ?>"
                     data-id="<?= e($task['id']) ?>">
                <div class="task-card-top">
                    <h3><?= e($task['title']) ?></h3>
                    <span class="tag <?= e($task['tagClass']) ?>"><?= e($task['priority']) ?></span>
                </div>
                <span class="task-ref"><?= e($task['ref']) ?></span>
                <p class="task-desc"><?= e($task['desc']) ?></p>
                <div class="task-card-bottom">
                    <div class="task-meta">
                        <span class="meta-chip"><?= e($task['chip']) ?></span>
                        <span class="meta-date"><?= e($task['date']) ?></span>
                    </div>
                    <button class="btn-primary" type="button" onclick="event.stopPropagation(); window.location.href='Request.php?id=<?= e($task['id']) ?>'">View Details</button>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <!-- NO RESULTS MESSAGE -->
    <div id="taskNoResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
        <p style="font-size: 15px; font-weight: 600;">No tasks found matching your filters.</p>
    </div>

    <!-- PAGINATION -->
    <nav class="pagination" aria-label="Tasks pagination">
        <button class="page-num active" type="button" data-page="1">1</button>
        <button class="page-num" type="button" data-page="2">2</button>
        <button class="page-num" type="button" data-page="3">3</button>
        <button class="page-num" type="button" data-page="4">4</button>
        <button class="page-num" type="button" data-page="5">5</button>
        <button class="page-num" type="button" data-page="6">6</button>
    </nav>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('taskSearch');
        const statusFilter = document.getElementById('taskStatusFilter');
        const facilityFilter = document.getElementById('taskFacilityFilter');
        const resetBtn = document.getElementById('taskReset');
        const noResults = document.getElementById('taskNoResults');
        const taskCards = document.querySelectorAll('.task-card');

        /* ---- Filter function ---- */
        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const status = statusFilter.value;
            const facility = facilityFilter.value;
            let visibleCount = 0;

            taskCards.forEach(function (card) {
                const text = card.textContent.toLowerCase();
                const cardStatus = card.dataset.status || '';
                const cardFacility = card.dataset.facility || '';

                const matchesSearch = text.includes(query);
                const matchesStatus = !status || cardStatus === status;
                const matchesFacility = !facility || cardFacility === facility;

                if (matchesSearch && matchesStatus && matchesFacility) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        /* ---- Live search ---- */
        searchInput.addEventListener('input', applyFilters);

        /* ---- Dropdown filters ---- */
        statusFilter.addEventListener('change', applyFilters);
        facilityFilter.addEventListener('change', applyFilters);

        /* ---- Reset ---- */
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            statusFilter.value = '';
            facilityFilter.value = '';
            applyFilters();
        });

        /* ---- Clickable Stat Cards ---- */
        document.querySelectorAll('.stat-card[data-filter]').forEach(function (card) {
            card.addEventListener('click', function () {
                const filter = this.dataset.filter;
                statusFilter.value = filter;
                applyFilters();
                document.getElementById('taskList').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        /* ---- Clickable Task Cards ---- */
        taskCards.forEach(function (card) {
            card.addEventListener('click', function () {
                const id = this.dataset.id;
                if (id) {
                    window.location.href = 'Request.php?id=' + encodeURIComponent(id);
                }
            });
        });

        /* ---- Pagination Highlight ---- */
        document.querySelectorAll('.page-num').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.page-num').forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    });
</script>
<?php renderFoot(); ?>
</html>