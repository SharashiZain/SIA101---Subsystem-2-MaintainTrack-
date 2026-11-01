<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---- Config ---- */
const AN_REPEAT_THRESHOLD = 3; // an item with this many repairs in the period is flagged "Frequent"
const AN_RANGES = [
    '30'  => 'Last 30 days',
    '90'  => 'Last 90 days',
    '365' => 'Last 12 months',
    'all' => 'All time',
];
const AN_STATUS_ORDER = [
    'pending'     => 'Pending',
    'assigned'    => 'Assigned',
    'in-progress' => 'In Progress',
    'on-hold'     => 'On Hold',
    'completed'   => 'Completed',
];

/* ---- Helpers (same data path as maintenance_dashboard.php) ---- */
function anApi(string $endpoint): array {
    $secret = defined('SUPABASE_SECRET_KEY') ? (string) SUPABASE_SECRET_KEY : '';
    $base   = defined('SUPABASE_URL') ? (string) SUPABASE_URL : '';
    if ($secret === '' || $base === '') {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'Supabase configuration is missing.'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL extension is not enabled.'];
    }
    $ch = curl_init(rtrim($base, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $secret,
            'Authorization: Bearer ' . $secret,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw       = curl_exec($ch);
    $curlError = curl_error($ch);
    $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'status' => $status, 'data' => null, 'error' => $curlError ?: 'Could not connect to Supabase.'];
    }
    $decoded = json_decode($raw, true);
    $success = $status >= 200 && $status < 300;
    $error   = null;
    if (!$success) {
        $error = is_array($decoded)
            ? (string) ($decoded['message'] ?? $decoded['details'] ?? $decoded['hint'] ?? $raw)
            : $raw;
    }
    return ['success' => $success, 'status' => $status, 'data' => $decoded, 'error' => $error];
}

/* Same status grouping as the admin dashboard */
function anStatusKey(string $status): string {
    $s = strtolower(trim($status));
    if (in_array($s, ['in progress', 'in-progress', 'in_progress', 'ongoing'], true)) return 'in-progress';
    if (in_array($s, ['on hold', 'on-hold', 'hold'], true)) return 'on-hold';
    if (in_array($s, ['completed', 'resolved', 'closed'], true)) return 'completed';
    if ($s === 'assigned') return 'assigned';
    return 'pending';
}

/* Rejected and cancelled reports are not real repairs, so they are left out of every count */
function anIsExcluded(string $status): bool {
    return in_array(strtolower(trim($status)), ['rejected', 'cancelled', 'canceled'], true);
}

function anTitle(string $value): string {
    $v = trim((string) preg_replace('/\s+/', ' ', $value));
    return $v === '' ? '' : mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
}

function anPercent(int $value, int $max): float {
    return $max > 0 ? round($value / $max * 100, 1) : 0.0;
}

function anMonthLabel(string $ym): string {
    $date = DateTime::createFromFormat('Y-m-d', $ym . '-01');
    return $date ? $date->format('M y') : $ym;
}

/* Ranked horizontal bars. Each item: label, sub, count, warn (bool). */
function anRenderBars(array $items): void {
    if (!$items) {
        echo '<div class="an-empty">No repair records for this period.</div>';
        return;
    }
    $max = (int) max(array_column($items, 'count'));
    foreach ($items as $item) {
        $count = (int) $item['count'];
        $pct   = anPercent($count, $max);
        $warn  = !empty($item['warn']);

        echo '<div class="an-row">';
        echo '  <div>';
        echo '    <div class="an-name">' . e($item['label']) . ($warn ? ' <span class="an-badge">Frequent</span>' : '') . '</div>';
        if (($item['sub'] ?? '') !== '') {
            echo '    <div class="an-sub">' . e($item['sub']) . '</div>';
        }
        echo '  </div>';
        echo '  <div class="an-count">' . $count . '</div>';
        echo '  <div class="an-track"><div class="an-fill' . ($warn ? ' warn' : '') . '" style="width:' . e((string) $pct) . '%"></div></div>';
        echo '</div>';
    }
}

/* Status bars: one per status, colored like the admin dashboard */
function anRenderStatus(array $counts, int $total): void {
    foreach (AN_STATUS_ORDER as $key => $label) {
        $value = (int) ($counts[$key] ?? 0);
        $pct   = anPercent($value, $total);

        echo '<div class="an-status-row">';
        echo '  <span class="an-status-label">' . e($label) . '</span>';
        echo '  <div class="an-track"><div class="an-fill s-' . e($key) . '" style="width:' . e((string) $pct) . '%"></div></div>';
        echo '  <span class="an-status-num">' . $value . '</span>';
        echo '</div>';
    }
}

/* ---- Access check (same rule as the maintenance dashboard) ---- */
$pageError       = '';
$currentUserId   = trim((string) ($_SESSION['user_id'] ?? ''));
$currentUsername = trim((string) ($_SESSION['username'] ?? $_SESSION['user'] ?? ''));

if ($currentUserId === '' && $currentUsername === '') {
    $pageError = 'Your session has expired. Please log in again.';
} else {
    if ($currentUserId === '') {
        $userResult = anApi('users?username=eq.' . rawurlencode($currentUsername) . '&select=user_id,username,full_name,role&limit=1');
        if (!$userResult['success'] || empty($userResult['data'][0])) {
            $pageError = 'Unable to identify your account. Please sign in again.';
        } else {
            $u = $userResult['data'][0];
            $currentUserId = (string) ($u['user_id'] ?? '');
            $_SESSION['user_id']   = $currentUserId;
            $_SESSION['username']  = (string) ($u['username'] ?? $currentUsername);
            $_SESSION['full_name'] = (string) ($u['full_name'] ?? '');
            $_SESSION['role']      = (string) ($u['role'] ?? ($_SESSION['role'] ?? ''));
        }
    }
    if ($pageError === '' && strtolower((string) ($_SESSION['role'] ?? '')) !== 'maintenance') {
        $pageError = 'This page is available to Maintenance Personnel only.';
    }
}

/* ---- Time range (filter applied on the server) ---- */
$range = (string) ($_GET['range'] ?? '90');
if (!array_key_exists($range, AN_RANGES)) {
    $range = '90';
}
$rangeFilter = '';
if ($range !== 'all') {
    $cutoff      = (new DateTime('now', new DateTimeZone('Asia/Manila')))->modify('-' . (int) $range . ' days');
    $rangeFilter = '&created_at=gte.' . rawurlencode($cutoff->format('Y-m-d\TH:i:sP'));
}

/* ---- Data ---- */
$requests      = [];
$equipmentById = [];
$facilityNames = [];

if ($pageError === '') {
    $reqResult = anApi(
        'maintenance_requests?select=request_id,concern,category,equipment_category,status,facility_id,equipment_id,date_reported,date_completed,created_at' .
        '&order=created_at.asc' . $rangeFilter
    );

    if (!$reqResult['success']) {
        $pageError = 'Unable to load repair data. ' . (string) ($reqResult['error'] ?? '');
    } else {
        foreach ((array) $reqResult['data'] as $row) {
            if (anIsExcluded((string) ($row['status'] ?? ''))) {
                continue;
            }
            $requests[] = $row;
        }

        foreach ((array) (anApi('facilities?select=facility_id,name')['data'] ?? []) as $x) {
            $facilityNames[(string) ($x['facility_id'] ?? '')] = (string) ($x['name'] ?? '');
        }
        foreach ((array) (anApi('equipment?select=equipment_id,display_id,name,location,specific_area,facility_id,total_repairs,is_recurring')['data'] ?? []) as $x) {
            $equipmentById[(string) ($x['equipment_id'] ?? '')] = $x;
        }
    }
}

/* ---- Aggregation ---- */
$total          = count($requests);
$completedCount = 0;
$statusCounts   = array_fill_keys(array_keys(AN_STATUS_ORDER), 0);
$resolutionDays = [];
$byEquipment    = [];   // equipment_id => count
$byCategory     = [];   // category label => count
$byConcern      = [];   // lowercase concern => ['label' => ..., 'count' => ...]
$byFacility     = [];   // facility name => count
$byMonth        = [];   // 'Y-m' => count

foreach ($requests as $r) {
    $statusKey = anStatusKey((string) ($r['status'] ?? ''));
    $statusCounts[$statusKey]++;

    $closed = $statusKey === 'completed';
    if ($closed) {
        $completedCount++;
    }

    if ($closed && !empty($r['date_reported']) && !empty($r['date_completed'])) {
        $days = (strtotime((string) $r['date_completed']) - strtotime((string) $r['date_reported'])) / 86400;
        if ($days >= 0) {
            $resolutionDays[] = $days;
        }
    }

    $eid = (string) ($r['equipment_id'] ?? '');
    if ($eid !== '') {
        $byEquipment[$eid] = ($byEquipment[$eid] ?? 0) + 1;
    }

    $cat = anTitle((string) ($r['equipment_category'] ?? ''))
        ?: anTitle((string) ($r['category'] ?? ''))
        ?: 'Uncategorized';
    $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;

    $concern = trim((string) preg_replace('/\s+/', ' ', (string) ($r['concern'] ?? '')));
    if ($concern !== '') {
        $key = mb_strtolower($concern);
        if (!isset($byConcern[$key])) {
            $byConcern[$key] = ['label' => $concern, 'count' => 0];
        }
        $byConcern[$key]['count']++;
    }

    $facilityName = $facilityNames[(string) ($r['facility_id'] ?? '')] ?? '';
    $facilityName = $facilityName !== '' ? $facilityName : 'Unassigned';
    $byFacility[$facilityName] = ($byFacility[$facilityName] ?? 0) + 1;

    if (!empty($r['created_at'])) {
        try {
            $ym = (new DateTime((string) $r['created_at'], new DateTimeZone('Asia/Manila')))->format('Y-m');
            $byMonth[$ym] = ($byMonth[$ym] ?? 0) + 1;
        } catch (Throwable $t) {
            // skip dates that cannot be read
        }
    }
}

/* Summary numbers */
$openCount      = $total - $completedCount;
$completionRate = $total > 0 ? (int) round($completedCount / $total * 100) : 0;
$avgDays        = $resolutionDays ? round(array_sum($resolutionDays) / count($resolutionDays), 1) : null;

/* Most repaired equipment (top 8) */
arsort($byEquipment);
$equipmentRows = [];
foreach (array_slice($byEquipment, 0, 8, true) as $eid => $count) {
    $eq = $equipmentById[$eid] ?? [];

    $idPart = trim((string) ($eq['display_id'] ?? ''));

    $locParts = array_values(array_unique(array_filter([
        $facilityNames[(string) ($eq['facility_id'] ?? '')] ?? '',
        trim((string) ($eq['location'] ?? '')),
        trim((string) ($eq['specific_area'] ?? '')),
    ], fn($p) => $p !== '')));

    $metaParts = array_filter([$idPart, implode(' · ', $locParts)], fn($p) => $p !== '');

    $equipmentRows[] = [
        'label' => trim((string) ($eq['name'] ?? '')) ?: 'Equipment',
        'sub'   => implode(' · ', $metaParts),
        'count' => $count,
        'warn'  => $count >= AN_REPEAT_THRESHOLD || !empty($eq['is_recurring']),
    ];
}
$flaggedEquipment = count(array_filter($byEquipment, fn($c) => $c >= AN_REPEAT_THRESHOLD));

/* Common problems (top 6) */
uasort($byConcern, fn($a, $b) => $b['count'] <=> $a['count']);
$concernRows = [];
foreach (array_slice(array_values($byConcern), 0, 6) as $c) {
    $concernRows[] = [
        'label' => $c['label'],
        'sub'   => '',
        'count' => $c['count'],
        'warn'  => $c['count'] >= AN_REPEAT_THRESHOLD,
    ];
}

/* Repairs by category (top 6) */
arsort($byCategory);
$categoryRows = [];
foreach (array_slice($byCategory, 0, 6, true) as $name => $count) {
    $categoryRows[] = ['label' => $name, 'sub' => '', 'count' => $count, 'warn' => false];
}

/* Repairs by facility */
arsort($byFacility);
$facilityRows = [];
foreach ($byFacility as $name => $count) {
    $facilityRows[] = ['label' => $name, 'sub' => '', 'count' => $count, 'warn' => false];
}

/* Monthly trend (latest 12 months that have data) */
ksort($byMonth);
$trend    = array_slice($byMonth, -12, null, true);
$trendMax = $trend ? (int) max($trend) : 0;

renderHead('Repair Analytics', 'Dashboard.css', 'maintenance');
?>
<link rel="stylesheet" href="<?= e(appAssetUrl('assets/css/maintenance/maintenance_Analytics.css')) ?>">

<div class="page">

    <!-- ONE CONTAINER: banner + all page content -->
    <div class="one-card">

    <?php renderPageHeader('Repair Analytics', 'Maintenance Personnel Portal · Barangay Gulod', 'maintenance'); ?>

    <?php if ($pageError !== ''): ?>
        <div class="an-alert" role="alert"><?= e($pageError) ?></div>
    <?php else: ?>

    <!-- TIME RANGE -->
    <form class="an-filter" method="get">
        <label for="anRange">Time period</label>
        <select id="anRange" name="range" onchange="this.form.submit()">
            <?php foreach (AN_RANGES as $value => $label): ?>
                <option value="<?= e((string) $value) ?>" <?= (string) $value === $range ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- SUMMARY -->
    <section class="an-strip">
        <div class="an-metric">
            <span class="an-metric-label">Total Repairs</span>
            <span class="an-metric-value"><?= (int) $total ?></span>
        </div>
        <div class="an-metric">
            <span class="an-metric-label">Open Now</span>
            <span class="an-metric-value <?= $openCount > 0 ? 'warn' : '' ?>"><?= (int) $openCount ?></span>
        </div>
        <div class="an-metric">
            <span class="an-metric-label">Completion Rate</span>
            <span class="an-metric-value"><?= (int) $completionRate ?>%</span>
        </div>
        <div class="an-metric">
            <span class="an-metric-label">Avg. Resolution</span>
            <span class="an-metric-value"><?= $avgDays !== null ? e((string) $avgDays) . ' days' : '—' ?></span>
        </div>
        <div class="an-metric">
            <span class="an-metric-label">Frequent Equipment</span>
            <span class="an-metric-value <?= $flaggedEquipment > 0 ? 'warn' : '' ?>"><?= (int) $flaggedEquipment ?></span>
        </div>
    </section>

    <!-- STATUS + TREND -->
    <div class="an-grid">
        <section class="an-panel">
            <div class="an-panel-head"><h2>Requests by Status</h2></div>
            <?php if ($total === 0): ?>
                <div class="an-empty">No repair records for this period.</div>
            <?php else: ?>
                <?php anRenderStatus($statusCounts, $total); ?>
            <?php endif; ?>
        </section>

        <section class="an-panel">
            <div class="an-panel-head"><h2>Requests Over Time</h2></div>
            <?php if (!$trend): ?>
                <div class="an-empty">No dated repair records for this period.</div>
            <?php else: ?>
                <div class="an-columns">
                    <?php foreach ($trend as $ym => $count): ?>
                        <?php $pct = anPercent((int) $count, $trendMax); ?>
                        <div class="an-col" title="<?= e(anMonthLabel($ym)) ?>: <?= (int) $count ?> repairs">
                            <span class="an-col-value"><?= (int) $count ?></span>
                            <div class="an-col-bar" style="height:<?= e((string) max($pct, 3)) ?>%"></div>
                            <span class="an-col-label"><?= e(anMonthLabel($ym)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- MOST REPAIRED EQUIPMENT -->
    <div class="an-grid single">
        <section class="an-panel">
            <div class="an-panel-head"><h2>Most Reported Equipment</h2></div>
            <p class="an-note">Items with <?= AN_REPEAT_THRESHOLD ?> or more repairs in this period, or marked recurring, are labeled Frequent.</p>
            <?php anRenderBars($equipmentRows); ?>
        </section>
    </div>

    <!-- PROBLEMS + CATEGORY -->
    <div class="an-grid">
        <section class="an-panel">
            <div class="an-panel-head"><h2>Most Common Problems</h2></div>
            <?php anRenderBars($concernRows); ?>
        </section>

        <section class="an-panel">
            <div class="an-panel-head"><h2>Requests by Category</h2></div>
            <?php anRenderBars($categoryRows); ?>
        </section>
    </div>

    <!-- FACILITY -->
    <div class="an-grid single">
        <section class="an-panel">
            <div class="an-panel-head"><h2>Repairs by Facility</h2></div>
            <?php anRenderBars($facilityRows); ?>
        </section>
    </div>

    <?php endif; ?>

    </div>

</div>
<?php renderFoot(); ?>
<script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>