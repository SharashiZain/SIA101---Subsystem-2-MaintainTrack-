-- Persist worker poll time across restarts for the operator status endpoint.
BEGIN;
CREATE TABLE IF NOT EXISTS public.integration_inbox_health (
    singleton boolean PRIMARY KEY DEFAULT true CHECK(singleton),
    last_successful_poll timestamptz NOT NULL
);
ALTER TABLE public.integration_inbox_health ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.integration_inbox_health FROM PUBLIC,anon,authenticated;
GRANT SELECT ON public.integration_inbox_health TO service_role;

CREATE OR REPLACE FUNCTION public.integration_note_inbox_poll(p_success boolean)
RETURNS boolean LANGUAGE sql SECURITY DEFINER SET search_path=public,pg_temp AS $$
    WITH saved AS (
        INSERT INTO public.integration_inbox_health(singleton,last_successful_poll)
        SELECT true,clock_timestamp() WHERE p_success
        ON CONFLICT(singleton) DO UPDATE SET last_successful_poll=EXCLUDED.last_successful_poll
        RETURNING true
    ) SELECT EXISTS(SELECT 1 FROM saved);
$$;
REVOKE ALL ON FUNCTION public.integration_note_inbox_poll(boolean) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.integration_note_inbox_poll(boolean) TO service_role;
CREATE OR REPLACE FUNCTION public.maintain_integration_status(p_status boolean)
RETURNS jsonb LANGUAGE sql SECURITY DEFINER SET search_path=public,pg_temp AS $$
    SELECT jsonb_build_object(
        'last_successful_inbox_poll',(SELECT last_successful_poll FROM public.integration_inbox_health WHERE singleton),
        'oldest_pending_at',min(created_at) FILTER(WHERE delivered_at IS NULL AND dead_lettered_at IS NULL),
        'pending',count(*) FILTER(WHERE delivered_at IS NULL AND dead_lettered_at IS NULL),
        'dead_letters',count(*) FILTER(WHERE dead_lettered_at IS NOT NULL),
        'checkpoints',(SELECT coalesce(jsonb_agg(to_jsonb(cp)),'[]') FROM public.maintain_central_backfill_checkpoint cp))
    FROM public.integration_outbox WHERE p_status;
$$;
REVOKE ALL ON FUNCTION public.maintain_integration_status(boolean) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_integration_status(boolean) TO service_role;
NOTIFY pgrst,'reload schema';
COMMIT;
