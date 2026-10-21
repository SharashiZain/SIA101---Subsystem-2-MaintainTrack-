<?php
declare(strict_types=1);

// Buffer all output so stray whitespace or warnings from included files can never corrupt the JSON response.
ob_start();

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';
require_once __DIR__ . '/../../includes/PriorityScore.php';

function assignmentJson(array $payload, int $status = 200): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function assignmentSupabase(string $endpoint, string $method = 'GET', ?array $data = null): array {
    $secret = defined('SUPABASE_SECRET_KEY') ? (string) SUPABASE_SECRET_KEY : (string) (getenv('SUPABASE_SECRET_KEY') ?: '');
    if ($secret === '') return ['success'=>false, 'status'=>0, 'data'=>null, 'error'=>'SUPABASE_SECRET_KEY is not configured.'];
    if (!function_exists('curl_init')) return ['success'=>false, 'status'=>0, 'data'=>null, 'error'=>'PHP cURL extension is not enabled.'];
    if (!str_starts_with($endpoint, '/') || str_starts_with($endpoint, '//') || str_contains($endpoint, "\r") || str_contains($endpoint, "\n")) {
        return ['success'=>false, 'status'=>0, 'data'=>null, 'error'=>'Invalid API endpoint.'];
    }
    $headers = ['apikey: '.$secret, 'Authorization: Bearer '.$secret, 'Accept: application/json', 'Content-Type: application/json', 'Prefer: return=representation'];
    $options = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>strtoupper($method), CURLOPT_HTTPHEADER=>$headers, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30];
    if ($data !== null) {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return ['success'=>false, 'status'=>0, 'data'=>null, 'error'=>'Could not encode request data.'];
        $options[CURLOPT_POSTFIELDS] = $json;
    }
    $ch = curl_init(rtrim((string) SUPABASE_URL, '/') . $endpoint);
    if ($ch === false) return ['success'=>false, 'status'=>0, 'data'=>null, 'error'=>'Unable to initialize cURL.'];
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($raw === false) return ['success'=>false, 'status'=>$status, 'data'=>null, 'error'=>$curlError ?: 'Could not connect to Supabase.'];
    $decoded = json_decode($raw, true);
    $success = $status >= 200 && $status < 300;
    $error = null;
    if (!$success) $error = is_array($decoded) ? (string)($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? $decoded['hint'] ?? $raw) : ($raw ?: 'Supabase returned an HTTP error.');
    return ['success'=>$success, 'status'=>$status, 'data'=>$decoded ?? $raw, 'error'=>$error];
}

function assignmentIsAdmin(): bool {
    return isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
}

// Accepts any standard UUID string. The seeded accounts use IDs such as
// bbbbbbbb-0000-0000-0000-000000000001, which the stricter RFC-variant pattern rejected.
function assignmentUuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
}

function assignmentStatusOpen(string $status): bool {
    return !in_array(strtolower(trim($status)), ['completed', 'cancelled', 'closed'], true);
}

// JSON API for assignment action. The secret key is used only by this server-side PHP file.
if (isset($_GET['assignment_api']) || isset($_POST['assignment_api'])) {
    if (!assignmentIsAdmin()) assignmentJson(['success'=>false, 'message'=>'Unauthorized. Sign in using an Admin account.'], 403);
    $action = (string)($_POST['action'] ?? $_GET['action'] ?? 'list');
    if ($action === 'assign') {
        $requestId = trim((string)($_POST['request_id'] ?? ''));
        $staffId = trim((string)($_POST['staff_id'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if (!assignmentUuid($requestId) || !assignmentUuid($staffId)) assignmentJson(['success'=>false, 'message'=>'Invalid request or personnel ID.'], 422);

        $staffLookup = assignmentSupabase('/rest/v1/users?user_id=eq.'.rawurlencode($staffId).'&select=user_id,full_name,email,role,status,specialization_category,specialization_type&limit=1');
        $person = is_array($staffLookup['data'] ?? null) ? ($staffLookup['data'][0] ?? null) : null;
        if (!$staffLookup['success'] || !$person || ($person['role'] ?? '') !== 'maintenance' || strtolower((string)($person['status'] ?? '')) !== 'active') {
            assignmentJson(['success'=>false, 'message'=>'The selected maintenance personnel is unavailable or inactive. Refresh the page and try again.'], 422);
        }

        $requestLookup = assignmentSupabase('/rest/v1/maintenance_requests?request_id=eq.'.rawurlencode($requestId).'&select=request_id,display_id,concern,status,assigned_to,approval_status&limit=1');
        $request = is_array($requestLookup['data'] ?? null) ? ($requestLookup['data'][0] ?? null) : null;
        if (!$requestLookup['success'] || !$request) assignmentJson(['success'=>false, 'message'=>'Request not found. Refresh the page and try again.'], 404);
        if (!assignmentStatusOpen((string)($request['status'] ?? ''))) assignmentJson(['success'=>false, 'message'=>'Completed or closed requests cannot be assigned.'], 409);
        // A request must be approved by the secretary before it can be assigned to anyone.
        if (strtolower(trim((string)($request['approval_status'] ?? ''))) !== 'approved') assignmentJson(['success'=>false, 'message'=>'This request has not been approved by the secretary yet, so it cannot be assigned.'], 409);

        $now = gmdate('c');
        $update = ['assigned_to'=>$staffId, 'status'=>'Assigned', 'date_assigned'=>$now, 'updated_at'=>$now];
        $save = assignmentSupabase('/rest/v1/maintenance_requests?request_id=eq.'.rawurlencode($requestId).'&select=request_id,display_id,concern,status,assigned_to,date_assigned', 'PATCH', $update);
        if (!$save['success']) assignmentJson(['success'=>false, 'message'=>'Could not assign request: '.(string)$save['error']], 502);
        $savedRequest = is_array($save['data'] ?? null) ? ($save['data'][0] ?? null) : null;
        if (!$savedRequest) assignmentJson(['success'=>false, 'message'=>'Supabase did not return the updated request. Refresh the page to verify the assignment.'], 502);

        // Timeline logging is optional. The assignment is already saved, so a failure here must not fail the request.
        // actor_user_id is only sent when it is a valid UUID, because the column rejects anything else.
        $actorId = (string)($_SESSION['user_id'] ?? '');
        $timelineNote = $notes !== '' ? $notes : ('Assigned to '.$person['full_name']);
        assignmentSupabase('/rest/v1/request_timeline', 'POST', [[
            'request_id'=>$requestId,
            'status'=>'Assigned',
            'actor_user_id'=>assignmentUuid($actorId) ? $actorId : null,
            'note'=>$timelineNote,
            'occurred_at'=>$now
        ]]);
        assignmentJson(['success'=>true, 'message'=>'Request assigned to '.$person['full_name'].'.', 'request'=>$savedRequest, 'staff'=>$person]);
    }
    assignmentJson(['success'=>false, 'message'=>'Unknown action.'], 400);
}

$staff = [];
$unassigned = [];
$pageError = '';
if (!assignmentIsAdmin()) {
    http_response_code(403);
    $pageError = 'Access denied. Please sign in using an Admin account.';
} else {
    $usersResult = assignmentSupabase('/rest/v1/users?select=user_id,display_id,full_name,email,role,status,specialization_category,specialization_type&role=eq.maintenance&status=eq.Active&order=full_name.asc');
    // Only requests the secretary has already approved can be assigned. Rejected requests are included
    // so the admin can reassign them; a rejected request keeps its old assigned_to, so it is matched by status.
    $requestsResult = assignmentSupabase('/rest/v1/maintenance_requests?select=request_id,display_id,concern,description,status,priority,category,assigned_to,approval_status,date_reported,created_at,facility_id,specific_area,equipment_id&approval_status=eq.Approved&status=not.in.(Completed)&or=(assigned_to.is.null,status.eq.Rejected)&order=date_reported.asc');
    if (!$usersResult['success']) $pageError = 'Could not load maintenance personnel: '.(string)$usersResult['error'];
    elseif (!$requestsResult['success']) $pageError = 'Could not load unassigned requests: '.(string)$requestsResult['error'];
    else {
        $staffRows = is_array($usersResult['data']) ? $usersResult['data'] : [];
        $requestRows = is_array($requestsResult['data']) ? $requestsResult['data'] : [];
        // Rejected requests are not active work, so they are left out of each worker's open-task count.
        $tasksResult = assignmentSupabase('/rest/v1/maintenance_requests?select=request_id,display_id,concern,status,assigned_to,date_reported&assigned_to=not.is.null&status=not.in.(Completed,Rejected)&order=date_reported.asc');
        $tasksByStaff = [];
        if ($tasksResult['success'] && is_array($tasksResult['data'])) {
            foreach ($tasksResult['data'] as $task) {
                $owner = (string)($task['assigned_to'] ?? '');
                if ($owner !== '') $tasksByStaff[$owner][] = [
                    'request_id'=>(string)($task['request_id'] ?? ''),
                    'id'=>(string)($task['display_id'] ?? $task['request_id'] ?? 'Request'),
                    'issue'=>(string)($task['concern'] ?? 'Maintenance concern'),
                    'status'=>(string)($task['status'] ?? 'Assigned')
                ];
            }
        }
        foreach ($staffRows as $row) {
            $id = (string)($row['user_id'] ?? '');
            $name = (string)($row['full_name'] ?? 'Unnamed personnel');
            $parts = preg_split('/\s+/', trim($name)) ?: [];
            $initials = strtoupper(substr($parts[0] ?? 'M', 0, 1).(count($parts) > 1 ? substr($parts[count($parts)-1], 0, 1) : ''));
            $staff[] = [
                'id'=>$id, 'name'=>$name, 'initials'=>$initials, 'email'=>(string)($row['email'] ?? ''),
                'role'=>'Maintenance Personnel', 'status'=>(string)($row['status'] ?? 'Active'),
                'specialization_category'=>(string)($row['specialization_category'] ?? ''),
                'specialization_type'=>(string)($row['specialization_type'] ?? ''),
                'tasks'=>$tasksByStaff[$id] ?? []
            ];
        }
        // Request location/equipment labels are optional lookups; core request list still loads if these tables are unavailable.
        $facilitiesById = [];
        $equipmentById = [];
        $facilityResult = assignmentSupabase('/rest/v1/facilities?select=facility_id,name');
        if ($facilityResult['success'] && is_array($facilityResult['data'])) foreach ($facilityResult['data'] as $f) $facilitiesById[(string)$f['facility_id']] = (string)$f['name'];
        $equipmentResult = assignmentSupabase('/rest/v1/equipment?select=equipment_id,name,display_id');
        if ($equipmentResult['success'] && is_array($equipmentResult['data'])) foreach ($equipmentResult['data'] as $eq) $equipmentById[(string)$eq['equipment_id']] = (string)($eq['name'] ?? $eq['display_id'] ?? 'Equipment');
        foreach ($requestRows as $row) {
            $facilityId = (string)($row['facility_id'] ?? '');
            $equipmentId = (string)($row['equipment_id'] ?? '');
            $unassigned[] = [
                'request_id'=>(string)($row['request_id'] ?? ''),
                'id'=>(string)($row['display_id'] ?? $row['request_id'] ?? 'Request'),
                'issue'=>(string)($row['concern'] ?? 'Maintenance concern'),
                'description'=>(string)($row['description'] ?? ''),
                'status'=>(string)($row['status'] ?? 'Pending'),
                'priority'=>(string)($row['priority'] ?? 'Medium'),
                'category'=>(string)($row['category'] ?? ''),
                'location'=>(($facilitiesById[$facilityId] ?? '') . (!empty($row['specific_area']) ? ' · '.$row['specific_area'] : '')),
                'equipment'=>$equipmentById[$equipmentId] ?? '',
                'date'=>(string)($row['date_reported'] ?? $row['created_at'] ?? '')
            ];
        }

        // The priority score is still calculated and shown on each card as extra information,
        // but it no longer decides the order of the list (see the first-come-first-served sort below).
        // The stored priority (Low / Medium / High / Urgent) is the urgency term; recurrence is counted against the last 30 days of all requests.
        try {
            $sinceIso = gmdate('Y-m-d\TH:i:s\Z', time() - PRIORITY_RECURRENCE_WINDOW_DAYS * 86400);
            $recentResult = assignmentSupabase('/rest/v1/maintenance_requests?select=request_id,equipment_id,date_reported&date_reported=gte.'.rawurlencode($sinceIso));
            $recentContext = [];
            if ($recentResult['success'] && is_array($recentResult['data'])) foreach ($recentResult['data'] as $rr) {
                $recentContext[] = ['id'=>(string)($rr['request_id'] ?? ''), 'equipment_id'=>(string)($rr['equipment_id'] ?? ''), 'date_reported'=>$rr['date_reported'] ?? ''];
            }
            $scoreRows = [];
            foreach ($requestRows as $row) {
                $scoreRows[] = [
                    'id'=>(string)($row['request_id'] ?? ''),
                    'urgency'=>(string)($row['priority'] ?? 'Medium'),
                    'facility'=>$facilitiesById[(string)($row['facility_id'] ?? '')] ?? '',
                    'room'=>(string)($row['specific_area'] ?? ''),
                    'equipment_id'=>(string)($row['equipment_id'] ?? ''),
                    'date_reported'=>$row['date_reported'] ?? $row['created_at'] ?? ''
                ];
            }
            $scoreById = [];
            foreach (buildPriorityQueue($scoreRows, null, $recentContext) as $scored) $scoreById[$scored['id']] = $scored;
            foreach ($unassigned as &$u) {
                $sc = $scoreById[$u['request_id']] ?? null;
                $u['score'] = $sc['_score'] ?? null;
                $u['score_tip'] = $sc ? priorityScoreTooltip($sc) : '';
            }
            unset($u);
        } catch (Throwable $e) {
            // Scoring is optional: if it fails, the requests simply show without a score.
        }

        // FIRST COME, FIRST SERVED: the oldest report is at the top and the newest report is at the very bottom.
        // Requests with no usable date go last. Ties keep their original order.
        $order = 0;
        foreach ($unassigned as &$u) { $u['_order'] = $order++; }
        unset($u);
        usort($unassigned, function (array $a, array $b): int {
            $ta = strtotime((string)($a['date'] ?? ''));
            $tb = strtotime((string)($b['date'] ?? ''));
            $ta = $ta === false ? PHP_INT_MAX : $ta;
            $tb = $tb === false ? PHP_INT_MAX : $tb;
            return ($ta <=> $tb) ?: ($a['_order'] <=> $b['_order']);
        });
    }
}

renderHead('Assignments & Personnel', 'Assignment.css');
?>
<div class="page">
    <div class="one-card">
        <?php renderPageHeader('Assignments & Personnel'); ?>
        <?php if ($pageError !== ''): ?>
            <div class="assignment-alert" role="alert"><?= e($pageError) ?></div>
        <?php endif; ?>
        <div class="assignment-grid">
            <section class="assignment-card personnel-panel">
                <div class="assignment-panel-heading">
                    <div><h2>Maintenance Personnel</h2><p>Browse personnel by specialization and view each person's workload.</p></div>
                    <span class="assignment-count" id="staffTotal"><?= count($staff) ?></span>
                </div>
                <div class="personnel-tools">
                    <label class="assignment-search-wrap"><span aria-hidden="true">⌕</span><input type="search" id="staffSearch" placeholder="Search personnel..." aria-label="Search maintenance personnel"></label>
                    <select id="specializationFilter" aria-label="Filter by specialization category"><option value="">All categories</option><option value="Electrical">Electrical</option><option value="Non-Electrical">Non-Electrical</option><option value="Unspecified">Unspecified</option></select>
                    <select id="typeFilter" aria-label="Filter by technician type"><option value="">All technician types</option></select>
                </div>
                <div class="staff-list" id="staffList">
                    <?php if (!$staff): ?><div class="assignment-empty">No active maintenance personnel found.</div><?php endif; ?>
                    <?php foreach ($staff as $index => $member): ?>
                        <button type="button" class="staff-item" data-staff-index="<?= $index ?>" data-staff-id="<?= e($member['id']) ?>" data-staff-name="<?= e($member['name']) ?>" data-category="<?= e($member['specialization_category'] ?: 'Unspecified') ?>" data-type="<?= e($member['specialization_type']) ?>">
                            <span class="profile-circle"><?= e($member['initials']) ?></span>
                            <span class="staff-main"><strong><?= e($member['name']) ?></strong><small><?= e($member['specialization_type'] ?: 'Specialization not set') ?></small><span class="staff-meta"><?= e($member['specialization_category'] ?: 'Unspecified category') ?> · <?= count($member['tasks']) ?> open <?= count($member['tasks']) === 1 ? 'task' : 'tasks' ?></span></span>
                            <span class="staff-arrow" aria-hidden="true">›</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="assignment-empty" id="staffNoResults" style="display:none;">No personnel match your search or filters.</div>
            </section>

            <section class="assignment-card unassigned-panel">
                <div class="assignment-panel-heading">
                    <div><h2>Unassigned Requests</h2><p>Secretary-approved maintenance requests that still need personnel. Oldest reports are at the top (first come, first served). Rejected requests appear here so they can be reassigned.</p></div>
                    <span class="assignment-count assignment-count-warning" id="unassignedTotal"><?= count($unassigned) ?></span>
                </div>
                <div class="request-tools">
                    <label class="assignment-search-wrap request-search"><span aria-hidden="true">⌕</span><input type="search" id="requestSearch" placeholder="Search unassigned requests..." aria-label="Search unassigned requests"></label>
                    <select id="requestPriorityFilter" aria-label="Filter by priority"><option value="">All priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select>
                </div>
                <div class="unassigned-list" id="unassignedList">
                    <?php foreach ($unassigned as $request): ?>
                        <article class="unassigned-item" data-request-id="<?= e($request['request_id']) ?>" data-request-display-id="<?= e($request['id']) ?>" data-request-issue="<?= e($request['issue']) ?>" data-request-category="<?= e($request['category']) ?>" data-request-priority="<?= e(strtolower($request['priority'])) ?>" data-request-status="<?= e(strtolower($request['status'])) ?>" data-request-search="<?= e(strtolower($request['id'].' '.$request['issue'].' '.$request['description'].' '.$request['location'].' '.$request['equipment'])) ?>">
                            <div class="unassigned-item-top"><strong><?= e($request['id']) ?></strong><span class="priority-chip priority-<?= e(strtolower((string)$request['priority'])) ?>"><?= e($request['priority']) ?></span><?php if (isset($request['score']) && $request['score'] !== null): ?><span class="priority-chip" style="background:#eef3f8;color:#12355f;border:1px solid #cfdce8;" title="<?= e($request['score_tip'] ?? '') ?>">Score <?= e(number_format((float)$request['score'], 2)) ?></span><?php endif; ?></div>
                            <h3><?= e($request['issue']) ?></h3>
                            <p class="request-subtitle"><?= e($request['location'] ?: 'Location not specified') ?><?= $request['equipment'] ? ' · '.e($request['equipment']) : '' ?></p>
                            <?php if ($request['description'] !== ''): ?><p class="request-description"><?= e($request['description']) ?></p><?php endif; ?>
                            <div class="unassigned-item-bottom"><span class="request-status status-<?= e(strtolower(str_replace(' ', '-', $request['status']))) ?>"><?= e($request['status']) ?></span><button type="button" class="assign-button" data-request-id="<?= e($request['request_id']) ?>" data-request-display-id="<?= e($request['id']) ?>" data-request-issue="<?= e($request['issue']) ?>" <?= !$staff ? 'disabled' : '' ?>>Assign Personnel</button></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$unassigned): ?><div class="assignment-empty">There are no approved unassigned requests right now.</div><?php endif; ?>
                </div>
                <div class="assignment-empty" id="requestNoResults" style="display:none;">No unassigned requests match your search or filters.</div>
            </section>
        </div>
    </div>
</div>

<!-- PERSONNEL WORKLOAD MODAL -->
<div class="static-modal" id="workloadModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="workloadModalTitle">
    <div class="static-modal-content"><div class="modal-header"><div><h3 id="workloadModalTitle">Personnel Workload</h3><p class="modal-subtitle" id="workloadModalEmail">—</p></div><button type="button" class="modal-close" id="workloadModalClose" aria-label="Close">✕</button></div>
        <div class="modal-body"><div class="workload-summary" id="workloadSummary"></div><h4 class="modal-section-title">Open Assigned Requests</h4><ul class="workload-task-list" id="workloadTaskList"></ul></div>
        <div class="modal-actions"><button type="button" class="modal-cancel" id="workloadModalCloseBtn">Close</button></div>
    </div>
</div>

<!-- ASSIGN REQUEST MODAL -->
<div class="static-modal" id="assignModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="assignModalTitle">
    <div class="static-modal-content"><div class="modal-header"><h3 id="assignModalTitle">Assign Request</h3><button type="button" class="modal-close" id="assignModalClose" aria-label="Close">✕</button></div>
        <div class="modal-body"><p class="modal-subtitle">Request <strong id="assignModalRequestId">—</strong></p><p class="modal-role" id="assignModalIssue">—</p>
            <div class="modal-field"><label for="assignStaffSelect">Maintenance Personnel *</label><select id="assignStaffSelect" required><option value="">Select personnel</option><?php foreach ($staff as $member): ?><option value="<?= e($member['id']) ?>"><?= e($member['name']) ?> — <?= e($member['specialization_type'] ?: 'Specialization not set') ?> (<?= count($member['tasks']) ?> open)</option><?php endforeach; ?></select><small id="assignStaffHint">You can assign to any active personnel; specialization is a guide, not a restriction.</small></div>
            <div class="modal-field"><label for="assignNotes">Assignment notes (optional)</label><textarea id="assignNotes" rows="3" placeholder="Add instructions or notes for this assignment..."></textarea></div>
        </div><div class="modal-actions"><button type="button" class="modal-cancel" id="assignModalCancel">Cancel</button><button type="button" class="modal-save" id="assignModalConfirm">Confirm Assignment</button></div>
    </div>
</div>

<div class="system-modal-overlay" id="systemModal" role="dialog" aria-modal="true" style="display:none;"><div class="system-modal"><div class="system-modal-icon" id="systemModalIcon"></div><h3 class="system-modal-title" id="systemModalTitle">Title</h3><p class="system-modal-text" id="systemModalText">Message</p><div class="system-modal-actions" id="systemModalActions"></div></div></div>

<style>
.assignment-alert{margin:16px 0;padding:13px 16px;border:1px solid #f2c6c6;border-radius:10px;background:#fff3f3;color:#9b2525;font-size:13px}.assignment-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px;align-items:start}.assignment-card{min-width:0;background:#fff;border:1px solid #dce6f0;border-radius:14px;padding:20px;box-shadow:0 4px 16px rgba(19,57,91,.04)}.assignment-panel-heading{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px}.assignment-panel-heading h2{margin:0;color:#174777;font-size:17px;font-weight:800}.assignment-panel-heading p{margin:5px 0 0;color:#71839a;font-size:12px;line-height:1.5}.assignment-count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;border-radius:9px;background:#e8f2fb;color:#1763a3;font-size:12px;font-weight:800}.assignment-count-warning{background:#fff2d7;color:#a76500}.personnel-tools{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.85fr);gap:9px;margin-bottom:14px}.assignment-search-wrap{grid-column:1/-1;display:flex;align-items:center;gap:8px;border:1px solid #d8e4ef;border-radius:9px;padding:0 11px;background:#fbfdff;color:#67829b}.assignment-search-wrap span{font-size:22px;line-height:1}.assignment-search-wrap input{width:100%;min-width:0;height:40px;border:0;outline:0;background:transparent;color:#173d60;font:inherit;font-size:12px}.personnel-tools select,.modal-field select,.modal-field textarea{width:100%;min-width:0;border:1px solid #d8e4ef;border-radius:9px;padding:10px;background:#fff;color:#24445f;font:inherit;font-size:12px}.staff-list,.unassigned-list{display:flex;flex-direction:column;gap:9px}.staff-item{width:100%;display:flex;align-items:center;gap:12px;padding:13px;border:1px solid #e2eaf2;border-radius:11px;background:#fff;text-align:left;cursor:pointer;color:inherit;transition:border-color .15s,background .15s,transform .15s}.staff-item:hover,.staff-item:focus-visible{border-color:#6ca6d8;background:#f7fbff;outline:none}.profile-circle{flex:0 0 42px;width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e5f1fc;color:#1766a6;font-weight:800;font-size:13px}.staff-main{display:flex;flex:1;min-width:0;flex-direction:column;gap:3px}.staff-main strong{color:#183e62;font-size:13px}.staff-main small,.staff-meta{color:#71849a;font-size:11px;line-height:1.4}.staff-meta{color:#4c7395}.staff-arrow{font-size:24px;color:#7793ac}.unassigned-item{border:1px solid #e1eaf2;border-radius:11px;padding:14px;background:#fff}.unassigned-item-top,.unassigned-item-bottom{display:flex;align-items:center;justify-content:space-between;gap:10px}.unassigned-item-top>strong{font-size:11px;color:#1763a3;letter-spacing:.3px}.unassigned-item h3{margin:9px 0 4px;font-size:14px;color:#1d3f5d}.request-subtitle,.request-description{margin:0;color:#71839a;font-size:11px;line-height:1.55;overflow-wrap:anywhere}.request-description{margin-top:8px;color:#526c82}.unassigned-item-bottom{margin-top:13px}.priority-chip,.request-status{display:inline-flex;align-items:center;border-radius:20px;padding:5px 8px;font-size:10px;font-weight:700;white-space:nowrap}.priority-chip{background:#f1f5f9;color:#5e7082}.priority-high,.priority-urgent{background:#ffebeb;color:#b42318}.priority-medium{background:#fff3d9;color:#9a6500}.priority-low{background:#e6f8ef;color:#14774c}.request-status{background:#fff2d8;color:#946000}.status-assigned{background:#e6f2ff;color:#1a66a5}.status-in-progress{background:#eee9ff;color:#6941c6}.status-rejected{background:#ffebeb;color:#b42318}.assign-button{border:0;border-radius:8px;padding:9px 12px;background:#1767a8;color:#fff;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap}.assign-button:hover{background:#105487}.assign-button:disabled{opacity:.5;cursor:not-allowed}.assignment-empty{padding:22px 12px;text-align:center;color:#7a8da0;font-size:12px}.request-search{margin-bottom:14px}.static-modal{position:fixed;inset:0;z-index:9998;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(9,35,61,.55);backdrop-filter:blur(3px)}.static-modal-content{width:min(100%,520px);max-height:85vh;overflow:auto;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.2)}.modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 22px;border-bottom:1px solid #e5edf4}.modal-header h3{margin:0;color:#173e63;font-size:18px}.modal-close{border:0;background:transparent;color:#71839a;font-size:19px;cursor:pointer}.modal-body{padding:20px 22px}.modal-subtitle,.modal-role{margin:0 0 7px;color:#71839a;font-size:12px}.modal-role{color:#264b6b;font-weight:700}.modal-section-title{margin:18px 0 10px;color:#234c70;font-size:13px}.modal-field{display:flex;flex-direction:column;gap:7px;margin-top:16px}.modal-field label{font-size:12px;font-weight:700;color:#24445f}.modal-field small{font-size:10px;color:#7b8da0;line-height:1.5}.modal-actions{display:flex;justify-content:flex-end;gap:9px;padding:15px 22px 20px;border-top:1px solid #e5edf4}.modal-cancel,.modal-save{border:1px solid #dbe5ef;border-radius:8px;padding:10px 15px;background:#fff;color:#31516d;font-size:12px;font-weight:700;cursor:pointer}.modal-save{border-color:#1767a8;background:#1767a8;color:#fff}.modal-save:disabled{opacity:.6;cursor:wait}.workload-summary{display:flex;flex-wrap:wrap;gap:8px}.workload-summary span{padding:7px 10px;border-radius:8px;background:#edf5fc;color:#285a82;font-size:11px;font-weight:700}.workload-task-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}.workload-task{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:11px;border:1px solid #e1eaf2;border-radius:9px}.workload-task-info{display:flex;flex-direction:column;gap:4px;min-width:0}.workload-task-info strong{color:#1763a3;font-size:11px}.workload-task-info span{color:#304e68;font-size:12px;overflow-wrap:anywhere}.workload-task-status{flex-shrink:0;border-radius:20px;padding:5px 7px;background:#fff2d8;color:#946000;font-size:10px;font-weight:700}.system-modal-overlay{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(9,35,61,.55)}.system-modal{width:min(100%,390px);padding:24px;border-radius:16px;background:#fff;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2)}.system-modal-icon{width:48px;height:48px;margin:0 auto 12px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f3ff;color:#1767a8}.system-modal-icon svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.system-modal-title{margin:0 0 8px;color:#173e63;font-size:17px}.system-modal-text{margin:0;color:#63788c;font-size:12px;line-height:1.6}.system-modal-actions{display:flex;justify-content:center;margin-top:20px}.system-btn{border:0;border-radius:8px;padding:10px 20px;background:#1767a8;color:#fff;font-weight:700;cursor:pointer}.system-btn-cancel{margin-right:8px;background:#eef3f8;color:#31516d}.system-btn-danger{background:#b42318}@media(max-width:900px){.assignment-grid{grid-template-columns:1fr}}@media(max-width:560px){.assignment-card{padding:14px}.personnel-tools{grid-template-columns:1fr}.personnel-tools select{grid-column:1/-1}.unassigned-item-bottom{align-items:flex-start;flex-direction:column}.assign-button{width:100%}.static-modal{padding:10px}}
/* Unassigned Requests filter bar */
.request-tools{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-bottom:14px}.request-tools .assignment-search-wrap{grid-column:1/-1}.request-tools .request-search{margin-bottom:0}.request-tools select{width:100%;min-width:0;height:40px;border:1px solid #d8e4ef;border-radius:9px;padding:0 10px;background:#fff;color:#24445f;font:inherit;font-size:12px;cursor:pointer;outline:none}.request-tools select:focus{border-color:#1767a8;box-shadow:0 0 0 3px rgba(23,103,168,.15)}@media(max-width:560px){.request-tools{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const staffData = <?= json_encode($staff, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    let unassignedData = <?= json_encode($unassigned, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

    const $ = id => document.getElementById(id);
    const staffList = $('staffList'), staffSearch = $('staffSearch'), specializationFilter = $('specializationFilter'),
          typeFilter = $('typeFilter'), staffNoResults = $('staffNoResults'),
          requestSearch = $('requestSearch'), requestPriorityFilter = $('requestPriorityFilter'),
          requestNoResults = $('requestNoResults'), unassignedList = $('unassignedList'),
          workloadModal = $('workloadModal'), assignModal = $('assignModal'), systemModal = $('systemModal'),
          assignStaffSelect = $('assignStaffSelect'), assignNotes = $('assignNotes'),
          assignConfirm = $('assignModalConfirm');
    let currentRequest = null;

    /* ---------- System message modal ---------- */
    const icons = {
        question:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        warning:'<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        success:'<svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        danger:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
    };
    function showMessage(icon, title, message, onClose) {
        $('systemModalIcon').innerHTML = icons[icon] || icons.question;
        $('systemModalIcon').className = 'system-modal-icon icon-' + icon;
        $('systemModalTitle').textContent = title;
        $('systemModalText').textContent = message;
        const actions = $('systemModalActions'); actions.innerHTML = '';
        const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'system-btn'; btn.textContent = 'OK';
        btn.addEventListener('click', () => { systemModal.style.display = 'none'; if (onClose) onClose(); });
        actions.appendChild(btn); systemModal.style.display = 'flex';
    }
    function closeModal(m) { m.style.display = 'none'; }

    /* ---------- Personnel filters ---------- */
    function fillTypeFilter() {
        const current = typeFilter.value;
        const types = [...new Set(staffData.map(s => s.specialization_type).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
        typeFilter.innerHTML = '<option value="">All technician types</option>';
        types.forEach(t => { const o = document.createElement('option'); o.value = t; o.textContent = t; typeFilter.appendChild(o); });
        if (types.includes(current)) typeFilter.value = current;
    }
    function filterStaff() {
        const q = staffSearch.value.trim().toLowerCase(), category = specializationFilter.value, type = typeFilter.value;
        let shown = 0;
        staffList.querySelectorAll('.staff-item').forEach(item => {
            const s = staffData[Number(item.dataset.staffIndex)];
            const ok = (!q || [s.name, s.email, s.specialization_category, s.specialization_type].join(' ').toLowerCase().includes(q))
                && (!category || (category === 'Unspecified' ? !s.specialization_category : s.specialization_category === category))
                && (!type || s.specialization_type === type);
            item.style.display = ok ? '' : 'none'; if (ok) shown++;
        });
        staffNoResults.style.display = (shown || !staffData.length) ? 'none' : 'block';
    }
    staffSearch.addEventListener('input', filterStaff);
    specializationFilter.addEventListener('change', filterStaff);
    typeFilter.addEventListener('change', filterStaff);
    fillTypeFilter();

    /* ---------- Request filters ---------- */
    function filterRequests() {
        const q = requestSearch.value.trim().toLowerCase(), pr = requestPriorityFilter.value;
        let shown = 0, total = 0;
        unassignedList.querySelectorAll('.unassigned-item').forEach(item => {
            total++;
            const ok = (!q || item.dataset.requestSearch.includes(q)) && (!pr || item.dataset.requestPriority === pr);
            item.style.display = ok ? '' : 'none'; if (ok) shown++;
        });
        requestNoResults.style.display = (total && !shown) ? 'block' : 'none';
    }
    requestSearch.addEventListener('input', filterRequests);
    requestPriorityFilter.addEventListener('change', filterRequests);

    /* ---------- Workload modal ---------- */
    function openWorkload(staffId) {
        const member = staffData.find(s => s.id === staffId); if (!member) return;
        $('workloadModalTitle').textContent = member.name + "'s Workload";
        $('workloadModalEmail').textContent = member.email || 'No email available';
        const summary = $('workloadSummary'); summary.innerHTML = '';
        [member.specialization_category || 'Unspecified category', member.specialization_type || 'Specialization not set',
         member.tasks.length + ' open ' + (member.tasks.length === 1 ? 'task' : 'tasks')].forEach(label => {
            const span = document.createElement('span'); span.textContent = label; summary.appendChild(span);
        });
        const list = $('workloadTaskList'); list.innerHTML = '';
        if (!member.tasks.length) {
            const li = document.createElement('li'); li.className = 'assignment-empty';
            li.textContent = 'No open requests assigned to this personnel.'; list.appendChild(li);
        }
        member.tasks.forEach(task => {
            const li = document.createElement('li'); li.className = 'workload-task';
            const info = document.createElement('div'); info.className = 'workload-task-info';
            const id = document.createElement('strong'); id.textContent = task.id;
            const issue = document.createElement('span'); issue.textContent = task.issue;
            info.append(id, issue);
            const status = document.createElement('span'); status.className = 'workload-task-status'; status.textContent = task.status;
            li.append(info, status); list.appendChild(li);
        });
        workloadModal.style.display = 'flex';
    }
    staffList.querySelectorAll('.staff-item').forEach(item => item.addEventListener('click', () => openWorkload(item.dataset.staffId)));
    $('workloadModalClose').addEventListener('click', () => closeModal(workloadModal));
    $('workloadModalCloseBtn').addEventListener('click', () => closeModal(workloadModal));

    /* ---------- Keep staff counts + dropdown in sync ---------- */
    function refreshStaffUi() {
        staffList.querySelectorAll('.staff-item').forEach(item => {
            const s = staffData[Number(item.dataset.staffIndex)];
            const meta = item.querySelector('.staff-meta');
            if (meta) meta.textContent = (s.specialization_category || 'Unspecified category') + ' · ' + s.tasks.length + ' open ' + (s.tasks.length === 1 ? 'task' : 'tasks');
        });
        const current = assignStaffSelect.value;
        assignStaffSelect.innerHTML = '<option value="">Select personnel</option>';
        staffData.forEach(s => {
            const o = document.createElement('option'); o.value = s.id;
            o.textContent = s.name + ' — ' + (s.specialization_type || 'Specialization not set') + ' (' + s.tasks.length + ' open)';
            assignStaffSelect.appendChild(o);
        });
        assignStaffSelect.value = current;
    }

    /* ---------- Assign modal ---------- */
    function openAssignModal(btn) {
        currentRequest = { id: btn.dataset.requestId, displayId: btn.dataset.requestDisplayId, issue: btn.dataset.requestIssue,
                           element: btn.closest('.unassigned-item') };
        $('assignModalRequestId').textContent = currentRequest.displayId;
        $('assignModalIssue').textContent = currentRequest.issue;
        assignStaffSelect.value = ''; assignNotes.value = '';
        assignConfirm.disabled = false; assignConfirm.textContent = 'Confirm Assignment';
        assignModal.style.display = 'flex';
    }
    function closeAssignModal() { assignModal.style.display = 'none'; currentRequest = null; }

    unassignedList.addEventListener('click', e => {
        const btn = e.target.closest('.assign-button');
        if (btn && !btn.disabled) { e.stopPropagation(); openAssignModal(btn); }
    });
    $('assignModalClose').addEventListener('click', closeAssignModal);
    $('assignModalCancel').addEventListener('click', closeAssignModal);

    /* ---------- Confirm assignment (saves to Supabase through PHP) ---------- */
    assignConfirm.addEventListener('click', async function () {
        if (!currentRequest) return;
        const staffId = assignStaffSelect.value;
        if (!staffId) { showMessage('warning', 'No personnel selected', 'Please choose a maintenance personnel to assign this request to.'); return; }

        const req = currentRequest;
        assignConfirm.disabled = true; assignConfirm.textContent = 'Assigning...';

        try {
            const body = new URLSearchParams();
            body.set('assignment_api', '1');
            body.set('action', 'assign');
            body.set('request_id', req.id);
            body.set('staff_id', staffId);
            body.set('notes', assignNotes.value.trim());

            const res = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: body
            });
            let data = null;
            try { data = await res.json(); } catch (_) {}
            if (!res.ok || !data || !data.success) {
                throw new Error((data && data.message) || 'Could not assign the request (HTTP ' + res.status + ').');
            }

            // Update local data: add the task to the chosen staff member.
            const member = staffData.find(s => s.id === staffId);
            if (member) member.tasks.push({ request_id: req.id, id: req.displayId, issue: req.issue, status: 'Assigned' });

            // Remove the request from the unassigned list.
            unassignedData = unassignedData.filter(u => u.request_id !== req.id);
            if (req.element) req.element.remove();
            $('unassignedTotal').textContent = unassignedData.length;
            if (!unassignedData.length && !unassignedList.querySelector('.assignment-empty')) {
                const empty = document.createElement('div'); empty.className = 'assignment-empty';
                empty.textContent = 'There are no approved unassigned requests right now.';
                unassignedList.appendChild(empty);
            }
            refreshStaffUi();
            filterRequests();

            closeAssignModal();
            showMessage('success', 'Request assigned', data.message || ('Request ' + req.displayId + ' has been assigned.'));
        } catch (err) {
            assignConfirm.disabled = false; assignConfirm.textContent = 'Confirm Assignment';
            showMessage('danger', 'Assignment failed', err.message || 'Something went wrong. Please try again.');
        }
    });

    /* ---------- Close modals: outside click + Escape ---------- */
    [workloadModal, assignModal].forEach(m => m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; }));
    systemModal.addEventListener('click', e => { if (e.target === systemModal) systemModal.style.display = 'none'; });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { workloadModal.style.display = 'none'; assignModal.style.display = 'none'; systemModal.style.display = 'none'; }
    });
});
</script>
<?php renderFoot(); ?>