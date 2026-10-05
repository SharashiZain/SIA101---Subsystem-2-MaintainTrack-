<?php
require_once '../../Components/layout.php';

/* ---- Sample data (replace with database queries later) ---- */
$staff = [
    ['name' => 'Jones',  'openTasks' => 3],
    ['name' => 'Marco',  'openTasks' => 2],
    ['name' => 'LeBron', 'openTasks' => 1],
];

$unassigned = [
    ['id' => 'MT-00133', 'issue' => 'No internet connection'],
    ['id' => 'MT-00135', 'issue' => 'Flickering lights'],
];

renderHead('Assignments & Personnel', 'Assignment.css');
?>
<div class="page">

    <?php renderPageHeader('Assignments & Personnel'); ?>

    <div class="assignment-grid">

        <!-- IT SUPPORT STAFF -->
        <section class="assignment-card">
            <h2>IT Support Staff</h2>

            <div class="staff-list">
                <?php foreach ($staff as $member): ?>
                    <div class="staff-item">
                        <div class="staff-info">
                            <div class="profile-circle"></div>
                            <div>
                                <strong><?= e($member['name']) ?></strong>
                                <small><?= e(openTasksLabel($member['openTasks'])) ?></small>
                            </div>
                        </div>
                        <button class="workload-button">View workload</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- UNASSIGNED REQUESTS -->
        <section class="assignment-card">
            <h2>Unassigned Requests</h2>

            <div class="unassigned-list">
                <?php foreach ($unassigned as $request): ?>
                    <div class="unassigned-item">
                        <div>
                            <strong><?= e($request['id']) ?></strong>
                            <small><?= e($request['issue']) ?></small>
                        </div>
                        <button class="assign-button">
                            Assign / Reassign
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
</div>
<?php renderFoot(); ?>
