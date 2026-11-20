-- LifecycleTrack inbox projection for MaintainTrack.
-- Apply after migration_integration_foundation.sql.
-- This stores external snapshots and review candidates, never changes the
-- existing MaintainTrack facilities/equipment business rows.

CREATE TABLE IF NOT EXISTS public.external_asset_snapshots (
    external_system TEXT NOT NULL DEFAULT 'LifeCycleTrack' CHECK (external_system = 'LifeCycleTrack'),
    entity_type TEXT NOT NULL CHECK (entity_type IN ('equipment','facility')),
    external_asset_id TEXT NOT NULL,
    external_asset_tag TEXT,
    event_id UUID NOT NULL,
    correlation_id UUID,
    payload JSONB NOT NULL CHECK (jsonb_typeof(payload) = 'object'),
    source_updated_at TIMESTAMPTZ NOT NULL,
    received_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at TIMESTAMPTZ,
    PRIMARY KEY (external_system, entity_type, external_asset_id)
);
CREATE INDEX IF NOT EXISTS external_asset_snapshots_review_idx
    ON public.external_asset_snapshots (entity_type, received_at)
    WHERE deleted_at IS NULL;

CREATE TABLE IF NOT EXISTS public.external_facility_links (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    external_system TEXT NOT NULL DEFAULT 'LifeCycleTrack' CHECK (external_system = 'LifeCycleTrack'),
    external_facility_id TEXT NOT NULL,
    local_facility_id UUID NOT NULL REFERENCES public.facilities(facility_id) ON DELETE RESTRICT,
    external_facility_name TEXT,
    match_method TEXT,
    mapped_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (external_system, external_facility_id),
    UNIQUE (local_facility_id)
);

CREATE OR REPLACE VIEW public.external_asset_review_queue AS
SELECT s.external_system,s.entity_type,s.external_asset_id,s.external_asset_tag,
       s.payload,s.source_updated_at,s.received_at,s.deleted_at
FROM public.external_asset_snapshots s
WHERE s.deleted_at IS NULL
  AND ((s.entity_type='equipment' AND NOT EXISTS (
        SELECT 1 FROM public.external_asset_links l
        WHERE l.external_system=s.external_system AND l.external_asset_id=s.external_asset_id))
    OR (s.entity_type='facility' AND NOT EXISTS (
        SELECT 1 FROM public.external_facility_links l
        WHERE l.external_system=s.external_system AND l.external_facility_id=s.external_asset_id)));

CREATE OR REPLACE FUNCTION public.maintain_apply_lifecycle_event(p_event JSONB)
RETURNS TEXT
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_event_id UUID;
    v_correlation UUID;
    v_event_type TEXT := p_event->>'event_type';
    v_source TEXT := p_event->>'source';
    v_destination TEXT := p_event->>'destination';
    v_record_type TEXT := p_event->>'record_type';
    v_source_id TEXT := p_event->>'source_id';
    v_entity_type TEXT := p_event#>>'{payload,entity_type}';
    v_payload JSONB := p_event->'payload';
    v_source_updated_at TIMESTAMPTZ;
    v_deleted_at TIMESTAMPTZ;
    v_inserted INTEGER;
    v_changed INTEGER;
    v_asset_tag TEXT;
BEGIN
    BEGIN
        v_event_id := (p_event->>'event_id')::UUID;
        v_correlation := NULLIF(p_event->>'correlation_id','')::UUID;
        v_source_updated_at := (p_event->>'occurred_at')::TIMESTAMPTZ;
    EXCEPTION WHEN OTHERS THEN
        RAISE EXCEPTION 'Malformed event identifier or timestamp' USING ERRCODE='22023';
    END;

    IF jsonb_typeof(p_event)<>'object'
       OR v_source IS DISTINCT FROM 'LifeCycleTrack'
       OR v_destination IS DISTINCT FROM 'MaintainTrack'
       OR v_record_type IS DISTINCT FROM 'lifecycle_asset'
       OR p_event->>'version' IS DISTINCT FROM '1.0'
       OR v_event_type NOT IN ('lifecycle.asset.upserted','lifecycle.asset.deleted')
       OR v_source_id IS NULL OR v_source_id='' OR length(v_source_id)>100
       OR v_entity_type NOT IN ('equipment','facility')
       OR jsonb_typeof(v_payload)<>'object'
       OR v_payload->>'id' IS DISTINCT FROM v_source_id
       OR v_source_updated_at IS NULL THEN
        RAISE EXCEPTION 'Event does not match the LifecycleTrack asset contract' USING ERRCODE='22023';
    END IF;

    INSERT INTO public.integration_processed_events
        (event_id,event_type,source_system,source_id,record_type,source_updated_at,processing_status,correlation_id)
    VALUES (v_event_id,v_event_type,v_source,v_source_id,v_record_type,v_source_updated_at,'applied',v_correlation)
    ON CONFLICT (event_id) DO NOTHING;
    GET DIAGNOSTICS v_inserted = ROW_COUNT;
    IF v_inserted = 0 THEN RETURN 'duplicate'; END IF;

    v_deleted_at := CASE WHEN v_event_type='lifecycle.asset.deleted' THEN v_source_updated_at ELSE NULL END;
    v_asset_tag := NULLIF(v_payload->>'asset_tag','');
    INSERT INTO public.external_asset_snapshots AS current_snapshot
        (external_system,entity_type,external_asset_id,external_asset_tag,event_id,correlation_id,payload,source_updated_at,received_at,updated_at,deleted_at)
    VALUES ('LifeCycleTrack',v_entity_type,v_source_id,v_asset_tag,v_event_id,v_correlation,v_payload,v_source_updated_at,now(),now(),v_deleted_at)
    ON CONFLICT (external_system,entity_type,external_asset_id) DO UPDATE SET
        external_asset_tag=CASE WHEN EXCLUDED.deleted_at IS NULL THEN EXCLUDED.external_asset_tag ELSE current_snapshot.external_asset_tag END,
        event_id=EXCLUDED.event_id,
        correlation_id=EXCLUDED.correlation_id,
        payload=CASE WHEN EXCLUDED.deleted_at IS NULL THEN EXCLUDED.payload ELSE current_snapshot.payload END,
        source_updated_at=EXCLUDED.source_updated_at,
        received_at=now(),updated_at=now(),deleted_at=EXCLUDED.deleted_at
    WHERE current_snapshot.source_updated_at < EXCLUDED.source_updated_at;
    GET DIAGNOSTICS v_changed = ROW_COUNT;

    IF v_changed = 0 THEN
        UPDATE public.integration_processed_events SET processing_status='stale'
         WHERE event_id=v_event_id;
        RETURN 'stale';
    END IF;
    RETURN 'applied';
END;
$$;

ALTER TABLE public.external_asset_snapshots ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.external_facility_links ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON TABLE public.external_asset_snapshots, public.external_facility_links,
    public.external_asset_review_queue FROM anon, authenticated;
GRANT ALL ON TABLE public.external_asset_snapshots, public.external_facility_links TO service_role;
GRANT SELECT ON TABLE public.external_asset_review_queue TO service_role;
REVOKE ALL ON FUNCTION public.maintain_apply_lifecycle_event(JSONB) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_apply_lifecycle_event(JSONB) TO service_role;
