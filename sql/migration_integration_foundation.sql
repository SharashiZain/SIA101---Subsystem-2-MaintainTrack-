-- Additive MaintainTrack integration foundation for the current schema.
-- Reuse external_asset_links: a row represents a reviewed/confirmed link.
-- Do not create equipment records or change existing business identifiers.

CREATE TABLE IF NOT EXISTS public.integration_outbox (
    event_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_type TEXT NOT NULL,
    source_system TEXT NOT NULL DEFAULT 'MaintainTrack' CHECK (source_system = 'MaintainTrack'),
    destination_system TEXT,
    event_version TEXT NOT NULL DEFAULT '1.0',
    correlation_id UUID,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    source_id TEXT NOT NULL,
    record_type TEXT NOT NULL CHECK (record_type IN ('maintenance_request','maintenance_update')),
    payload JSONB NOT NULL CHECK (jsonb_typeof(payload) = 'object'),
    attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts >= 0),
    last_error TEXT,
    next_attempt_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    delivered_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS integration_outbox_due_idx
    ON public.integration_outbox (next_attempt_at, created_at)
    WHERE delivered_at IS NULL;

CREATE TABLE IF NOT EXISTS public.integration_processed_events (
    event_id UUID PRIMARY KEY,
    event_type TEXT NOT NULL,
    source_system TEXT NOT NULL,
    source_id TEXT NOT NULL,
    record_type TEXT NOT NULL,
    source_updated_at TIMESTAMPTZ,
    processing_status TEXT NOT NULL CHECK (processing_status IN ('applied','duplicate','stale','rejected')),
    processed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    correlation_id UUID
);
CREATE INDEX IF NOT EXISTS integration_processed_events_source_idx
    ON public.integration_processed_events (source_system, record_type, source_id, source_updated_at DESC);

CREATE TABLE IF NOT EXISTS public.integration_errors (
    error_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    event_id UUID,
    event_type TEXT,
    correlation_id UUID,
    error_code TEXT NOT NULL,
    error_message TEXT NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    resolved_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS integration_errors_unresolved_idx
    ON public.integration_errors (occurred_at DESC) WHERE resolved_at IS NULL;

-- Preserve existing external_asset_links.id and local_equipment_id. Add only
-- source version/method metadata; links are written only after explicit match
-- review, so there is no pending/auto-created equipment state in this table.
ALTER TABLE public.external_asset_links
    ADD COLUMN IF NOT EXISTS source_updated_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS match_method TEXT;

DO $asset_link_uniqueness$
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.external_asset_links
        GROUP BY external_system, external_asset_id HAVING count(*) > 1
    ) THEN
        RAISE EXCEPTION 'external_asset_links has duplicate (external_system, external_asset_id) rows; resolve duplicates before adding the idempotency index';
    END IF;
    IF EXISTS (
        SELECT 1 FROM public.external_asset_links
        GROUP BY local_equipment_id HAVING count(*) > 1
    ) THEN
        RAISE EXCEPTION 'external_asset_links has multiple external assets linked to one local_equipment_id; resolve duplicates before adding the uniqueness index';
    END IF;
END
$asset_link_uniqueness$;
CREATE UNIQUE INDEX IF NOT EXISTS external_asset_links_external_uidx
    ON public.external_asset_links (external_system, external_asset_id);
CREATE UNIQUE INDEX IF NOT EXISTS external_asset_links_local_uidx
    ON public.external_asset_links (local_equipment_id);
CREATE INDEX IF NOT EXISTS external_asset_links_tag_idx
    ON public.external_asset_links (external_system, external_asset_tag)
    WHERE external_asset_tag IS NOT NULL;

-- Keep new integration tables private to the server role. Do not alter the
-- existing external_asset_links RLS/policies or its current app permissions.
ALTER TABLE public.integration_outbox ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.integration_processed_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.integration_errors ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON TABLE public.integration_outbox, public.integration_processed_events,
    public.integration_errors FROM anon, authenticated;
GRANT ALL ON TABLE public.integration_outbox, public.integration_processed_events,
    public.integration_errors TO service_role;
GRANT ALL ON TABLE public.external_asset_links TO service_role;
DO $sequence_grants$
DECLARE s TEXT;
BEGIN
    s := pg_get_serial_sequence('public.integration_errors', 'error_id');
    IF s IS NOT NULL THEN EXECUTE format('GRANT USAGE, SELECT ON SEQUENCE %s TO service_role', s); END IF;
END
$sequence_grants$;
