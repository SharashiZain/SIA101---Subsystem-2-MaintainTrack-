<?php
declare(strict_types=1);

/* =========================================================
   Priority scoring + priority queue

   score = 0.40 × urgency
         + 0.25 × facility importance
         + 0.20 × age
         + 0.15 × recurrence

   Every term is on a 1–3 scale so no single term drowns out the others:
     urgency     High = 3, Medium = 2, Low = 1  (Urgent = 4, only an Admin can set it by hand)
     facility    3 = lab / records / secretary, 2 = offices and halls, 1 = hallway / storage / covered court
     age         days waiting, capped at 10 days, scaled to 1–3  (older = higher, so low-urgency items never starve)
     recurrence  other reports of the SAME equipment in the last 30 days, capped at 3, scaled to 1–3
========================================================= */

const PRIORITY_WEIGHTS = ['urgency' => 0.40, 'facility' => 0.25, 'age' => 0.20, 'recurrence' => 0.15];
const PRIORITY_AGE_CAP_DAYS = 10;
const PRIORITY_RECURRENCE_CAP = 3;
const PRIORITY_RECURRENCE_WINDOW_DAYS = 30;


const PRIORITY_FACILITY_RULES = [
    ['records',   3],
    ['lab',       3],
    ['secretary', 3],
    ['server',    3],
    ['hallway',   1],
    ['storage',   1],
    ['court',     1],
    ['office',    2],
    ['hall',      2],
];
const PRIORITY_FACILITY_DEFAULT = 2;

/* Facility importance by FACILITY NAME. When the facility is listed here its points are used and the
   room-name keywords above are skipped. Names ignore capitals, spaces and hyphens
   ("Multi-Purpose Hall" = "multipurpose hall"). Facilities not listed fall back to the keyword rules. */
const PRIORITY_FACILITY_BY_NAME = [
    'barangayhall'      => 3,   // Barangay Hall: everything in it is important
    'multipurposehall'  => 1,   // Multi-Purpose Hall: less important
];

/* Reports about these (equipment name, equipment type or the words of the concern) count as High urgency.
   Anything else is Medium. Add words here, for example 'router', 'wifi', 'cctv'. */
const PRIORITY_IMPORTANT_EQUIPMENT_WORDS = ['computer', 'printer'];

/* Score -> priority level, used when a request is submitted (see priorityAutoLevel).
   score >= HIGH_AT is High, score >= MEDIUM_AT is Medium, anything lower is Low. */
const PRIORITY_LEVEL_HIGH_AT = 2.2;
const PRIORITY_LEVEL_MEDIUM_AT = 1.6;

function priorityUrgencyValue($urgency): int {
    return match (strtolower(trim((string)$urgency))) {
        'urgent' => 4,
        'high' => 3,
        'low' => 1,
        default => 2, // Medium, or not set yet
    };
}

function priorityNormalizeName(string $name): string {
    return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
}

/* High when the report is about an important kind of equipment (see PRIORITY_IMPORTANT_EQUIPMENT_WORDS), otherwise Medium. */
function priorityEquipmentUrgency(array $req): string {
    $text = implode(' ', [
        (string)($req['equipment_name'] ?? ''), (string)($req['equipment_type'] ?? ''),
        (string)($req['equipment'] ?? ''), (string)($req['concern'] ?? ''),
    ]);
    foreach (PRIORITY_IMPORTANT_EQUIPMENT_WORDS as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '/i', $text)) return 'High';
    }
    return 'Medium';
}

function priorityFacilityImportance(string $facility, string $room = ''): int {
    $byName = PRIORITY_FACILITY_BY_NAME[priorityNormalizeName($facility)] ?? null;
    if ($byName !== null) return $byName;
    foreach ([$room, $facility] as $text) {
        if (trim($text) === '') continue;
        foreach (PRIORITY_FACILITY_RULES as [$word, $weight]) {
            if (preg_match('/\b' . preg_quote($word, '/') . '/i', $text)) return $weight;
        }
    }
    return PRIORITY_FACILITY_DEFAULT;
}

function priorityAgeScore(float $days): float {
    return 1 + 2 * (min(max($days, 0), PRIORITY_AGE_CAP_DAYS) / PRIORITY_AGE_CAP_DAYS);
}

function priorityRecurrenceScore(int $count): float {
    return 1 + 2 * (min(max($count, 0), PRIORITY_RECURRENCE_CAP) / PRIORITY_RECURRENCE_CAP);
}

/* Reads the request fields this file needs. Works with the Approvals sample data now
   and with Supabase rows later (pass 'reported_at', 'equipment_key' and 'room' when you have them). */
function priorityRequestTime(array $req): int {
    $raw = $req['reported_at'] ?? $req['date_reported'] ?? $req['date'] ?? '';
    $ts = strtotime((string)$raw);
    return $ts === false ? time() : $ts;
}

function priorityEquipmentKey(array $req): string {
    if (!empty($req['equipment_key'])) return (string)$req['equipment_key'];
    if (!empty($req['equipment_id'])) return (string)$req['equipment_id'];
    return strtolower(trim((string)($req['facility'] ?? '')) . '|' . trim((string)($req['equipment'] ?? '')));
}

/* Reports of the same equipment in the last 30 days, not counting the request itself. */
function priorityRecurrenceCount(array $req, array $allRequests, int $nowTs): int {
    $key = priorityEquipmentKey($req);
    if ($key === '|' || $key === '') return 0;
    $since = $nowTs - PRIORITY_RECURRENCE_WINDOW_DAYS * 86400;
    $count = 0;
    foreach ($allRequests as $other) {
        if (($other['id'] ?? null) === ($req['id'] ?? null)) continue;
        if (priorityEquipmentKey($other) !== $key) continue;
        $ts = priorityRequestTime($other);
        if ($ts >= $since && $ts <= $nowTs) $count++;
    }
    return $count;
}

function calculatePriorityScore(array $req, array $allRequests, ?DateTimeImmutable $now = null): array {
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $nowTs = $now->getTimestamp();

    $urgency    = priorityUrgencyValue($req['urgency'] ?? '');
    $facility   = priorityFacilityImportance((string)($req['facility'] ?? ''), (string)($req['room'] ?? ''));
    $ageDays    = max(0, (int)floor(($nowTs - priorityRequestTime($req)) / 86400));
    $age        = priorityAgeScore((float)$ageDays);
    $recCount   = priorityRecurrenceCount($req, $allRequests, $nowTs);
    $recurrence = priorityRecurrenceScore($recCount);

    $score = PRIORITY_WEIGHTS['urgency'] * $urgency
           + PRIORITY_WEIGHTS['facility'] * $facility
           + PRIORITY_WEIGHTS['age'] * $age
           + PRIORITY_WEIGHTS['recurrence'] * $recurrence;

    return [
        'score' => round($score, 2),
        'parts' => [
            'urgency' => $urgency, 'facility' => $facility,
            'age' => round($age, 2), 'age_days' => $ageDays,
            'recurrence' => round($recurrence, 2), 'recurrence_count' => $recCount,
        ],
    ];
}

/* Builds the priority queue (a max-heap, SplPriorityQueue) and returns the requests highest score first.
   Ties go to the request that has waited longer, then to the original order.
   $context = every request to count recurrence against (defaults to $requests itself). */
function buildPriorityQueue(array $requests, ?DateTimeImmutable $now = null, ?array $context = null): array {
    $queue = new SplPriorityQueue();
    $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);

    foreach (array_values($requests) as $i => $req) {
        $result = calculatePriorityScore($req, $context ?? $requests, $now);
        $req['_score'] = $result['score'];
        $req['_score_parts'] = $result['parts'];
        $queue->insert($req, [$result['score'], -priorityRequestTime($req), -$i]);
    }

    $ordered = [];
    $rank = 1;
    while (!$queue->isEmpty()) {
        $item = $queue->extract();
        $item['_rank'] = $rank++;
        $ordered[] = $item;
    }
    return $ordered;
}

function priorityScoreTooltip(array $req): string {
    $p = $req['_score_parts'] ?? [];
    if (!$p) return '';
    return sprintf(
        'Urgency %d · Facility %d · Age %.1f (%d d) · Recurrence %.1f (%d×)',
        $p['urgency'], $p['facility'], $p['age'], $p['age_days'], $p['recurrence'], $p['recurrence_count']
    );
}
/* ---------------------------------------------------------
   Priority LEVEL (Low / Medium / High)
   --------------------------------------------------------- */
function priorityLevelFromScore(float $score): string {
    if ($score >= PRIORITY_LEVEL_HIGH_AT) return 'High';
    if ($score >= PRIORITY_LEVEL_MEDIUM_AT) return 'Medium';
    return 'Low';
}

/* Works out the priority level of a request.
   A brand-new request has no urgency yet (the reporter does not choose one), so urgency comes from the kind of
   equipment: High for computers and printers, Medium for everything else (priorityEquipmentUrgency).
   The rest of the score is facility importance (by facility name), how long ago it was reported and how often
   the same equipment was reported in the last 30 days.
   $req needs: id, facility (name), room (name, optional), equipment_id (optional), date_reported,
   and for the urgency: equipment_name, equipment_type, concern (all optional).
   $recentRequests = other requests (id, equipment_id, date_reported) from the last 30 days.
   Returns ['level' => 'Low|Medium|High', 'score' => float, 'parts' => array]. */
function priorityAutoLevel(array $req, array $recentRequests, ?DateTimeImmutable $now = null): array {
    $req['urgency'] = priorityEquipmentUrgency($req);
    $result = calculatePriorityScore($req, $recentRequests, $now);
    return [
        'level' => priorityLevelFromScore($result['score']),
        'urgency' => $req['urgency'],
        'score' => $result['score'],
        'parts' => $result['parts'],
    ];
}