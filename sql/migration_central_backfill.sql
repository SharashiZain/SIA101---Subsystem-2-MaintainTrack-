-- Resumable snapshots for existing MaintainTrack requests and outcomes.
CREATE TABLE IF NOT EXISTS public.maintain_central_backfill_checkpoint (
    job_name TEXT PRIMARY KEY CHECK (job_name IN ('maintain_requests','maintain_updates')),
    last_source_id TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','running','completed','failed')),
    scanned_count BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    completed_at TIMESTAMPTZ
);
ALTER TABLE public.maintain_central_backfill_checkpoint ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.maintain_central_backfill_checkpoint FROM anon,authenticated;
GRANT ALL ON public.maintain_central_backfill_checkpoint TO service_role;

CREATE OR REPLACE FUNCTION public.maintain_enqueue_central_backfill_batch(
    p_job_name TEXT,p_events JSONB,p_last_source_id TEXT,p_completed BOOLEAN DEFAULT FALSE
) RETURNS INTEGER LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE v_item JSONB; v_event JSONB; v_scanned INTEGER; v_inserted INTEGER:=0;
BEGIN
    IF p_job_name NOT IN ('maintain_requests','maintain_updates')
       OR jsonb_typeof(COALESCE(p_events,'[]'::jsonb))<>'array' THEN
        RAISE EXCEPTION 'Invalid MaintainTrack backfill batch' USING ERRCODE='22023';
    END IF;
    v_scanned:=jsonb_array_length(COALESCE(p_events,'[]'::jsonb));
    FOR v_item IN SELECT value FROM jsonb_array_elements(COALESCE(p_events,'[]'::jsonb)) LOOP
        v_event:=v_item->'event';
        IF jsonb_typeof(v_event)<>'object' OR v_event->>'source'<>'MaintainTrack'
           OR v_event->>'source_id' IS DISTINCT FROM v_item->>'source_id'
           OR v_event->'payload'->>'id' IS DISTINCT FROM v_item->>'source_id'
           OR NULLIF(v_event->>'event_id','') IS NULL THEN
            RAISE EXCEPTION 'Invalid MaintainTrack backfill event envelope' USING ERRCODE='22023';
        END IF;
    END LOOP;
    INSERT INTO public.integration_outbox(event_id,event_type,source_system,event_version,correlation_id,occurred_at,source_id,record_type,payload)
    SELECT (item->'event'->>'event_id')::UUID,item->'event'->>'event_type','MaintainTrack',item->'event'->>'version',
           NULLIF(item->'event'->>'correlation_id','')::UUID,(item->'event'->>'occurred_at')::TIMESTAMPTZ,
           item->>'source_id',item->'event'->>'record_type',item->'event'
    FROM jsonb_array_elements(COALESCE(p_events,'[]'::jsonb)) AS item
    ON CONFLICT(event_id) DO NOTHING;
    GET DIAGNOSTICS v_inserted=ROW_COUNT;
    INSERT INTO public.maintain_central_backfill_checkpoint(job_name,last_source_id,status,scanned_count,updated_at,completed_at)
    VALUES(p_job_name,p_last_source_id,CASE WHEN p_completed THEN 'completed' ELSE 'running' END,v_scanned,now(),CASE WHEN p_completed THEN now() ELSE NULL END)
    ON CONFLICT(job_name) DO UPDATE SET last_source_id=COALESCE(EXCLUDED.last_source_id,maintain_central_backfill_checkpoint.last_source_id),
      status=EXCLUDED.status,scanned_count=maintain_central_backfill_checkpoint.scanned_count+EXCLUDED.scanned_count,
      updated_at=now(),completed_at=EXCLUDED.completed_at;
    RETURN v_inserted;
END $$;

CREATE OR REPLACE FUNCTION public.maintain_reset_central_backfill(p_job_name TEXT)
RETURNS BOOLEAN LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
BEGIN
    IF p_job_name NOT IN ('maintain_requests','maintain_updates') THEN RAISE EXCEPTION 'Invalid MaintainTrack backfill job' USING ERRCODE='22023'; END IF;
    INSERT INTO public.maintain_central_backfill_checkpoint(job_name,last_source_id,status,scanned_count,updated_at,completed_at)
    VALUES(p_job_name,NULL,'pending',0,now(),NULL)
    ON CONFLICT(job_name) DO UPDATE SET last_source_id=NULL,status='pending',scanned_count=0,updated_at=now(),completed_at=NULL;
    RETURN TRUE;
END $$;

REVOKE ALL ON FUNCTION public.maintain_enqueue_central_backfill_batch(TEXT,JSONB,TEXT,BOOLEAN) FROM PUBLIC,anon,authenticated;
REVOKE ALL ON FUNCTION public.maintain_reset_central_backfill(TEXT) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_enqueue_central_backfill_batch(TEXT,JSONB,TEXT,BOOLEAN) TO service_role;
GRANT EXECUTE ON FUNCTION public.maintain_reset_central_backfill(TEXT) TO service_role;
NOTIFY pgrst,'reload schema';
