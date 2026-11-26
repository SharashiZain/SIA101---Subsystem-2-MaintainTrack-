-- Revision-safe snapshots and tombstone reconciliation.
BEGIN;
CREATE OR REPLACE FUNCTION public.maintain_enqueue_central_backfill_batch(
    p_job_name TEXT,p_events JSONB,p_last_source_id TEXT,p_completed BOOLEAN DEFAULT FALSE
) RETURNS INTEGER LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE
    v_item jsonb;
    v_event jsonb;
    v_saved jsonb;
    v_scanned integer;
    v_inserted integer:=0;
    v_source text;
    v_type text:=CASE p_job_name WHEN 'maintain_requests' THEN 'maintenance_request' ELSE 'maintenance_update' END;
    v_seed integer;
BEGIN
    IF p_job_name NOT IN ('maintain_requests','maintain_updates')
       OR jsonb_typeof(COALESCE(p_events,'[]'::jsonb))<>'array' THEN
        RAISE EXCEPTION 'Invalid MaintainTrack backfill batch' USING ERRCODE='22023';
    END IF;
    v_scanned:=jsonb_array_length(COALESCE(p_events,'[]'::jsonb));
    FOR v_item IN SELECT value FROM jsonb_array_elements(COALESCE(p_events,'[]'::jsonb)) LOOP
        v_source:=v_item->>'source_id';
        v_event:=v_item->'event';
        IF v_source IS NULL OR (v_event->>'record_type') IS DISTINCT FROM v_type
            OR (v_event#>>'{payload,id}') IS DISTINCT FROM v_source THEN
            RAISE EXCEPTION 'Invalid MaintainTrack snapshot' USING ERRCODE='22023';
        END IF;
        IF v_type='maintenance_request' THEN
            PERFORM 1 FROM public.maintenance_requests WHERE request_id=v_source::uuid FOR SHARE;
        ELSE
            PERFORM 1 FROM public.maintenance_updates WHERE update_id=v_source::uuid FOR SHARE;
        END IF;
        IF NOT FOUND THEN CONTINUE; END IF;
        v_event:=v_event||jsonb_build_object('version','1.1','payload',
            jsonb_strip_nulls(v_event->'payload')||jsonb_build_object('source_revision','1'));
        INSERT INTO public.maintain_sync_record_head(record_type,source_id,revision,event)
        VALUES(v_type,v_source,1,v_event) ON CONFLICT DO NOTHING;
        GET DIAGNOSTICS v_seed=ROW_COUNT;
        SELECT event INTO v_saved FROM public.maintain_sync_record_head
         WHERE record_type=v_type AND source_id=v_source FOR SHARE;
        IF v_seed=0 THEN v_saved:=v_saved||jsonb_build_object('event_id',gen_random_uuid()); END IF;
        INSERT INTO public.integration_outbox(event_id,event_type,source_system,event_version,occurred_at,source_id,record_type,payload)
        VALUES((v_saved->>'event_id')::uuid,v_saved->>'event_type','MaintainTrack','1.1',
            (v_saved->>'occurred_at')::timestamptz,v_source,v_type,v_saved) ON CONFLICT DO NOTHING;
        IF FOUND THEN v_inserted:=v_inserted+1; END IF;
    END LOOP;
    INSERT INTO public.maintain_central_backfill_checkpoint(job_name,last_source_id,status,scanned_count,updated_at,completed_at)
    VALUES(p_job_name,p_last_source_id,CASE WHEN p_completed THEN 'completed' ELSE 'running' END,v_scanned,now(),CASE WHEN p_completed THEN now() ELSE NULL END)
    ON CONFLICT(job_name) DO UPDATE SET last_source_id=COALESCE(EXCLUDED.last_source_id,maintain_central_backfill_checkpoint.last_source_id),
      status=EXCLUDED.status,scanned_count=maintain_central_backfill_checkpoint.scanned_count+EXCLUDED.scanned_count,
      updated_at=now(),completed_at=EXCLUDED.completed_at;
    RETURN v_inserted;
END $$;

CREATE OR REPLACE FUNCTION public.maintain_requeue_tombstones(p_reconcile boolean)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path=public,pg_temp AS $$
    WITH tombstones AS (
        SELECT event||jsonb_build_object('event_id',gen_random_uuid()) AS envelope
          FROM public.maintain_sync_record_head WHERE p_reconcile AND event->>'event_type' LIKE '%.deleted'
    )
    INSERT INTO public.integration_outbox(event_id,event_type,source_system,event_version,occurred_at,source_id,record_type,payload)
    SELECT (envelope->>'event_id')::uuid,envelope->>'event_type','MaintainTrack','1.1',
        (envelope->>'occurred_at')::timestamptz,envelope->>'source_id',envelope->>'record_type',envelope FROM tombstones;
$$;
REVOKE ALL ON FUNCTION public.maintain_requeue_tombstones(boolean) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_requeue_tombstones(boolean) TO service_role;
NOTIFY pgrst,'reload schema';
COMMIT;
