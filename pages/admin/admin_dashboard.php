<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function adminDashApi(string $method, string $endpoint, ?array $payload = null, array $extraHeaders = []): array {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) return ['success'=>false,'status'=>0,'data'=>null,'error'=>'Supabase configuration or PHP cURL is missing.'];
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
    $headers = ['apikey: '.SUPABASE_SECRET_KEY, 'Authorization: Bearer '.SUPABASE_SECRET_KEY, 'Accept: application/json', 'Content-Type: application/json'];
    foreach ($extraHeaders as $header) $headers[] = $header;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>strtoupper($method), CURLOPT_HTTPHEADER=>$headers, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch); $curlError = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body === false || $curlError !== '') return ['success'=>false,'status'=>$status,'data'=>null,'error'=>$curlError ?: 'Request failed.'];
    $decoded = json_decode($body, true);
    return ['success'=>$status>=200 && $status<300,'status'=>$status,'data'=>$decoded,'error'=>($status<200 || $status>=300) ? (is_array($decoded)?($decoded['message']??$decoded['details']??$decoded['hint']??$body):$body) : null];
}
function adminDashEsc($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function adminDashStatusKey($status): string {
    $s = strtolower(trim((string)$status));
    if (in_array($s, ['in progress','in-progress','in_progress'], true)) return 'in-progress';
    if (in_array($s, ['on hold','on-hold','hold'], true)) return 'on-hold';
    if (in_array($s, ['completed','resolved','closed'], true)) return 'completed';
    if ($s === 'assigned') return 'assigned';
    return 'pending';
}
function adminDashDate($value): string {
    if (!$value) return '—';
    try { return (new DateTime((string)$value, new DateTimeZone('Asia/Manila')))->format('M j, Y'); }
    catch (Throwable $e) { return (string)$value; }
}
/* Short code shown on this dashboard only (the full word is in the tooltip) */
function adminDashStatusCode(string $key): string {
    $codes = ['pending' => 'P', 'assigned' => 'A', 'in-progress' => 'IP', 'completed' => 'C', 'on-hold' => 'H'];
    return $codes[$key] ?? 'P';
}
function adminDashStatusCodeBadge(string $statusText): string {
    $key = adminDashStatusKey($statusText);
    return '<span class="dash-code dash-code-' . adminDashEsc($key) . '" title="' . adminDashEsc($statusText) . '">'
         . adminDashEsc(adminDashStatusCode($key)) . '</span>';
}

$pageError = '';
$requests = [];
$facilities = $floors = $rooms = $equipment = $users = [];
$sessionUsername = trim((string)($_SESSION['username'] ?? $_SESSION['user'] ?? ''));
if ($sessionUsername === '') {
    $pageError = 'Your session has expired. Please log in again.';
} else {
    $lookup = $sessionUsername === 'user' ? 'admin.user' : $sessionUsername;
    $who = adminDashApi('GET', 'users?username=eq.'.rawurlencode($lookup).'&select=user_id,username,full_name,role,status&limit=1');
    if (!$who['success'] || empty($who['data'][0])) {
        $pageError = 'Unable to identify your account. Please sign in again.';
    } elseif (strtolower((string)($who['data'][0]['role'] ?? '')) !== 'admin') {
        $pageError = 'This dashboard is available to administrators only.';
    } else {
        $currentAdmin = $who['data'][0];
        $_SESSION['user_id'] = $currentAdmin['user_id'] ?? ($_SESSION['user_id'] ?? null);
        $_SESSION['username'] = $currentAdmin['username'] ?? $sessionUsername;
        $_SESSION['full_name'] = $currentAdmin['full_name'] ?? '';
        $reqResult = adminDashApi('GET', 'maintenance_requests?select=request_id,display_id,concern,description,status,priority,category,equipment_category,facility_id,floor_id,room_id,equipment_id,reported_by,assigned_to,date_reported,created_at&order=created_at.desc.nullslast&limit=1000');
        if (!$reqResult['success']) {
            // Retry without optional category columns in case the project schema has not added them yet.
            $reqResult = adminDashApi('GET', 'maintenance_requests?select=request_id,display_id,concern,description,status,priority,facility_id,floor_id,room_id,equipment_id,reported_by,assigned_to,date_reported,created_at&order=created_at.desc.nullslast&limit=1000');
        }
        if (!$reqResult['success']) $pageError = 'Unable to load maintenance requests. '.(string)($reqResult['error'] ?? 'Check the maintenance_requests table.');
        else $requests = is_array($reqResult['data']) ? $reqResult['data'] : [];
        foreach ([
            'facilities' => 'facilities?select=facility_id,name',
            'floors' => 'facility_floors?select=floor_id,name',
            'rooms' => 'facility_rooms?select=room_id,name',
            'equipment' => 'equipment?select=equipment_id,name,display_id',
            'users' => 'users?select=user_id,full_name,username,role,status'
        ] as $key=>$endpoint) {
            $res = adminDashApi('GET', $endpoint);
            $$key = ($res['success'] && is_array($res['data'])) ? $res['data'] : [];
        }
    }
}

$facilityMap=[]; foreach($facilities as $x) $facilityMap[(string)($x['facility_id']??'')]=$x['name']??'';
$floorMap=[]; foreach($floors as $x) $floorMap[(string)($x['floor_id']??'')]=$x['name']??'';
$roomMap=[]; foreach($rooms as $x) $roomMap[(string)($x['room_id']??'')]=$x['name']??'';
$equipmentMap=[]; foreach($equipment as $x) $equipmentMap[(string)($x['equipment_id']??'')]=$x['name']??($x['display_id']??'');
$userMap=[]; foreach($users as $x) $userMap[(string)($x['user_id']??'')]=$x['full_name']??($x['username']??'');
foreach ($requests as &$r) {
    $parts=[];
    foreach ([ $facilityMap[(string)($r['facility_id']??'')]??'', $floorMap[(string)($r['floor_id']??'')]??'', $roomMap[(string)($r['room_id']??'')]??'' ] as $p) if (trim((string)$p)!=='') $parts[]=trim((string)$p);
    $r['_id']=(string)($r['display_id']??$r['request_id']??'—');
    $r['_issue']=trim((string)($r['concern']??'')) ?: 'Maintenance Request';
    $r['_equipment']=$equipmentMap[(string)($r['equipment_id']??'')]??'—';
    $r['_location']=$parts ? implode(' · ', array_unique($parts)) : 'Location not specified';
    $r['_date']=adminDashDate($r['date_reported']??$r['created_at']??null);
    $r['_status']=trim((string)($r['status']??'Pending')) ?: 'Pending';
    $r['_statusKey']=adminDashStatusKey($r['_status']);
    $assigned=trim((string)($r['assigned_to']??''));
    $r['_assignedTo']=($assigned!=='' && isset($userMap[$assigned])) ? $userMap[$assigned] : ($assigned!=='' ? $assigned : '—');
    $r['_category']=trim((string)($r['equipment_category']??$r['category']??'')) ?: 'Uncategorized';
}
unset($r);

$counts=['total'=>count($requests),'pending'=>0,'assigned'=>0,'in-progress'=>0,'completed'=>0,'on-hold'=>0,'unassigned'=>0];
$categoryCounts=[]; $activity=[]; $monthly=[]; $equipmentIssues=[];
foreach ($requests as $r) {
    $key=$r['_statusKey']; if(isset($counts[$key])) $counts[$key]++;
    if (trim((string)($r['assigned_to']??''))==='') $counts['unassigned']++;
    $cat=$r['_category']; $categoryCounts[$cat]=($categoryCounts[$cat]??0)+1;
    $eq=$r['_equipment']; if($eq!=='—') $equipmentIssues[$eq]=($equipmentIssues[$eq]??0)+1;
    $rawDate=$r['created_at']??$r['date_reported']??null;
    if($rawDate){ try{$dt=new DateTime((string)$rawDate,new DateTimeZone('Asia/Manila'));$month=$dt->format('M Y');$monthly[$month]=($monthly[$month]??0)+1;}catch(Throwable $e){} }
    if(count($activity)<30) $activity[]=['id'=>$r['_id'],'issue'=>$r['_issue'],'status'=>$r['_status'],'date'=>$r['_date']];
}
$activePersonnel=0; foreach($users as $u) if(strtolower((string)($u['role']??''))==='maintenance personnel' && strtolower((string)($u['status']??'active'))==='active') $activePersonnel++;
$resolvedRate=$counts['total']>0 ? round(($counts['completed']/$counts['total'])*100) : 0;
$openCount=$counts['pending']+$counts['assigned']+$counts['in-progress']+$counts['on-hold'];
arsort($categoryCounts); arsort($equipmentIssues);
$monthly=array_slice($monthly,-6,null,true);
$maxMonthly=max(array_values($monthly) ?: [1]); $maxCategory=max(array_values($categoryCounts) ?: [1]);
$statusChart=[['label'=>'Pending','key'=>'pending','value'=>$counts['pending']],['label'=>'Assigned','key'=>'assigned','value'=>$counts['assigned']],['label'=>'In Progress','key'=>'in-progress','value'=>$counts['in-progress']],['label'=>'Completed','key'=>'completed','value'=>$counts['completed']],['label'=>'On Hold','key'=>'on-hold','value'=>$counts['on-hold']]];
renderHead('Administrator Dashboard', 'Dashboard.css');
?>
<style>
    /* Short status codes used in the table and Recent Activity on this dashboard */
    .dash-code {
        display: inline-grid; place-items: center;
        min-width: 26px; height: 22px; padding: 0 6px; box-sizing: border-box;
        border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: .02em;
        background: #eef2f6; color: #425466; cursor: help;
    }
    .dash-code-pending     { background: #fff1dc; color: #b56a00; }
    .dash-code-assigned    { background: #e4f0fc; color: #1f6fb8; }
    .dash-code-in-progress { background: #efe8fb; color: #6d43b8; }
    .dash-code-completed   { background: #e0f6ec; color: #17794f; }
    .dash-code-on-hold     { background: #fde8e9; color: #b53b40; }
</style>
<div class="dashboard">
  <div class="dash-card">
    <?php renderPageHeader('Administrator Dashboard', 'Maintenance Reporting Portal · Barangay Gulod'); ?>
    <?php if ($pageError !== ''): ?><div class="admin-dash-alert"><?= adminDashEsc($pageError) ?></div><?php endif; ?>

    <div class="dash-main">
      <section class="dash-panel">
        <div class="dash-panel-head">
          <h2>Recent Maintenance Requests</h2>
          <a href="admin_request.php" class="view-all">View all →</a>
        </div>
        <div class="dash-toolbar">
          <input type="text" placeholder="Search requests" id="dashboardSearch">
          <select id="dashboardStatus">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="assigned">Assigned</option>
            <option value="in-progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="on-hold">On Hold</option>
          </select>
          <button type="button" id="dashboardReset">Reset</button>
        </div>
        <div class="dash-table-wrap">
          <table class="admin-dash-table">
            <thead><tr><th>Issue / Equipment</th><th>Assigned To</th><th>Status</th><th>Date</th></tr></thead>
            <tbody id="dashboardTableBody">
              <?php foreach (array_slice($requests, 0, 50) as $r): ?>
                <tr data-status="<?= adminDashEsc($r['_statusKey']) ?>" data-search="<?= adminDashEsc(strtolower($r['_id'].' '.$r['_issue'].' '.$r['_equipment'].' '.$r['_location'].' '.$r['_assignedTo'])) ?>" onclick="window.location.href='admin_request.php?id=<?= rawurlencode((string)($r['request_id']??$r['_id'])) ?>'">
                  <td><b><?= adminDashEsc($r['_issue']) ?></b><small><?= adminDashEsc($r['_equipment']) ?></small></td>
                  <td><?= adminDashEsc($r['_assignedTo']) ?></td>
                  <td><?= adminDashStatusCodeBadge($r['_status']) ?></td>
                  <td><?= adminDashEsc($r['_date']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="empty-state" id="dashboardEmpty" style="display:none"><p>No requests found matching your filters.</p></div>
      </section>

      <div class="dash-side">
        <section class="dash-panel">
          <h3>Requests by Status</h3>
          <?php if ($counts['total'] === 0): ?>
            <div class="admin-no-data">No request data available yet.</div>
          <?php else: ?>
            <div class="admin-status-bars">
              <?php foreach ($statusChart as $bar): $pct = $counts['total'] > 0 ? ($bar['value'] / $counts['total']) * 100 : 0; ?>
                <div class="admin-status-row">
                  <span><?= adminDashEsc($bar['label']) ?></span>
                  <div class="admin-track"><div class="admin-bar <?= adminDashEsc($bar['key']) ?>" style="width:<?= number_format($pct, 2, '.', '') ?>%"></div></div>
                  <span class="admin-status-num"><?= (int)$bar['value'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="dash-panel">
          <h3>Recent Activity</h3>
          <?php if (!$activity): ?>
            <div class="admin-no-data">No recent activity to show.</div>
          <?php else: ?>
            <ul class="admin-activity-list">
              <?php foreach ($activity as $a): ?>
                <li>
                  <span class="admin-activity-dot"></span>
                  <div class="admin-activity-copy">
                    <strong><?= adminDashEsc($a['id'].' · '.$a['issue']) ?></strong>
                    <small><?= adminDashEsc(adminDashStatusCode(adminDashStatusKey($a['status'])).' · '.$a['date']) ?></small>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>
    </div>

    <div class="dash-bottom">
      <section class="dash-panel">
        <h3>Requests Over Time</h3>
        <?php if (!$monthly): ?>
          <div class="admin-no-data">No dated requests available yet.</div>
        <?php else: ?>
          <div class="admin-month-chart">
            <?php foreach ($monthly as $month => $value): $height = max(2, ($value / $maxMonthly) * 100); ?>
              <div class="admin-month-col">
                <span class="admin-month-value"><?= (int)$value ?></span>
                <div class="admin-month-bar" style="height:<?= number_format($height, 1, '.', '') ?>%" title="<?= adminDashEsc($month.': '.$value.' requests') ?>"></div>
                <span class="admin-month-label"><?= adminDashEsc(date('M', strtotime($month))) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-panel">
        <h3>Requests by Category</h3>
        <?php if (!$categoryCounts): ?>
          <div class="admin-no-data">No category data available yet.</div>
        <?php else: ?>
          <div class="admin-category-list">
            <?php foreach (array_slice($categoryCounts, 0, 5, true) as $category => $value): ?>
              <div class="admin-category-row">
                <span class="admin-category-name" title="<?= adminDashEsc($category) ?>"><?= adminDashEsc($category) ?></span>
                <div class="admin-category-track"><div class="admin-category-fill" style="width:<?= number_format(($value / $maxCategory) * 100, 2, '.', '') ?>%"></div></div>
                <span class="admin-category-count"><?= (int)$value ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-panel">
        <h3>Most Reported Equipment</h3>
        <?php if (!$equipmentIssues): ?>
          <div class="admin-no-data">No equipment-linked requests available yet.</div>
        <?php else: ?>
          <?php $topEquipment = array_slice($equipmentIssues, 0, 4, true); $maxEquipment = max(array_values($topEquipment) ?: [1]); ?>
          <div class="admin-category-list">
            <?php foreach ($topEquipment as $name => $value): ?>
              <div class="admin-category-row">
                <span class="admin-category-name" title="<?= adminDashEsc($name) ?>"><?= adminDashEsc($name) ?></span>
                <div class="admin-category-track"><div class="admin-category-fill" style="width:<?= number_format(($value / $maxEquipment) * 100, 2, '.', '') ?>%"></div></div>
                <span class="admin-category-count"><?= (int)$value ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-panel">
        <h3>Service Overview</h3>
        <div class="admin-metric-list">
          <div class="admin-metric"><span>Open Requests</span><strong><?= (int)$openCount ?></strong></div>
          <div class="admin-metric"><span>Completion Rate</span><strong><?= (int)$resolvedRate ?>%</strong></div>
          <div class="admin-metric"><span>Active Personnel</span><strong><?= (int)$activePersonnel ?></strong></div>
        </div>
      </section>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('dashboardSearch');
    const statusSelect = document.getElementById('dashboardStatus');
    const reset = document.getElementById('dashboardReset');
    const rows = Array.from(document.querySelectorAll('#dashboardTableBody tr'));
    const empty = document.getElementById('dashboardEmpty');

    function apply() {
        const q = (search.value || '').toLowerCase().trim();
        const s = statusSelect.value;
        let visible = 0;
        rows.forEach(function (row) {
            const hay = (row.dataset.search || row.textContent).toLowerCase();
            const key = row.dataset.status || '';
            const show = (!q || hay.includes(q)) && (!s || key === s);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        empty.style.display = visible === 0 ? 'block' : 'none';
    }

    search.addEventListener('input', apply);
    statusSelect.addEventListener('change', apply);
    reset.addEventListener('click', function () {
        search.value = '';
        statusSelect.value = '';
        apply();
    });
});
</script>
<?php renderFoot(); ?>
</html>