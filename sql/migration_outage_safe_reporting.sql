-- Apply after the asset inbox, external request columns and outbox capture.
BEGIN;

ALTER TABLE public.maintenance_requests
    ADD COLUMN IF NOT EXISTS submission_id uuid,
    ADD COLUMN IF NOT EXISTS reported_floor text,
    ADD COLUMN IF NOT EXISTS linked_floor text,
    ADD COLUMN IF NOT EXISTS reported_location text,
    ADD COLUMN IF NOT EXISTS reported_equipment text,
    ADD COLUMN IF NOT EXISTS reported_asset_snapshot jsonb,
    ADD COLUMN IF NOT EXISTS asset_review_required boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS lifecycle_assessment_reference uuid;

CREATE UNIQUE INDEX IF NOT EXISTS maintenance_requests_submission_uidx
    ON public.maintenance_requests (reported_by, submission_id) WHERE submission_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS maintenance_requests_external_facility_idx
    ON public.maintenance_requests (external_facility_id) WHERE external_facility_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS maintenance_requests_external_equipment_idx
    ON public.maintenance_requests (external_equipment_id) WHERE external_equipment_id IS NOT NULL;

CREATE OR REPLACE FUNCTION public.maintain_report_floor(p_label text)
RETURNS text LANGUAGE sql IMMUTABLE SET search_path = pg_catalog AS $$
    SELECT CASE
        WHEN label ~ '\m(ground|gf)\M' THEN 'ground'
        WHEN label ~ '\m(2nd|second|2)([[:space:]]*(floor|f))?\M' THEN '2nd'
        WHEN label ~ '\m(3rd|third|3)([[:space:]]*(floor|f))?\M' THEN '3rd'
        WHEN label ~ '\m(4th|fourth|4)([[:space:]]*(floor|f))?\M' THEN '4th'
        ELSE '' END
    FROM (SELECT regexp_replace(lower(coalesce(p_label,'')), '[^a-z0-9]+', ' ', 'g') AS label) AS normalized;
$$;

CREATE OR REPLACE FUNCTION public.maintain_snapshot_available(p_payload jsonb, p_deleted_at timestamptz)
RETURNS boolean LANGUAGE sql IMMUTABLE SET search_path = pg_catalog AS $$
    SELECT p_payload IS NOT NULL
       AND p_deleted_at IS NULL
       AND nullif(btrim(p_payload->>'name'),'') IS NOT NULL
       AND lower(coalesce(p_payload->>'lifecycle_status','')) <> 'disposed'
       AND nullif(p_payload->>'disposed_at','') IS NULL;
$$;

CREATE OR REPLACE FUNCTION public.maintain_submit_report(p_reporter uuid, p_submission uuid, p_report jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
DECLARE
    input public.maintenance_requests;
    saved public.maintenance_requests;
    facility_snapshot public.external_asset_snapshots;
    equipment_snapshot public.external_asset_snapshots;
    floor_label text;
    area_label text;
    next_number bigint;
    next_display text;
BEGIN
    IF p_submission IS NULL OR NOT EXISTS (
        SELECT 1 FROM public.users WHERE user_id=p_reporter AND role::text='regular' AND lower(status::text)='active'
    ) THEN RAISE EXCEPTION 'An active requester account is required.' USING ERRCODE='42501'; END IF;

    -- Serialize number allocation and duplicate submissions from the web form.
    PERFORM pg_advisory_xact_lock(81930277);
    SELECT * INTO saved FROM public.maintenance_requests
     WHERE reported_by=p_reporter AND submission_id=p_submission;
    IF FOUND THEN
        RETURN jsonb_build_object('request_id',saved.request_id,'display_id',saved.display_id,
                                  'image_path',saved.image_path,'duplicate',true);
    END IF;

    input := jsonb_populate_record(NULL::public.maintenance_requests, p_report);
    IF input.reported_floor IS NULL OR input.reported_floor NOT IN ('ground','2nd','3rd','4th')
       OR nullif(btrim(input.concern),'') IS NULL OR nullif(btrim(input.description),'') IS NULL
       OR input.equipment_category IS NULL OR input.equipment_category NOT IN ('Electrical','Non-Electrical')
       OR input.date_reported IS NULL THEN
        RAISE EXCEPTION 'Complete the required report fields.' USING ERRCODE='22023';
    END IF;
    IF input.equipment_category <> 'Electrical'
       AND (input.equipment_id IS NOT NULL OR input.external_equipment_id IS NOT NULL
            OR nullif(btrim(input.reported_equipment),'') IS NOT NULL) THEN
        RAISE EXCEPTION 'Equipment is only available for Electrical concerns.' USING ERRCODE='22023';
    END IF;
    IF input.external_equipment_id IS NOT NULL AND input.external_facility_id IS NULL THEN
        RAISE EXCEPTION 'Select the facility for the external equipment.' USING ERRCODE='22023';
    END IF;

    IF input.external_facility_id IS NOT NULL THEN
        IF input.facility_id IS NOT NULL OR input.floor_id IS NOT NULL
            OR input.room_id IS NOT NULL OR input.equipment_id IS NOT NULL THEN
            RAISE EXCEPTION 'Select either a saved LifeCycleTrack location or a MaintainTrack location.' USING ERRCODE='22023';
        END IF;
        SELECT * INTO facility_snapshot FROM public.external_asset_snapshots
         WHERE external_system='LifeCycleTrack' AND entity_type='facility'
           AND external_asset_id=input.external_facility_id FOR SHARE;
        IF NOT maintain_snapshot_available(facility_snapshot.payload, facility_snapshot.deleted_at)
           OR maintain_report_floor(facility_snapshot.payload->>'location') <> input.reported_floor THEN
            RAISE EXCEPTION 'The saved facility changed. Select another location or choose Asset or location not listed.' USING ERRCODE='22023';
        END IF;
        input.external_facility_name := facility_snapshot.payload->>'name';
        area_label := input.external_facility_name;
        IF input.external_equipment_id IS NOT NULL THEN
            SELECT * INTO equipment_snapshot FROM public.external_asset_snapshots
             WHERE external_system='LifeCycleTrack' AND entity_type='equipment'
               AND external_asset_id=input.external_equipment_id FOR SHARE;
            IF NOT maintain_snapshot_available(equipment_snapshot.payload, equipment_snapshot.deleted_at)
               OR (equipment_snapshot.payload->>'facility_id') IS DISTINCT FROM input.external_facility_id THEN
                RAISE EXCEPTION 'The saved equipment changed. Select it again or describe it in the report.' USING ERRCODE='22023';
            END IF;
            input.external_equipment_name := equipment_snapshot.payload->>'name';
        END IF;
        input.reported_asset_snapshot := jsonb_strip_nulls(jsonb_build_object(
            'facility_id',input.external_facility_id,'facility_name',input.external_facility_name,
            'facility_synced_at',facility_snapshot.received_at,'equipment_id',input.external_equipment_id,
            'equipment_name',input.external_equipment_name,'equipment_synced_at',equipment_snapshot.received_at));
    ELSIF input.facility_id IS NOT NULL THEN
        SELECT name || ' ' || coalesce(code,''), name INTO floor_label, area_label
          FROM public.facility_floors WHERE floor_id=input.floor_id AND facility_id=input.facility_id FOR SHARE;
        IF NOT FOUND OR maintain_report_floor(floor_label) <> input.reported_floor THEN
            RAISE EXCEPTION 'Select a floor at the matching facility.' USING ERRCODE='22023';
        END IF;
        IF input.room_id IS NOT NULL THEN
            SELECT name INTO area_label FROM public.facility_rooms
             WHERE room_id=input.room_id AND floor_id=input.floor_id FOR SHARE;
            IF NOT FOUND THEN RAISE EXCEPTION 'Select a room on the matching floor.' USING ERRCODE='22023'; END IF;
        END IF;
        IF input.equipment_id IS NOT NULL THEN
            PERFORM 1 FROM public.equipment WHERE equipment_id=input.equipment_id
               AND facility_id=input.facility_id AND floor_id=input.floor_id
               AND room_id IS NOT DISTINCT FROM input.room_id AND lower(status::text)='active' FOR SHARE;
            IF NOT FOUND THEN RAISE EXCEPTION 'Select active equipment in the matching location.' USING ERRCODE='22023'; END IF;
        END IF;
    ELSE
        IF nullif(btrim(input.reported_location),'') IS NULL OR length(input.reported_location) > 200
           OR length(coalesce(input.reported_equipment,'')) > 120
           OR input.floor_id IS NOT NULL OR input.room_id IS NOT NULL OR input.equipment_id IS NOT NULL THEN
            RAISE EXCEPTION 'Describe the location when it is not listed.' USING ERRCODE='22023';
        END IF;
        -- Keep unverified descriptions in MaintainTrack. The existing outbox publishes specific_area.
        area_label := 'Unverified location';
    END IF;

    SELECT coalesce(max(substring(display_id FROM 4)::bigint), 0) + 1 INTO next_number
      FROM public.maintenance_requests WHERE display_id ~ '^MT-[0-9]{1,15}$';
    next_display := 'MT-' || lpad(next_number::text, greatest(5,length(next_number::text)), '0');
    INSERT INTO public.maintenance_requests (
        display_id, concern, description, category, equipment_category, priority, facility_id, floor_id,
        room_id, equipment_id, specific_area, external_facility_id, external_facility_name,
        external_equipment_id, external_equipment_name, reported_by, image_path, date_reported, submission_id,
        reported_floor, linked_floor, reported_location, reported_equipment, reported_asset_snapshot, asset_review_required
    ) VALUES (
        next_display,input.concern,input.description,input.category,input.equipment_category,
        coalesce(input.priority,'Medium'),input.facility_id,input.floor_id,input.room_id,input.equipment_id,
        left(area_label,100),input.external_facility_id,input.external_facility_name,
        input.external_equipment_id,input.external_equipment_name,p_reporter,input.image_path,input.date_reported,
        p_submission,input.reported_floor,CASE WHEN input.external_facility_id IS NOT NULL THEN input.reported_floor END,
        CASE WHEN input.facility_id IS NULL AND input.external_facility_id IS NULL THEN input.reported_location END,
        CASE WHEN input.facility_id IS NULL AND input.external_facility_id IS NULL THEN input.reported_equipment END,
        input.reported_asset_snapshot,input.facility_id IS NULL AND input.external_facility_id IS NULL
    ) RETURNING * INTO saved;

    INSERT INTO public.request_timeline (request_id,status,actor_user_id,note)
    VALUES (saved.request_id,'Pending',p_reporter,'Maintenance request submitted');
    INSERT INTO public.activity_log (action_text,related_request_id,actor_user_id)
    VALUES (saved.display_id || ' submitted',saved.request_id,p_reporter);

    RETURN jsonb_build_object('request_id',saved.request_id,'display_id',saved.display_id,
                              'image_path',saved.image_path,'duplicate',false);
END;
$$;

CREATE OR REPLACE FUNCTION public.maintain_review_catalog_change()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
BEGIN
    IF NEW.external_system <> 'LifeCycleTrack' THEN RETURN NEW; END IF;
    IF NEW.entity_type = 'facility' THEN
        UPDATE public.maintenance_requests r SET asset_review_required=true, lifecycle_assessment_reference=NULL
         WHERE r.external_facility_id=NEW.external_asset_id AND NOT r.asset_review_required
           AND (NOT maintain_snapshot_available(NEW.payload,NEW.deleted_at)
                OR (coalesce(r.linked_floor,r.reported_floor) IS NOT NULL
                    AND coalesce(r.linked_floor,r.reported_floor) <> maintain_report_floor(NEW.payload->>'location')));
    ELSE
        UPDATE public.maintenance_requests r SET asset_review_required=true, lifecycle_assessment_reference=NULL
         WHERE r.external_equipment_id=NEW.external_asset_id AND NOT r.asset_review_required
           AND (NOT maintain_snapshot_available(NEW.payload,NEW.deleted_at)
                OR r.external_facility_id IS DISTINCT FROM (NEW.payload->>'facility_id'));
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS maintain_review_catalog_change ON public.external_asset_snapshots;
CREATE TRIGGER maintain_review_catalog_change AFTER INSERT OR UPDATE ON public.external_asset_snapshots
    FOR EACH ROW EXECUTE FUNCTION public.maintain_review_catalog_change();

REVOKE ALL ON FUNCTION public.maintain_submit_report(uuid,uuid,jsonb) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_submit_report(uuid,uuid,jsonb) TO service_role;

ALTER TABLE public.maintenance_requests
    ADD COLUMN IF NOT EXISTS asset_link_generation uuid NOT NULL DEFAULT gen_random_uuid();

CREATE OR REPLACE FUNCTION public.maintain_load_report_link(p_facility text, p_equipment text)
RETURNS jsonb LANGUAGE plpgsql SET search_path = public, pg_temp AS $$
DECLARE
    facility_snapshot public.external_asset_snapshots;
    equipment_snapshot public.external_asset_snapshots;
BEGIN
    SELECT * INTO facility_snapshot FROM public.external_asset_snapshots
     WHERE external_system='LifeCycleTrack' AND entity_type='facility' AND external_asset_id=p_facility FOR SHARE;
    IF NOT maintain_snapshot_available(facility_snapshot.payload, facility_snapshot.deleted_at) THEN
        RAISE EXCEPTION 'The selected facility is not available in the saved catalog.' USING ERRCODE='22023';
    END IF;
    IF p_equipment IS NOT NULL THEN
        SELECT * INTO equipment_snapshot FROM public.external_asset_snapshots
         WHERE external_system='LifeCycleTrack' AND entity_type='equipment' AND external_asset_id=p_equipment FOR SHARE;
        IF NOT maintain_snapshot_available(equipment_snapshot.payload, equipment_snapshot.deleted_at)
           OR (equipment_snapshot.payload->>'facility_id') IS DISTINCT FROM p_facility THEN
            RAISE EXCEPTION 'The selected equipment is not available at this facility.' USING ERRCODE='22023';
        END IF;
    END IF;
    RETURN jsonb_build_object('facility_name',facility_snapshot.payload->>'name',
        'equipment_name',equipment_snapshot.payload->>'name','floor',maintain_report_floor(facility_snapshot.payload->>'location'));
END;
$$;

CREATE OR REPLACE FUNCTION public.maintain_confirm_report_asset(p_request uuid, p_admin uuid, p_facility text, p_equipment text)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
DECLARE
    report public.maintenance_requests;
    asset jsonb;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM public.users WHERE user_id=p_admin AND role::text='admin' AND lower(status::text)='active') THEN
        RAISE EXCEPTION 'An active administrator is required.' USING ERRCODE='42501';
    END IF;
    SELECT * INTO report FROM public.maintenance_requests WHERE request_id=p_request FOR UPDATE;
    IF NOT FOUND OR NOT report.asset_review_required OR report.status::text IN ('Completed','Rejected') THEN
        RAISE EXCEPTION 'This request is no longer available for asset review.' USING ERRCODE='22023';
    END IF;
    IF p_equipment IS NOT NULL AND report.equipment_category IS DISTINCT FROM 'Electrical' THEN
        RAISE EXCEPTION 'Select only a facility for a non-Electrical request.' USING ERRCODE='22023';
    END IF;
    asset := maintain_load_report_link(p_facility,p_equipment);
    UPDATE public.maintenance_requests SET external_facility_id=p_facility, external_equipment_id=p_equipment,
           external_facility_name=asset->>'facility_name', external_equipment_name=asset->>'equipment_name',
           facility_id=NULL, floor_id=NULL, room_id=NULL, equipment_id=NULL,
           asset_review_required=false, asset_link_generation=gen_random_uuid(), lifecycle_assessment_reference=NULL,
           linked_floor=asset->>'floor',
           specific_area=left(asset->>'facility_name',100)
     WHERE request_id=p_request;
    INSERT INTO public.activity_log(action_text,related_request_id,actor_user_id)
    VALUES (report.display_id || ' LifeCycleTrack asset confirmed',p_request,p_admin);
    RETURN true;
END;
$$;

CREATE OR REPLACE FUNCTION public.maintain_confirm_report_assessment(
    p_request uuid,p_admin uuid,p_entity text,p_asset text,p_link_generation uuid,p_assessment uuid
)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
DECLARE report public.maintenance_requests;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM public.users WHERE user_id=p_admin AND role::text='admin' AND lower(status::text)='active') THEN
        RAISE EXCEPTION 'An active administrator is required.' USING ERRCODE='42501';
    END IF;
    SELECT * INTO report FROM public.maintenance_requests WHERE request_id=p_request FOR UPDATE;
    IF NOT FOUND OR report.asset_review_required OR report.approval_status <> 'Approved' OR p_assessment IS NULL
       OR report.asset_link_generation IS DISTINCT FROM p_link_generation
       OR p_asset IS DISTINCT FROM coalesce(report.external_equipment_id,report.external_facility_id)
       OR p_entity IS DISTINCT FROM (CASE WHEN report.external_equipment_id IS NULL THEN 'facility' ELSE 'equipment' END) THEN
        RAISE EXCEPTION 'The assessment does not match the approved request and asset.' USING ERRCODE='22023';
    END IF;
    PERFORM maintain_load_report_link(report.external_facility_id,report.external_equipment_id);
    IF report.lifecycle_assessment_reference IS DISTINCT FROM p_assessment THEN
        UPDATE public.maintenance_requests SET lifecycle_assessment_reference=p_assessment WHERE request_id=p_request;
    END IF;
    RETURN true;
END;
$$;

CREATE OR REPLACE FUNCTION public.maintain_require_assignment_assessment()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
DECLARE report public.maintenance_requests;
BEGIN
    SELECT * INTO report FROM public.maintenance_requests WHERE request_id=NEW.request_id FOR UPDATE;
    IF report.asset_review_required THEN
        RAISE EXCEPTION 'Confirm the reported asset before assigning this request.' USING ERRCODE='22023';
    END IF;
    IF report.external_facility_id IS NOT NULL THEN
        IF report.lifecycle_assessment_reference IS NULL THEN
            RAISE EXCEPTION 'Complete the LifeCycleTrack assessment before assigning.' USING ERRCODE='22023';
        END IF;
        PERFORM maintain_load_report_link(report.external_facility_id,report.external_equipment_id);
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS maintain_require_assignment_assessment ON public.assignments;
CREATE TRIGGER maintain_require_assignment_assessment BEFORE INSERT ON public.assignments
    FOR EACH ROW EXECUTE FUNCTION public.maintain_require_assignment_assessment();

REVOKE ALL ON FUNCTION public.maintain_load_report_link(text,text),
    public.maintain_confirm_report_asset(uuid,uuid,text,text),
    public.maintain_confirm_report_assessment(uuid,uuid,text,text,uuid,uuid) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_confirm_report_asset(uuid,uuid,text,text),
    public.maintain_confirm_report_assessment(uuid,uuid,text,text,uuid,uuid) TO service_role;

NOTIFY pgrst,'reload schema';
COMMIT;
