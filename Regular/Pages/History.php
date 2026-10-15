<?php
require_once '../../Components/layout.php';

/* All request data lives here */
$requests = [
    [
        'id'          => 'MT-2026-001',
        'title'       => 'Broken Aircon',
        'location'    => 'Barangay Hall · 2nd Floor',
        'date'        => 'Sept 10, 2026',
        'technician'  => 'Pedro Santos',
        'status'      => 'In Progress',
        'statusClass' => 'in-progress',
    ],
    [
        'id'          => 'MT-2026-002',
        'title'       => 'Printer Not Working',
        'location'    => 'Barangay Hall · Records Office',
        'date'        => 'Sept 9, 2026',
        'technician'  => 'Pending Assignment',
        'status'      => 'Pending',
        'statusClass' => 'pending',
    ],
    [
        'id'          => 'MT-2026-003',
        'title'       => 'Broken Light',
        'location'    => 'Covered Court · Main Area',
        'date'        => 'Sept 7, 2026',
        'technician'  => 'Carlo Reyes',
        'status'      => 'Completed',
        'statusClass' => 'completed',
    ],
];

renderHead('History', 'History.css', 'regular');
?>
<div class="page">

    

    <section class="history-card">
<?php renderPageHeader('My History', 'Maintenance Reporting Portal · Barangay Gulod', 'regular'); ?>
        <div class="history-controls">
            <div class="search-box">
                <input type="text" placeholder="Search requests..." id="historySearch">
                <span class="search-icon">⌕</span>
            </div>

            <select class="filter-select" id="historyFilter">
                <option value="">All Statuses</option>
                <option value="in-progress">In Progress</option>
                <option value="pending">Pending</option>
                <option value="completed">Completed</option>
            </select>

            <button class="reset-button" type="button" id="historyReset">Reset</button>

            <button class="request-button" type="button" onclick="window.location.href='Report.php'">
                Report an Issue
            </button>
        </div>

        <div class="request-list" id="requestList">
            <?php foreach ($requests as $req): ?>
                <div class="request-card"
                     data-status="<?= e($req['statusClass']) ?>"
                     onclick="window.location.href='Request.php?id=<?= e($req['id']) ?>'">
                    <div class="request-main">
                        <div class="request-id"><?= e($req['id']) ?></div>
                        <div class="request-title"><?= e($req['title']) ?></div>
                        <div class="request-details"><?= e($req['location']) ?> · <?= e($req['date']) ?></div>
                        <div class="request-tech">Technician: <em><?= e($req['technician']) ?></em></div>
                    </div>
                    <div class="request-side">
                        <span class="status <?= e($req['statusClass']) ?>"><?= e($req['status']) ?></span>
                        <span class="chevron" aria-hidden="true">›</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="noResults" style="display: none; text-align: center; padding: 40px; color: #687985;">
            <p style="font-size: 15px; font-weight: 600;">No requests found matching your search.</p>
        </div>

    </section>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('historySearch');
        const filter = document.getElementById('historyFilter');
        const reset = document.getElementById('historyReset');
        const noResults = document.getElementById('noResults');
        const cards = document.querySelectorAll('.request-card');

        function applyFilters() {
            const q = search.value.toLowerCase();
            const s = filter.value;
            let visible = 0;

            cards.forEach(function (card) {
                const text = card.textContent.toLowerCase();
                const status = card.dataset.status || '';
                const show = (!q || text.includes(q)) && (!s || status === s);
                card.style.display = show ? 'flex' : 'none';
                if (show) visible++;
            });

            noResults.style.display = visible === 0 ? 'block' : 'none';
        }

        search.addEventListener('input', applyFilters);
        filter.addEventListener('change', applyFilters);
        reset.addEventListener('click', function () {
            search.value = '';
            filter.value = '';
            applyFilters();
        });
    });
</script>
<?php renderFoot(); ?>
</html>