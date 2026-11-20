# LifecycleTrack inbox worker

The worker consumes LifecycleTrack asset events from the Central Server API. It writes the sanitized upstream snapshot to MaintainTrack's integration projection through `maintain_apply_lifecycle_event`; it never creates or edits rows in `equipment` or `facilities`.

## Database setup

Run these scripts in the MaintainTrack Supabase project, in order:

1. `sql/migration_integration_foundation.sql` (if not already applied).
2. `sql/migration_lifecycle_asset_inbox.sql`.

The worker uses MaintainTrack's own Supabase secret key to call the narrowly scoped RPC. Do not use the Central Server key here. The key is read from process environment and is not stored in this repository.

For outbound maintenance events, also apply `sql/migration_central_outbox_capture.sql`. PostgreSQL triggers place sanitized request/result events in `integration_outbox` in the same transaction as the source row. Publish one batch with `php scripts/publish_central_outbox.php 20`; schedule it regularly. Set `CENTRAL_API_BASE_URL`, `MAINTAINTRACK_API_TOKEN`, `MAINTAINTRACK_SUPABASE_URL`, and `MAINTAINTRACK_SUPABASE_SECRET_KEY` in the process environment. Event IDs remain stable across retries.

## Backfill and reconciliation

Apply `sql/migration_central_backfill.sql` once. Run `php scripts/backfill_central_records.php` to resume the initial snapshot. Weekly reconciliation runs use `php scripts/backfill_central_records.php --reconcile --limit=100`; reconciliation assigns fresh event IDs so Central can redeliver state while receiver version checks protect newer replicas. The Central Server registers this weekly task with `scripts/register-reconciliation-tasks.ps1`.

## Environment

Set these values in the worker process environment:

- `CENTRAL_API_BASE_URL`: HTTPS base URL for the Central Server API.
- `MAINTAINTRACK_INBOX_TOKEN`: a distinct random token matching the Central Server's `MAINTAINTRACK_INBOX_TOKEN`.
- `MAINTAINTRACK_SUPABASE_URL`: MaintainTrack's Supabase project URL.
- `MAINTAINTRACK_SUPABASE_SECRET_KEY`: MaintainTrack's project secret key.

## Run

From the MaintainTrack project root, run one batch with `php scripts/maintain_integration_worker.php --once`. Run continuously with `php scripts/maintain_integration_worker.php`; on systems with PHP pcntl support, SIGTERM/SIGINT stop it between batches. On Windows, schedule the one-batch command with Task Scheduler and configure a short recurring interval.

An event is acknowledged only after the MaintainTrack RPC commits both the snapshot and `integration_processed_events` row. Unmatched assets appear in `external_asset_review_queue` for manual mapping. Once a local equipment/facility mapping is added, incoming LifecycleTrack data remains in the external snapshot; existing MaintainTrack business records are not overwritten.
