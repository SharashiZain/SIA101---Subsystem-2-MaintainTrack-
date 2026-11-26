-- Atomic technician updates and schedule metadata. Apply after outage-safe reporting.
BEGIN;
ALTER TABLE public.maintenance_requests
    ADD COLUMN IF NOT EXISTS scheduled_start timestamptz,
    ADD COLUMN IF NOT EXISTS scheduled_end timestamptz;

ALTER TABLE public.maintenance_updates
    ADD COLUMN IF NOT EXISTS operation_id uuid,
    ADD COLUMN IF NOT EXISTS observed_condition text,
    ADD COLUMN IF NOT EXISTS observed_asset_version timestamptz,
    ADD COLUMN IF NOT EXISTS observed_facility_id text,
    ADD COLUMN IF NOT EXISTS observed_equipment_id text;

CREATE UNIQUE INDEX IF NOT EXISTS maintenance_updates_operation_idx
    ON public.maintenance_updates (author_user_id,operation_id) WHERE operation_id IS NOT NULL;

CREATE OR REPLACE FUNCTION public.maintain_save_worker_update(
    p_request uuid,p_worker uuid,p_operation uuid,p_status text,p_title text,p_note text,p_photo text,
    p_observation text,p_asset_version timestamptz
)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE
    report public.maintenance_requests;
    change public.maintenance_updates;
    snapshot public.external_asset_snapshots;
    saved_at timestamptz := clock_timestamp();
BEGIN
    IF p_operation IS NULL OR p_status IS NULL OR p_status NOT IN ('In Progress','Completed','Fail to Fix')
       OR nullif(btrim(p_title),'') IS NULL OR length(p_title)>200 OR nullif(btrim(p_note),'') IS NULL
       OR (p_observation IS NOT NULL AND p_observation NOT IN ('Good','Fair','Poor')) THEN
        RAISE EXCEPTION 'Select a status and enter a valid update.' USING ERRCODE='22023';
    END IF;
    SELECT * INTO report FROM public.maintenance_requests WHERE request_id=p_request FOR UPDATE;
    IF NOT FOUND OR NOT EXISTS (
        SELECT 1 FROM public.users WHERE user_id=p_worker AND role::text='maintenance' AND lower(status::text)='active'
    ) OR (report.assigned_to IS DISTINCT FROM p_worker AND NOT EXISTS (
        SELECT 1 FROM public.assignments WHERE request_id=p_request AND assigned_to=p_worker
    )) THEN
        RAISE EXCEPTION 'The request is not assigned to this worker.' USING ERRCODE='42501';
    END IF;
    SELECT * INTO change FROM public.maintenance_updates WHERE author_user_id=p_worker AND operation_id=p_operation;
    IF FOUND THEN
        IF change.request_id<>p_request THEN RAISE EXCEPTION 'Invalid operation ID.' USING ERRCODE='22023'; END IF;
        RETURN jsonb_build_object('update_id',change.update_id,'photo_path',change.photo_path,'duplicate',true);
    END IF;
    IF report.status::text IN ('Completed','Rejected','Cancelled','Closed') THEN
        RAISE EXCEPTION 'The request is closed.' USING ERRCODE='22023';
    END IF;
    IF p_observation IS NOT NULL THEN
        SELECT * INTO snapshot FROM public.external_asset_snapshots
         WHERE external_system='LifeCycleTrack'
           AND entity_type=CASE WHEN report.external_equipment_id IS NULL THEN 'facility' ELSE 'equipment' END
           AND external_asset_id=coalesce(report.external_equipment_id,report.external_facility_id) FOR SHARE;
        IF report.asset_review_required OR p_asset_version IS NULL
           OR NOT maintain_snapshot_available(snapshot.payload,snapshot.deleted_at) THEN
            RAISE EXCEPTION 'An active linked asset is required to submit a condition observation.' USING ERRCODE='22023';
        END IF;
    END IF;

    UPDATE public.maintenance_requests
       SET status=p_status::public.request_status,
           date_started=CASE WHEN p_status='In Progress' THEN coalesce(date_started,saved_at) ELSE date_started END,
           date_completed=CASE WHEN p_status='Completed' THEN saved_at ELSE date_completed END
     WHERE request_id=p_request;
    INSERT INTO public.maintenance_updates(request_id,author_user_id,title,status,update_text,photo_path,
        operation_id,observed_condition,observed_asset_version,observed_facility_id,observed_equipment_id)
    VALUES(p_request,p_worker,p_title,p_status,p_note,p_photo,p_operation,p_observation,
        CASE WHEN p_observation IS NOT NULL THEN p_asset_version END,
        report.external_facility_id,report.external_equipment_id)
    RETURNING * INTO change;
    INSERT INTO public.request_timeline(request_id,status,actor_user_id,note)
    VALUES (p_request,p_status::public.request_status,p_worker,left(p_status||': '||p_title,255));
    INSERT INTO public.activity_log(action_text,related_request_id,actor_user_id)
    VALUES (left(report.display_id||' updated: '||p_status,255),p_request,p_worker);

    RETURN jsonb_build_object('update_id',change.update_id,'photo_path',change.photo_path,'duplicate',false);
END;
$$;

REVOKE ALL ON FUNCTION public.maintain_save_worker_update(uuid,uuid,uuid,text,text,text,text,text,timestamptz)
    FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_save_worker_update(uuid,uuid,uuid,text,text,text,text,text,timestamptz)
    TO service_role;

NOTIFY pgrst,'reload schema';
COMMIT;
