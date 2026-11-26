-- Integration test for a disposable MaintainTrack database with an active requester and admin.
-- All fixtures and writes roll back. No Central or LifeCycleTrack connections are needed.
BEGIN;
DO $test$
DECLARE
    requester uuid;
    admin_id uuid;
    nonce uuid := gen_random_uuid();
    chosen jsonb;
    retry jsonb;
    manual jsonb;
    captured public.maintenance_requests;
    report jsonb;
BEGIN
    SELECT user_id INTO STRICT requester FROM public.users WHERE role::text='regular' AND lower(status::text)='active' LIMIT 1;
    SELECT user_id INTO STRICT admin_id FROM public.users WHERE role::text='admin' AND lower(status::text)='active' LIMIT 1;

    INSERT INTO public.external_asset_snapshots(external_system,entity_type,external_asset_id,event_id,payload,source_updated_at)
    VALUES ('LifeCycleTrack','facility','991111',gen_random_uuid(),'{"name":"Staging floor","location":"4th Floor"}',now()),
           ('LifeCycleTrack','equipment','991112',gen_random_uuid(),'{"name":"Staging unit","facility_id":"991111"}',now());

    report := jsonb_build_object('concern','Test outage','description','Staging only','category','Electrical',
        'equipment_category','Electrical','reported_floor','4th','date_reported',now(),
        'external_facility_id','991111','external_equipment_id','991112');
    chosen := public.maintain_submit_report(requester,nonce,report);
    retry := public.maintain_submit_report(requester,nonce,report);
    IF (retry->>'request_id') IS DISTINCT FROM (chosen->>'request_id') OR NOT (retry->>'duplicate')::boolean THEN
        RAISE EXCEPTION 'Retry must return the same request';
    END IF;
    SELECT * INTO STRICT captured FROM public.maintenance_requests WHERE request_id=(chosen->>'request_id')::uuid;
    IF captured.equipment_id IS NOT NULL OR captured.asset_review_required OR captured.external_equipment_id <> '991112'
       OR (captured.reported_asset_snapshot->>'equipment_name') IS DISTINCT FROM 'Staging unit' THEN
        RAISE EXCEPTION 'Saved asset references were incorrect';
    END IF;
    IF (SELECT count(*) FROM public.integration_outbox WHERE source_id=captured.request_id::text
            AND event_type='maintenance.work_order.upserted') <> 1 THEN
        RAISE EXCEPTION 'Expected exactly one durable outbox event for the report';
    END IF;

    manual := public.maintain_submit_report(requester,gen_random_uuid(),(report-'external_facility_id'-'external_equipment_id')
         || '{"reported_location":"Unlisted lab","reported_equipment":"Unit X"}');
    IF NOT EXISTS (SELECT 1 FROM public.maintenance_requests WHERE request_id=(manual->>'request_id')::uuid
              AND asset_review_required AND external_facility_id IS NULL AND external_equipment_id IS NULL
              AND reported_location='Unlisted lab' AND reported_equipment='Unit X') THEN
        RAISE EXCEPTION 'Manual report must preserve descriptions without asset IDs';
    END IF;
    IF NOT public.maintain_confirm_report_asset((manual->>'request_id')::uuid,admin_id,'991111','991112') THEN
        RAISE EXCEPTION 'Admin confirmation failed';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM public.maintenance_requests WHERE request_id=(manual->>'request_id')::uuid
              AND NOT asset_review_required AND external_equipment_id='991112' AND reported_location='Unlisted lab') THEN
        RAISE EXCEPTION 'Admin confirmation must retain the original report';
    END IF;

    UPDATE public.maintenance_requests SET approval_status='Approved' WHERE request_id=captured.request_id;
    IF NOT public.maintain_confirm_report_assessment(captured.request_id,admin_id,'equipment','991112',
        captured.asset_link_generation,gen_random_uuid()) THEN RAISE EXCEPTION 'Receipt was not persisted'; END IF;

    UPDATE public.external_asset_snapshots SET payload=payload||'{"facility_id":"991113"}'
     WHERE external_system='LifeCycleTrack' AND entity_type='equipment' AND external_asset_id='991112';
    IF NOT EXISTS (SELECT 1 FROM public.maintenance_requests WHERE request_id=captured.request_id
        AND asset_review_required AND lifecycle_assessment_reference IS NULL
        AND reported_asset_snapshot->>'equipment_id'='991112') THEN
        RAISE EXCEPTION 'Catalog move must flag the saved request and invalidate the receipt';
    END IF;
    BEGIN
        PERFORM public.maintain_submit_report(requester,gen_random_uuid(),report);
        RAISE EXCEPTION 'Mismatched equipment was accepted';
    EXCEPTION WHEN invalid_parameter_value THEN NULL;
    END;
END;
$test$;
ROLLBACK;
