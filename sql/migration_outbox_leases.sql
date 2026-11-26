-- Lease outbound attempts so scheduled and manual publishers can overlap safely.
BEGIN;

ALTER TABLE public.integration_outbox
    ADD COLUMN IF NOT EXISTS locked_by uuid,
    ADD COLUMN IF NOT EXISTS lease_until timestamptz,
    ADD COLUMN IF NOT EXISTS dead_lettered_at timestamptz;

CREATE OR REPLACE FUNCTION public.maintain_claim_outbox(p_worker uuid, p_limit integer)
RETURNS SETOF public.integration_outbox
LANGUAGE sql SECURITY DEFINER SET search_path = public, pg_temp AS $$
    WITH due AS (
        SELECT event_id FROM public.integration_outbox
         WHERE delivered_at IS NULL AND dead_lettered_at IS NULL
           AND (next_attempt_at IS NULL OR next_attempt_at <= now())
           AND (lease_until IS NULL OR lease_until <= now())
         ORDER BY created_at,event_id
         FOR UPDATE SKIP LOCKED LIMIT greatest(1,least(50,p_limit))
    )
    UPDATE public.integration_outbox o SET locked_by=p_worker, lease_until=now()+interval '3 minutes'
      FROM due WHERE p_worker IS NOT NULL AND o.event_id=due.event_id
    RETURNING o.*;
$$;

CREATE OR REPLACE FUNCTION public.maintain_finish_outbox(p_worker uuid, p_event uuid, p_error text)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
BEGIN
    UPDATE public.integration_outbox SET locked_by=NULL, lease_until=NULL, attempts=attempts+1,
           delivered_at=CASE WHEN p_error IS NULL THEN now() END,
           dead_lettered_at=CASE WHEN p_error IS NOT NULL AND attempts+1>=12 THEN now() END,
           last_error=left(p_error,400),
           next_attempt_at=CASE WHEN p_error IS NOT NULL AND attempts+1<12
               THEN now()+make_interval(secs=>least(3600,15*(1<<least(attempts,8)))) END
     WHERE event_id=p_event AND locked_by=p_worker AND delivered_at IS NULL AND dead_lettered_at IS NULL;
    RETURN FOUND;
END;
$$;

REVOKE ALL ON FUNCTION public.maintain_claim_outbox(uuid,integer), public.maintain_finish_outbox(uuid,uuid,text)
    FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_claim_outbox(uuid,integer), public.maintain_finish_outbox(uuid,uuid,text)
    TO service_role;
NOTIFY pgrst,'reload schema';
COMMIT;
