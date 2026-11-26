-- LifeCycleTrack evidence replicas and reviewable maintenance referrals.
BEGIN;
CREATE TABLE IF NOT EXISTS public.maintenance_asset_evidence (
    request_id uuid PRIMARY KEY,
    entity_type text NOT NULL,
    asset_id text NOT NULL,
    revision bigint NOT NULL,
    payload jsonb NOT NULL,
    received_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS public.maintenance_investigation_referrals (
    investigation_id uuid PRIMARY KEY,
    request_id uuid NOT NULL UNIQUE REFERENCES public.maintenance_requests(request_id),
    revision bigint NOT NULL,
    payload jsonb NOT NULL
);
ALTER TABLE public.maintenance_asset_evidence ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.maintenance_investigation_referrals ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.maintenance_asset_evidence,public.maintenance_investigation_referrals FROM PUBLIC,anon,authenticated;
GRANT SELECT ON public.maintenance_asset_evidence,public.maintenance_investigation_referrals TO service_role;

CREATE OR REPLACE FUNCTION public.maintain_apply_maintenance_evidence(p_event jsonb)
RETURNS text LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE
    evidence jsonb:=p_event->'payload';
    revision bigint:=(evidence->>'source_revision')::bigint;
    request_key uuid:=(evidence->>'request_id')::uuid;
BEGIN
    IF (p_event->>'source') IS DISTINCT FROM 'LifeCycleTrack' OR (p_event->>'destination') IS DISTINCT FROM 'MaintainTrack'
       OR (p_event->>'event_type') IS DISTINCT FROM 'lifecycle.maintenance_evidence.upserted'
       OR request_key IS NULL OR coalesce(revision,0)<1
       OR coalesce(evidence->>'entity_type','') NOT IN ('facility','equipment')
       OR coalesce(evidence->>'asset_id','') !~ '^[0-9]+$' THEN
        RAISE EXCEPTION 'Invalid maintenance evidence' USING ERRCODE='22023';
    END IF;
    INSERT INTO public.maintenance_asset_evidence AS e(request_id,entity_type,asset_id,revision,payload)
    VALUES(request_key,evidence->>'entity_type',evidence->>'asset_id',revision,evidence)
    ON CONFLICT(request_id) DO UPDATE SET entity_type=EXCLUDED.entity_type,asset_id=EXCLUDED.asset_id,
        revision=EXCLUDED.revision,payload=EXCLUDED.payload,received_at=now() WHERE e.revision<EXCLUDED.revision;
    RETURN CASE WHEN FOUND THEN 'applied' ELSE 'stale' END;
END;
$$;

CREATE OR REPLACE FUNCTION public.maintain_apply_maintenance_referral(p_event jsonb,p_reporter uuid)
RETURNS text LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE
    referral jsonb:=p_event->'payload';
    investigation uuid:=(referral->>'id')::uuid;
    version bigint:=(referral->>'source_revision')::bigint;
    previous public.maintenance_investigation_referrals;
    facility text := referral->>'external_facility_id';
    equipment text := referral->>'external_equipment_id';
    request_key uuid;
    asset jsonb;
    number bigint;
BEGIN
    IF (p_event->>'source') IS DISTINCT FROM 'LifeCycleTrack' OR (p_event->>'destination') IS DISTINCT FROM 'MaintainTrack'
       OR (p_event->>'event_type') IS DISTINCT FROM 'lifecycle.maintenance_referral.upserted'
       OR investigation IS NULL OR coalesce(version,0)<1 OR coalesce(facility,'') !~ '^[0-9]+$'
       OR (equipment IS NOT NULL AND equipment !~ '^[0-9]+$') THEN
        RAISE EXCEPTION 'Invalid maintenance referral' USING ERRCODE='22023';
    END IF;
    PERFORM pg_advisory_xact_lock(81930277);
    SELECT * INTO previous FROM public.maintenance_investigation_referrals WHERE investigation_id=investigation;
    IF FOUND THEN
        IF previous.revision>=version THEN RETURN 'stale'; END IF;
        UPDATE public.maintenance_investigation_referrals SET payload=referral,revision=version WHERE investigation_id=investigation;
        IF (previous.payload->>'external_facility_id') IS DISTINCT FROM facility
           OR (previous.payload->>'external_equipment_id') IS DISTINCT FROM equipment THEN
            UPDATE public.maintenance_requests SET asset_review_required=true,lifecycle_assessment_reference=NULL
             WHERE request_id=previous.request_id;
        END IF;
        RETURN 'applied';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM public.users WHERE user_id=p_reporter AND lower(status::text)='active' AND role::text='regular') THEN
        RAISE EXCEPTION 'Configure an active referral requester.' USING ERRCODE='42501';
    END IF;
    -- Delayed asset replicas leave the referral visible for review.
    BEGIN
        asset := maintain_load_report_link(facility,equipment);
    EXCEPTION WHEN invalid_parameter_value THEN asset := NULL;
    END;
    SELECT coalesce(max(substring(display_id FROM 4)::bigint),0)+1 INTO number
      FROM public.maintenance_requests WHERE display_id ~ '^MT-[0-9]{1,15}$';
    INSERT INTO public.maintenance_requests(display_id,concern,description,category,equipment_category,reported_by,
        external_facility_id,external_facility_name,external_equipment_id,external_equipment_name,asset_review_required,specific_area)
    VALUES ('MT-'||lpad(number::text,greatest(5,length(number::text)),'0'),'Utility anomaly referral',
        coalesce(nullif(left(referral->>'summary',1000),''),'Reviewed utility anomaly referral'),
        'Equipment','Electrical',p_reporter,
        CASE WHEN asset IS NOT NULL THEN facility END,asset->>'facility_name',
        CASE WHEN asset IS NOT NULL THEN equipment END,asset->>'equipment_name',asset IS NULL,
        left(coalesce(asset->>'facility_name','Unverified location'),100))
    RETURNING request_id INTO request_key;
    INSERT INTO public.maintenance_investigation_referrals(investigation_id,request_id,revision,payload)
    VALUES (investigation,request_key,version,referral);
    INSERT INTO public.request_timeline(request_id,status,actor_user_id,note)
    VALUES (request_key,'Pending',p_reporter,'Reviewed utility anomaly referred for maintenance');
    RETURN 'applied';
END;
$$;

CREATE OR REPLACE FUNCTION public.maintain_schedule_and_assign(
    p_request_id uuid,p_staff_ids uuid[],p_assigned_by uuid,p_notes text,p_start timestamptz,p_end timestamptz
)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE result jsonb;
BEGIN
    IF (p_start IS NULL) <> (p_end IS NULL) OR p_end<=p_start
       OR (p_start IS NULL AND EXISTS(SELECT 1 FROM public.maintenance_investigation_referrals WHERE request_id=p_request_id)) THEN
        RAISE EXCEPTION 'Select a valid start and end for the work schedule.' USING ERRCODE='22023';
    END IF;
    result:=public.assign_maintenance_request_with_notifications(p_request_id,p_staff_ids,p_assigned_by,p_notes);
    IF result->>'assigned'='true' AND p_start IS NOT NULL THEN
        UPDATE public.maintenance_requests SET scheduled_start=p_start,scheduled_end=p_end WHERE request_id=p_request_id;
    END IF;
    RETURN result;
END;
$$;

REVOKE ALL ON FUNCTION public.maintain_apply_maintenance_evidence(jsonb),
    public.maintain_apply_maintenance_referral(jsonb,uuid),
    public.maintain_schedule_and_assign(uuid,uuid[],uuid,text,timestamptz,timestamptz) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_apply_maintenance_evidence(jsonb),public.maintain_apply_maintenance_referral(jsonb,uuid),
    public.maintain_schedule_and_assign(uuid,uuid[],uuid,text,timestamptz,timestamptz) TO service_role;
NOTIFY pgrst,'reload schema';
COMMIT;
