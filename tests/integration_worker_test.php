<?php
declare(strict_types=1);

require_once __DIR__ . '/../scripts/maintain_integration_worker.php';

function worker_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$valid = [
    'event_id' => 'f0b3d22c-5e10-4a27-8c03-3a57cd7819fd',
    'event_type' => 'lifecycle.asset.upserted',
    'source' => 'LifeCycleTrack',
    'destination' => 'MaintainTrack',
    'version' => '1.0',
    'occurred_at' => '2026-11-11T10:12:13.123456Z',
    'source_id' => '42',
    'record_type' => 'lifecycle_asset',
    'payload' => ['id' => '42', 'entity_type' => 'equipment', 'name' => 'Generator'],
];
maintain_validate_lifecycle_event($valid);
worker_check(true, 'valid asset event passes validation');

foreach ([
    array_merge($valid, ['source' => 'UtilityTrack']),
    array_merge($valid, ['destination' => 'LifeCycleTrack']),
    array_merge($valid, ['source_id' => '43']),
    array_merge($valid, ['payload' => ['id' => '42', 'entity_type' => 'vehicle']]),
    array_merge($valid, ['event_id' => 'bad']),
] as $invalid) {
    try {
        maintain_validate_lifecycle_event($invalid);
        worker_check(false, 'invalid event rejected');
    } catch (RuntimeException) {
        worker_check(true, 'invalid event rejected');
    }
}

echo "All MaintainTrack integration worker validation checks passed.\n";
