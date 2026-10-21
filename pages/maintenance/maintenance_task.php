<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../Components/layout.php';
require_once __DIR__ . '/../../Config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function taskApi(string $endpoint): array {
    $secret = defined('SUPABASE_SECRET_KEY') ? (string) SUPABASE_SECRET_KEY : '';
    $base = defined('SUPABASE_URL') ? (string) SUPABASE_URL : '';
    if ($secret === '' || $base === '') {
        return ['success'=>false, 'status'=>0, 'data'=>null, 'error'=>'Supabase configuration is missing.'];
    }
    if (!function_exists('curl_init')) {
        return ['success'=>false, 'status'=>0, 'data'=>null, 'error'=>'PHP cURL extension is not enabled.'];
    }
    $ch = curl_init(rtrim($base, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPGET=>true,
        CURLOPT_HTTPHEADER=>[
            'apikey: '.$secret,
            'Authorization: Bearer '.$secret,
            'Accept: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>30
    ]);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return ['success'=>false, 'status'=>$status, 'data'=>null, 'error'=>$curlError ?: 'Could not connect to Supabase.'];
    $decoded = json_decode($raw, true);
    $success = $status >= 200 && $status < 300;
    $error = null;
    if (!$success) $error = is_array($decoded) ? (string)($decoded['message'] ?? $decoded['details'] ?? $decoded['hint'] ?? $raw) : $raw;
    return ['success'=>$success, 'status'=>$status, 'data'=>$decoded, 'error'=>$error];
}
function taskEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function taskDate($value): string {
    if (!$value) return '—';
    try { return (new DateTime((string)$value, new DateTimeZone('Asia/Manila')))->format('M j, Y'); }
    catch (Throwable $e) { return (string)$value; }
}
function taskStatusKey($value): string {
    $s = strtolower(trim((string)$value));
    return match ($s) {
        'in progress', 'in-progress', 'ongoing' => 'progress',
        'completed', 'closed', 'resolved' => 'completed',
        'rejected' => 'rejected',
        'assigned', 'pending', 'on hold', 'on-hold', 'hold' => 'pending',
        default => 'pending'
    };
}
function taskPriorityClass($value): string {
    return match (strtolower(trim((string)$value))) {
        'high', 'urgent', 'critical' => 'tag-high',
        'medium', 'normal' => 'tag-mid',
        default => 'tag-low'
    };
}

$pageError = '';
$tasks = [];
$facilityMap = $floorMap = $roomMap = $equipmentMap = [];
$currentUsername = trim((string)($_SESSION['username'] ?? $_SESSION['user'] ?? ''));
$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));

if ($currentUsername === '' && $currentUserId === '') {
    $pageError = 'Your session has expired. Please log in again.';
} else {
    if ($currentUserId === '') {
        $lookupUsername = $currentUsername === 'user' ? 'maintenance' : $currentUsername;
        $userResult = taskApi('users?username=eq.'.rawurlencode($lookupUsername).'&select=user_id,username,full_name,role,status&limit=1');
        if (!$userResult['success'] || empty($userResult['data'][0])) {
            $pageError = 'Unable to identify your account. Please sign in again.';
        } else {
            $currentUser = $userResult['data'][0];
            $currentUserId = (string)($currentUser['user_id'] ?? '');
            $_SESSION['user_id'] = $currentUserId;
            $_SESSION['username'] = (string)($currentUser['username'] ?? $currentUsername);
            $_SESSION['full_name'] = (string)($currentUser['full_name'] ?? '');
            $_SESSION['role'] = (string)($currentUser['role'] ?? ($_SESSION['role'] ?? ''));
        }
    }

    if ($pageError === '' && strtolower((string)($_SESSION['role'] ?? '')) !== 'maintenance') {
        $pageError = 'This page is available to Maintenance Personnel only.';
    }

    if ($pageError === '') {
        $requestResult = taskApi(
            'maintenance_requests?assigned_to=eq.'.rawurlencode($currentUserId).
            '&select=request_id,display_id,concern,description,category,equipment_category,priority,status,facility_id,floor_id,room_id,equipment_id,specific_area,date_reported,created_at,date_assigned'.
            '&order=created_at.desc.nullslast'
        );
        if (!$requestResult['success']) {
            $pageError = 'Unable to load your tasks. '.(string)($requestResult['error'] ?? 'Please check the maintenance_requests table.');
        } else {
            $tasks = is_array($requestResult['data']) ? $requestResult['data'] : [];
            $facilities = taskApi('facilities?select=facility_id,name');
            $floors = taskApi('facility_floors?select=floor_id,name');
            $rooms = taskApi('facility_rooms?select=room_id,name');
            $equipment = taskApi('equipment?select=equipment_id,display_id,name,type,category');

            foreach (($facilities['data'] ?? []) as $x) $facilityMap[(string)($x['facility_id'] ?? '')] = (string)($x['name'] ?? '');
            foreach (($floors['data'] ?? []) as $x) $floorMap[(string)($x['floor_id'] ?? '')] = (string)($x['name'] ?? '');
            foreach (($rooms['data'] ?? []) as $x) $roomMap[(string)($x['room_id'] ?? '')] = (string)($x['name'] ?? '');
            foreach (($equipment['data'] ?? []) as $x) $equipmentMap[(string)($x['equipment_id'] ?? '')] = $x;

            foreach ($tasks as &$task) {
                $task['status_key'] = taskStatusKey($task['status'] ?? '');
                $task['title'] = trim((string)($task['concern'] ?? '')) ?: 'Maintenance Request';
                $task['id_label'] = trim((string)($task['display_id'] ?? '')) ?: (string)($task['request_id'] ?? '');
                $eq = $equipmentMap[(string)($task['equipment_id'] ?? '')] ?? [];
                $task['chip'] = trim((string)($task['equipment_category'] ?? '')) ?: trim((string)($task['category'] ?? ''));
                if ($task['chip'] === '') $task['chip'] = trim((string)($eq['category'] ?? $eq['type'] ?? ''));
                if ($task['chip'] === '') $task['chip'] = 'General Maintenance';
                $location = [];
                foreach ([
                    $facilityMap[(string)($task['facility_id'] ?? '')] ?? '',
                    $floorMap[(string)($task['floor_id'] ?? '')] ?? '',
                    $roomMap[(string)($task['room_id'] ?? '')] ?? '',
                    trim((string)($task['specific_area'] ?? ''))
                ] as $part) {
                    if ($part !== '' && !in_array($part, $location, true)) $location[] = $part;
                }
                $task['location'] = $location ? implode(' · ', $location) : 'Location not specified';
                if (!empty($task['equipment_id']) && isset($eq['name']) && trim((string)$eq['name']) !== '') {
                    $task['equipment_name'] = (string)$eq['name'];
                } else {
                    $task['equipment_name'] = '';
                }
                $task['priority'] = trim((string)($task['priority'] ?? '')) ?: 'Low';
                $task['date_label'] = taskDate($task['date_reported'] ?? $task['created_at'] ?? null);
            }
            unset($task);

            // First come, first serve: earliest assigned first; completed/rejected at the bottom
            usort($tasks, function ($a, $b) {
                $doneA = in_array($a['status_key'], ['completed', 'rejected'], true) ? 1 : 0;
                $doneB = in_array($b['status_key'], ['completed', 'rejected'], true) ? 1 : 0;
                if ($doneA !== $doneB) return $doneA <=> $doneB;
                $timeA = strtotime((string) ($a['date_assigned'] ?: $a['created_at'])) ?: PHP_INT_MAX;
                $timeB = strtotime((string) ($b['date_assigned'] ?: $b['created_at'])) ?: PHP_INT_MAX;
                return $timeA <=> $timeB;
            });
        }
    }
}

$stats = [
    ['label'=>'My Task', 'value'=>count($tasks), 'filter'=>''],
    ['label'=>'Pending', 'value'=>count(array_filter($tasks, fn($t)=>$t['status_key']==='pending')), 'filter'=>'pending'],
    ['label'=>'In Progress', 'value'=>count(array_filter($tasks, fn($t)=>$t['status_key']==='progress')), 'filter'=>'progress'],
    ['label'=>'Completed', 'value'=>count(array_filter($tasks, fn($t)=>$t['status_key']==='completed')), 'filter'=>'completed'],
    ['label'=>'Rejected', 'value'=>count(array_filter($tasks, fn($t)=>$t['status_key']==='rejected')), 'filter'=>'rejected'],
];

renderHead('My Tasks', 'Task.css', 'maintenance');
?>
<div class="page">
  <div class="one-card">
    <?php renderPageHeader('My Tasks', 'Maintenance Personnel Portal • Barangay Gulod', 'maintenance'); ?>

    <?php if ($pageError !== ''): ?>
      <div class="task-alert" role="alert"><?= taskEscape($pageError) ?></div>
    <?php endif; ?>

    <section class="stat-grid">
      <?php foreach ($stats as $item): ?>
        <div class="stat-card" data-filter="<?= taskEscape($item['filter']) ?>" role="button" tabindex="0">
          <span class="stat-label"><?= taskEscape($item['label']) ?></span>
          <span class="stat-value"><?= (int)$item['value'] ?></span>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="filter-bar">
      <div class="search-input">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <input type="text" placeholder="Search task..." id="taskSearch" aria-label="Search tasks">
      </div>
      <select class="filter-select" id="taskStatusFilter" aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="pending">Pending / Assigned</option>
        <option value="progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="rejected">Rejected</option>
      </select>
      <select class="filter-select" id="taskFacilityFilter" aria-label="Filter by facility">
        <option value="">All Facilities</option>
        <?php
        $taskFacilityNames = [];
        foreach ($tasks as $task) {
            $name = $facilityMap[(string)($task['facility_id'] ?? '')] ?? '';
            if ($name !== '') $taskFacilityNames[$name] = true;
        }
        foreach (array_keys($taskFacilityNames) as $facilityName):
        ?>
          <option value="<?= taskEscape($facilityName) ?>"><?= taskEscape($facilityName) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="reset-btn" type="button" id="taskReset">Reset</button>
    </section>

    <section class="task-list" id="taskList">
      <?php foreach ($tasks as $task):
        $facilityName = $facilityMap[(string)($task['facility_id'] ?? '')] ?? '';
        $detailUrl = 'maintenance_request.php?id='.rawurlencode((string)($task['request_id'] ?? ''));
      ?>
        <article class="task-card"
          data-status="<?= taskEscape($task['status_key']) ?>"
          data-facility="<?= taskEscape($facilityName) ?>"
          data-priority="<?= taskEscape(strtolower((string)$task['priority'])) ?>"
          data-id="<?= taskEscape($task['request_id'] ?? '') ?>"
          data-search="<?= taskEscape(strtolower(implode(' ', [
            $task['title'], $task['id_label'], $task['description'] ?? '', $task['chip'],
            $task['location'], $task['equipment_name'] ?? '', $task['priority'], $task['status'] ?? ''
          ]))) ?>"
          tabindex="0"
          onclick="window.location.href='<?= taskEscape($detailUrl) ?>'">
          <div class="task-card-main">
            <div class="task-card-top">
              <h3><?= taskEscape($task['title']) ?></h3>
              <span class="tag <?= taskEscape(taskPriorityClass($task['priority'])) ?>"><?= taskEscape($task['priority']) ?></span>
            </div>
            <span class="task-ref"><?= taskEscape($task['id_label']) ?> · <?= taskEscape($task['location']) ?></span>
            <?php if (!empty($task['equipment_name'])): ?><span class="task-ref">Equipment: <?= taskEscape($task['equipment_name']) ?></span><?php endif; ?>
            <p class="task-desc"><?= taskEscape($task['description'] ?? 'No additional description provided.') ?></p>
            <div class="task-meta">
              <span class="meta-chip"><?= taskEscape($task['chip']) ?></span>
              <span class="meta-date"><?= taskEscape($task['date_label']) ?></span>
              <span class="task-status-label"><?= taskEscape($task['status'] ?? 'Pending') ?></span>
            </div>
          </div>
          <div class="task-card-right">
            <button class="btn-primary" type="button" onclick="event.stopPropagation(); window.location.href='<?= taskEscape($detailUrl) ?>'">View Details</button>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <div id="taskNoResults" style="display:<?= (!$tasks && $pageError === '') ? 'block' : 'none' ?>; text-align:center; padding:40px; color:#687985;">
      <p style="font-size:15px;font-weight:600;"><?= $pageError !== '' ? 'Tasks could not be displayed.' : 'No tasks have been assigned to you yet.' ?></p>
    </div>
    <nav class="pagination" id="taskPagination" aria-label="Tasks pagination"></nav>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('taskSearch');
  const statusFilter = document.getElementById('taskStatusFilter');
  const facilityFilter = document.getElementById('taskFacilityFilter');
  const resetBtn = document.getElementById('taskReset');
  const noResults = document.getElementById('taskNoResults');
  const cards = Array.from(document.querySelectorAll('.task-card'));
  const pagination = document.getElementById('taskPagination');
  const pageSize = 6;
  let currentPage = 1;
  let filteredCards = cards.slice();

  function renderPage() {
    const query = searchInput.value.trim().toLowerCase();
    const status = statusFilter.value;
    const facility = facilityFilter.value;
    filteredCards = cards.filter(card => {
      const matchesSearch = (card.dataset.search || card.textContent.toLowerCase()).includes(query);
      const matchesStatus = !status || card.dataset.status === status;
      const matchesFacility = !facility || card.dataset.facility === facility;
      return matchesSearch && matchesStatus && matchesFacility;
    });

    const pages = Math.max(1, Math.ceil(filteredCards.length / pageSize));
    if (currentPage > pages) currentPage = pages;
    cards.forEach(card => card.style.display = 'none');
    filteredCards.slice((currentPage - 1) * pageSize, currentPage * pageSize).forEach(card => card.style.display = 'grid');
    noResults.style.display = filteredCards.length === 0 ? 'block' : 'none';
    pagination.innerHTML = '';

    if (pages > 1) {
      for (let i = 1; i <= pages; i++) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-num' + (i === currentPage ? ' active' : '');
        button.textContent = String(i);
        button.dataset.page = String(i);
        button.addEventListener('click', () => {
          currentPage = i;
          renderPage();
          document.getElementById('taskList').scrollIntoView({behavior:'smooth', block:'start'});
        });
        pagination.appendChild(button);
      }
    }
  }

  [searchInput, statusFilter, facilityFilter].forEach(el => el.addEventListener(el === searchInput ? 'input' : 'change', () => {
    currentPage = 1;
    renderPage();
  }));
  resetBtn.addEventListener('click', function () {
    searchInput.value = '';
    statusFilter.value = '';
    facilityFilter.value = '';
    currentPage = 1;
    renderPage();
  });

  document.querySelectorAll('.stat-card[data-filter]').forEach(card => {
    function activate() {
      statusFilter.value = card.dataset.filter || '';
      currentPage = 1;
      renderPage();
      document.getElementById('taskList').scrollIntoView({behavior:'smooth', block:'start'});
    }
    card.addEventListener('click', activate);
    card.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(); } });
  });
  cards.forEach(card => card.addEventListener('keydown', e => {
    if (e.key === 'Enter') window.location.href = card.getAttribute('onclick').match(/href='([^']+)'/)[1];
  }));
  renderPage();
});
</script>
<?php renderFoot(); ?>
</html>