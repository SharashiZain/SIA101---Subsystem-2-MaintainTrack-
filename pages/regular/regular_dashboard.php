<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function dashboardApi(string $endpoint): array {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
        return ['success'=>false,'status'=>0,'data'=>null,'error'=>'Supabase configuration or PHP cURL is missing.'];
    }
    $ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPGET=>true,
        CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SECRET_KEY,'Authorization: Bearer '.SUPABASE_SECRET_KEY,'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>30
    ]);
    $body=curl_exec($ch); $curlError=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body===false || $curlError!=='') return ['success'=>false,'status'=>$status,'data'=>null,'error'=>$curlError ?: 'Request failed.'];
    $decoded=json_decode($body,true);
    return ['success'=>$status>=200 && $status<300,'status'=>$status,'data'=>$decoded,'error'=>($status<200 || $status>=300)?(is_array($decoded)?($decoded['message']??$decoded['details']??$decoded['hint']??$body):$body):null];
}
function dashboardEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function dashboardDate($value): string {
    if (!$value) return '—';
    try { return (new DateTime((string)$value, new DateTimeZone('Asia/Manila')))->format('M j, Y'); }
    catch (Throwable $e) { return (string)$value; }
}
function dashboardStatusClass($status): string {
    return match (strtolower(trim((string)$status))) {
        'in progress','in-progress','assigned','on hold','on-hold','hold' => 'in-progress',
        'completed','closed','resolved' => 'completed',
        default => 'pending'
    };
}

$pageError='';
$requests=[];
$currentUserName='';
$currentUsername=trim((string)($_SESSION['username']??$_SESSION['user']??''));
if ($currentUsername==='') {
    $pageError='Your session has expired. Please log in again.';
} else {
    $lookupUsername=$currentUsername==='user'?'psantos':$currentUsername;
    $userResult=dashboardApi('users?username=eq.'.rawurlencode($lookupUsername).'&select=user_id,username,full_name,role,status&limit=1');
    if (!$userResult['success'] || empty($userResult['data'][0])) {
        $pageError='Unable to identify your account. Please sign in again.';
    } elseif (($userResult['data'][0]['role']??'')!=='regular') {
        $pageError='This dashboard is available to Regular Users only.';
    } else {
        $currentUser=$userResult['data'][0];
        $_SESSION['user_id']=$currentUser['user_id'];
        $_SESSION['username']=$currentUser['username'];
        $_SESSION['full_name']=$currentUser['full_name']??'';
        $currentUserName=trim((string)($currentUser['full_name']??'')) ?: (string)$currentUser['username'];

        // All of the user's requests are loaded (no limit) so the summary filters can show every Pending / In Progress / Completed request.
        // The default "Total" view still shows only the 5 most recent.
        $result=dashboardApi('maintenance_requests?reported_by=eq.'.rawurlencode((string)$currentUser['user_id']).'&select=request_id,display_id,concern,status,facility_id,floor_id,room_id,equipment_id,date_reported,created_at&order=created_at.desc.nullslast');
        if (!$result['success']) {
            $pageError='Unable to load your recent requests. '.(string)($result['error']??'Please check the maintenance_requests table.');
        } else {
            $requests=is_array($result['data'])?$result['data']:[];
            $facilities=dashboardApi('facilities?select=facility_id,name');
            $floors=dashboardApi('facility_floors?select=floor_id,name');
            $rooms=dashboardApi('facility_rooms?select=room_id,name');
            $equipment=dashboardApi('equipment?select=equipment_id,name,display_id');
            $facilityMap=[]; foreach (($facilities['data']??[]) as $row) $facilityMap[(string)$row['facility_id']]=$row['name']??'';
            $floorMap=[]; foreach (($floors['data']??[]) as $row) $floorMap[(string)$row['floor_id']]=$row['name']??'';
            $roomMap=[]; foreach (($rooms['data']??[]) as $row) $roomMap[(string)$row['room_id']]=$row['name']??'';
            $equipmentMap=[]; foreach (($equipment['data']??[]) as $row) $equipmentMap[(string)$row['equipment_id']]=$row['name']??($row['display_id']??'');
            foreach ($requests as &$req) {
                $parts=[];
                foreach ([$facilityMap[(string)($req['facility_id']??'')]??'', $floorMap[(string)($req['floor_id']??'')]??'', $roomMap[(string)($req['room_id']??'')]??''] as $part) {
                    if (trim((string)$part)!=='') $parts[]=trim((string)$part);
                }
                $req['_id']=(string)($req['display_id']??$req['request_id']??'—');
                $req['_title']=trim((string)($req['concern']??''))?:'Maintenance Request';
                $req['_location']=$parts?implode(' · ',array_unique($parts)):'Location not specified';
                $req['_date']=dashboardDate($req['date_reported']??$req['created_at']??null);
                $req['_status']=trim((string)($req['status']??'Pending'))?:'Pending';
                $req['_status_class']=dashboardStatusClass($req['_status']);
                $req['_equipment']=$equipmentMap[(string)($req['equipment_id']??'')]??'';
            }
            unset($req);
        }
    }
}

$allForSummary=[];
if (!$pageError && isset($currentUser['user_id'])) {
    $summaryResult=dashboardApi('maintenance_requests?reported_by=eq.'.rawurlencode((string)$currentUser['user_id']).'&select=status');
    if ($summaryResult['success'] && is_array($summaryResult['data'])) $allForSummary=$summaryResult['data'];
}
$total=count($allForSummary);
$pending=count(array_filter($allForSummary,fn($r)=>dashboardStatusClass($r['status']??'')==='pending'));
$inProgress=count(array_filter($allForSummary,fn($r)=>dashboardStatusClass($r['status']??'')==='in-progress'));
$completed=count(array_filter($allForSummary,fn($r)=>dashboardStatusClass($r['status']??'')==='completed'));
$summary=[['count'=>$total,'label'=>'Total','class'=>'','filter'=>'all'],['count'=>$pending,'label'=>'Pending','class'=>'orange','filter'=>'pending'],['count'=>$inProgress,'label'=>'In Progress','class'=>'purple','filter'=>'in-progress'],['count'=>$completed,'label'=>'Completed','class'=>'green','filter'=>'completed']];

renderHead('My Dashboard','regular_dashboard.css','regular');
?>
<div class="page">
    <section class="panel">
        <div class="hero-card">
            <?php renderPageHeader('My Dashboard','Maintenance Reporting Portal · Barangay Gulod','regular'); ?>
            <section class="welcome-card">
                <div class="welcome-content">
                    <h2>Hello, <?=dashboardEscape($currentUserName!==''?$currentUserName:'there')?>!</h2>
                    <p>Have a maintenance concern? Report it and track its progress.</p>
                </div>
                <button type="button" class="report-button" onclick="window.location.href='regular_report.php'">
                    <span class="report-button-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg></span>
                    Report an Issue
                </button>
            </section>
        </div>

        <?php if ($pageError!==''): ?>
            <div role="alert" style="margin:18px 0;padding:14px 16px;border-radius:10px;background:#fff0f0;border:1px solid #f0c3c3;color:#9d3030;font-size:13px;"><?=dashboardEscape($pageError)?></div>
        <?php endif; ?>

        <div class="panel-head">
            <h2>My Recent Requests</h2>
            <div class="summary-line">
                <?php foreach ($summary as $s): ?>
                    <div class="summary-item<?= $s['filter']==='all' ? ' is-active' : '' ?>" data-filter="<?=dashboardEscape($s['filter'])?>" role="button" tabindex="0" aria-pressed="<?= $s['filter']==='all' ? 'true' : 'false' ?>"><strong class="<?=dashboardEscape($s['class'])?>"><?= (int)$s['count']?></strong><span><?=dashboardEscape($s['label'])?></span></div>
                <?php endforeach; ?>
            </div>
            <a href="regular_history.php" class="panel-link">View all →</a>
        </div>

        <div class="request-list">
            <?php if (!$pageError && $requests): ?>
                <?php foreach ($requests as $reqIndex => $req): ?>
                    <div class="request-card" data-status="<?=dashboardEscape($req['_status_class'])?>"<?= $reqIndex >= 5 ? ' hidden style="display:none"' : '' ?> onclick="window.location.href='regular_request.php?id=<?=rawurlencode((string)$req['request_id'])?>'" role="link" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                        <div class="request-main">
                            <div class="request-id"><?=dashboardEscape($req['_id'])?></div>
                            <div class="request-title"><?=dashboardEscape($req['_title'])?></div>
                            <div class="request-details"><?=dashboardEscape($req['_location'])?> · <?=dashboardEscape($req['_date'])?></div>
                            <?php if ($req['_equipment']!==''): ?><div class="request-details">Equipment: <?=dashboardEscape($req['_equipment'])?></div><?php endif; ?>
                        </div>
                        <div class="request-side"><span class="status <?=dashboardEscape($req['_status_class'])?>"><?=dashboardEscape($req['_status'])?></span><span class="chevron" aria-hidden="true">›</span></div>
                    </div>
                <?php endforeach; ?>
                <div class="request-filter-empty" id="filterEmpty" hidden style="display:none"></div>
            <?php elseif (!$pageError): ?>
                <div style="text-align:center;padding:36px 20px;color:#687985;"><p style="font-size:15px;font-weight:600;">You have not submitted any maintenance requests yet.</p><p>Use “Report an Issue” to submit your first request.</p></div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const items = document.querySelectorAll('.summary-item[data-filter]');
    const cards = document.querySelectorAll('.request-card[data-status]');
    const empty = document.getElementById('filterEmpty');
    const RECENT_LIMIT = 5; // "Total" shows only the 5 most recent; the status filters show every match.

    function applyFilter(filter, label) {
        let shown = 0;
        cards.forEach(function (card) {
            const match = filter === 'all' ? shown < RECENT_LIMIT : card.dataset.status === filter;
            card.hidden = !match;
            card.style.display = match ? '' : 'none'; // works even if the CSS didn't load
            if (match) shown++;
        });

        items.forEach(function (item) {
            const on = item.dataset.filter === filter;
            item.classList.toggle('is-active', on);
            item.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        if (empty) {
            empty.hidden = shown !== 0;
            empty.style.display = shown === 0 ? 'block' : 'none';
            if (shown === 0) empty.textContent = 'No requests found with the status “' + label + '”.';
        }
    }

    items.forEach(function (item) {
        const label = item.querySelector('span') ? item.querySelector('span').textContent : '';
        item.addEventListener('click', function () { applyFilter(item.dataset.filter, label); });
        item.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); item.click(); }
        });
    });

    applyFilter('all', 'Total'); // set the initial state
});
</script>
<?php renderFoot(); ?>
</html>