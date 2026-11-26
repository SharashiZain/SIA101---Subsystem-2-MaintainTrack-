-- Read canonical revisioned assets while preserving existing native IDs.
BEGIN;
ALTER TABLE public.external_asset_snapshots ADD COLUMN IF NOT EXISTS source_revision bigint;

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
    v_source_id TEXT := p_event#>>'{payload,id}';
    v_entity_type TEXT := p_event#>>'{payload,entity_type}';
    v_payload JSONB := p_event->'payload';
    v_source_updated_at TIMESTAMPTZ;
    v_deleted_at TIMESTAMPTZ;
    v_inserted INTEGER;
    v_changed INTEGER;
    v_revision bigint := CASE WHEN p_event->>'version'='1.1' THEN (p_event#>>'{payload,source_revision}')::bigint END;
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
       OR coalesce(p_event->>'version','') NOT IN ('1.0','1.1')
       OR v_event_type NOT IN ('lifecycle.asset.upserted','lifecycle.asset.deleted')
       OR v_source_id IS NULL OR v_source_id='' OR length(v_source_id)>100
       OR v_entity_type NOT IN ('equipment','facility')
       OR jsonb_typeof(v_payload)<>'object'
       OR (p_event->>'source_id') IS DISTINCT FROM
           (CASE WHEN v_revision IS NULL THEN v_source_id ELSE v_entity_type||':'||v_source_id END)
       OR (p_event->>'version'='1.1' AND coalesce(v_revision,0)<1)
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
        (external_system,entity_type,external_asset_id,external_asset_tag,event_id,correlation_id,payload,source_updated_at,received_at,updated_at,deleted_at,source_revision)
    VALUES ('LifeCycleTrack',v_entity_type,v_source_id,v_asset_tag,v_event_id,v_correlation,v_payload,v_source_updated_at,now(),now(),v_deleted_at,v_revision)
    ON CONFLICT (external_system,entity_type,external_asset_id) DO UPDATE SET
        external_asset_tag=CASE WHEN EXCLUDED.deleted_at IS NULL THEN EXCLUDED.external_asset_tag ELSE current_snapshot.external_asset_tag END,
        event_id=EXCLUDED.event_id,
        correlation_id=EXCLUDED.correlation_id,
        payload=CASE WHEN EXCLUDED.deleted_at IS NULL THEN EXCLUDED.payload ELSE current_snapshot.payload END,
        source_updated_at=EXCLUDED.source_updated_at,
        received_at=now(),updated_at=now(),deleted_at=EXCLUDED.deleted_at,source_revision=EXCLUDED.source_revision
    WHERE CASE WHEN EXCLUDED.source_revision IS NULL THEN current_snapshot.source_revision IS NULL AND current_snapshot.source_updated_at<EXCLUDED.source_updated_at
               ELSE current_snapshot.source_revision IS NULL OR current_snapshot.source_revision<EXCLUDED.source_revision END;
    GET DIAGNOSTICS v_changed = ROW_COUNT;

    IF v_changed = 0 THEN
        UPDATE public.integration_processed_events SET processing_status='stale'
         WHERE event_id=v_event_id;
        RETURN 'stale';
    END IF;
    RETURN 'applied';
END;
$$;
NOTIFY pgrst,'reload schema';
COMMIT;
