<?php
/* ------------------------------------------------------------
   DUPLICATE REPORT CHECK (shared by Regular "My Report" and Admin "New Request")

   A new report is treated as a duplicate when ALL of these are true:
     1. an OPEN request exists (status is not Completed / Cancelled / Rejected)
     2. it is in the SAME room, and not for a different piece of equipment
     3. its concern is the same or very similar (same words, or >= 75% alike
        after ignoring upper/lower case, punctuation and extra spaces)

   Usage:
     $dup = findDuplicateReport($fetch, $roomId, $equipmentId, $concern);
     if ($dup) { ... $dup['display_id'], $dup['status'], $dup['concern'], $dup['date_reported'] ... }
   ------------------------------------------------------------ */

const DUPLICATE_CLOSED_STATUSES = ['Completed', 'Cancelled', 'Rejected'];
const DUPLICATE_SIMILARITY_PERCENT = 75.0;

/* Every report is stored in ALL CAPS so the same concern is always written the same way. */
if (!function_exists('reportUppercase')) {
    function reportUppercase(string $text): string
    {
        return mb_strtoupper(trim($text), 'UTF-8');
    }
}

/* "Air-con  not COOLING!" -> "AIR CON NOT COOLING" */
if (!function_exists('reportNormalizeText')) {
    function reportNormalizeText(string $text): string
    {
        $text = mb_strtoupper($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}

if (!function_exists('reportConcernsMatch')) {
    function reportConcernsMatch(string $a, string $b): bool
    {
        $a = reportNormalizeText($a);
        $b = reportNormalizeText($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        /* One contains the other ("AIRCON NOT COOLING" vs "AIRCON NOT COOLING AT ALL") */
        $shorter = strlen($a) <= strlen($b) ? $a : $b;
        $longer  = $shorter === $a ? $b : $a;
        if (strlen($shorter) >= 8 && strpos($longer, $shorter) !== false) {
            return true;
        }
        similar_text($a, $b, $percent);
        return $percent >= DUPLICATE_SIMILARITY_PERCENT;
    }
}

/**
 * @param callable $fetch  function(string $endpoint): ?array -> rows from Supabase REST, or null when the call failed
 * @return array|null      the matching open request, or null when this is not a duplicate
 *
 * The query is kept simple on purpose (only "same room"); closed statuses and the equipment rule are
 * applied here in PHP, so one unsupported filter can never make the check silently find nothing.
 * If the database call itself fails, $GLOBALS['duplicateCheckFailed'] is set to true.
 */
if (!function_exists('findDuplicateReport')) {
    function findDuplicateReport(callable $fetch, string $roomId, string $equipmentId, string $concern): ?array
    {
        $GLOBALS['duplicateCheckFailed'] = false;
        $roomId      = trim($roomId);
        $equipmentId = trim($equipmentId);
        if (reportNormalizeText($concern) === '' || $roomId === '') {
            return null;
        }

        $rows = $fetch(
            'maintenance_requests?select=request_id,display_id,concern,status,date_reported,assigned_to,equipment_id'
            . '&room_id=eq.' . rawurlencode($roomId)
            . '&order=date_reported.desc&limit=200'
        );
        if (!is_array($rows)) {
            $GLOBALS['duplicateCheckFailed'] = true;
            return null;
        }

        foreach ($rows as $row) {
            if (in_array((string) ($row['status'] ?? ''), DUPLICATE_CLOSED_STATUSES, true)) {
                continue; /* finished or closed requests do not count */
            }
            /* Both name a piece of equipment and it is a different one -> a different problem */
            $rowEquipment = trim((string) ($row['equipment_id'] ?? ''));
            if ($equipmentId !== '' && $rowEquipment !== '' && strcasecmp($equipmentId, $rowEquipment) !== 0) {
                continue;
            }
            if (!reportConcernsMatch($concern, (string) ($row['concern'] ?? ''))) {
                continue;
            }

            /* Who is looking at it? (assigned_to holds the maintenance person's user_id) */
            $handlerName = '';
            $assignedTo  = trim((string) ($row['assigned_to'] ?? ''));
            if ($assignedTo !== '') {
                $u = $fetch('users?select=full_name,username&user_id=eq.' . rawurlencode($assignedTo) . '&limit=1');
                $handlerName = is_array($u) ? trim((string) ($u[0]['full_name'] ?? $u[0]['username'] ?? '')) : '';
            }
            return [
                'handler_name'  => $handlerName,
                'assigned'      => $assignedTo !== '',
                'request_id'    => (string) ($row['request_id'] ?? ''),
                'display_id'    => (string) ($row['display_id'] ?? ''),
                'concern'       => (string) ($row['concern'] ?? ''),
                'status'        => (string) ($row['status'] ?? ''),
                'date_reported' => (string) ($row['date_reported'] ?? ''),
            ];
        }
        return null;
    }
}

/* Short text shown in the warning, e.g. "MT-00123 (Pending, reported Oct 20, 2026)" */
if (!function_exists('duplicateReportLabel')) {
    function duplicateReportLabel(array $dup): string
    {
        $date = '';
        if (!empty($dup['date_reported']) && ($ts = strtotime($dup['date_reported']))) {
            $date = ', reported ' . (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y');
        }
        return ($dup['display_id'] ?: 'An existing request') . ' (' . ($dup['status'] ?: 'Open') . $date . ')';
    }
}

/* True when somebody has already picked the request up (assigned or in progress). */
if (!function_exists('duplicateReportIsHandled')) {
    function duplicateReportIsHandled(array $dup): bool
    {
        return !empty($dup['assigned']) || in_array($dup['status'] ?? '', ['Assigned', 'In Progress'], true);
    }
}

/* One sentence saying who is on it, e.g. "JUAN DELA CRUZ is already working on it." */
if (!function_exists('duplicateReportProgress')) {
    function duplicateReportProgress(array $dup): string
    {
        $who = trim((string) ($dup['handler_name'] ?? ''));
        if (($dup['status'] ?? '') === 'In Progress') {
            return ($who !== '' ? $who : 'A maintenance personnel') . ' is already working on it.';
        }
        if (duplicateReportIsHandled($dup)) {
            return 'It is already assigned to ' . ($who !== '' ? $who : 'a maintenance personnel') . ' and will be looked at soon.';
        }
        return 'It is already submitted and is waiting to be reviewed and assigned.';
    }
}