-- Apply after worker updates and after Central and LifeCycleTrack can receive 1.1.
BEGIN;
CREATE TABLE IF NOT EXISTS public.maintain_sync_record_head (
    record_type text NOT NULL,
    source_id text NOT NULL,
    revision bigint NOT NULL,
    event jsonb,
    PRIMARY KEY(record_type,source_id)
);
ALTER TABLE public.maintain_sync_record_head ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON TABLE public.maintain_sync_record_head FROM PUBLIC,anon,authenticated;
GRANT SELECT ON TABLE public.maintain_sync_record_head TO service_role;

CREATE OR REPLACE FUNCTION public.maintain_capture_integration_event()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE
    v_event_id UUID:=gen_random_uuid();
    v_event_type TEXT;
    v_record_type TEXT;
    v_source_id TEXT;
    v_payload JSONB;
    v_row JSONB;
    v_revision bigint;
    v_event jsonb;
BEGIN
    IF TG_TABLE_NAME='maintenance_requests' THEN
        v_source_id:=COALESCE(NEW.request_id,OLD.request_id)::text;
        v_record_type:='maintenance_request';
        IF TG_OP='DELETE' THEN
            v_event_type:='maintenance.work_order.deleted';
            v_payload:=jsonb_build_object('id',v_source_id);
        ELSE
            v_row:=to_jsonb(NEW);
            IF TG_OP='UPDATE' AND (v_row-'updated_at')=(to_jsonb(OLD)-'updated_at') THEN RETURN NEW; END IF;
            v_event_type:='maintenance.work_order.upserted';
            v_payload:=jsonb_strip_nulls(jsonb_build_object(
                'id',v_source_id,'display_id',v_row->'display_id','facility_id',v_row->'facility_id',
                'external_facility_id',v_row->'external_facility_id','external_facility_name',v_row->'external_facility_name',
                'external_equipment_id',v_row->'external_equipment_id','external_equipment_name',v_row->'external_equipment_name',
                'equipment_id',v_row->'equipment_id','category',v_row->'category','equipment_category',v_row->'equipment_category',
                'specific_area',v_row->'specific_area','status',v_row->'status','approval_status',v_row->'approval_status',
                'priority',v_row->'priority','date_reported',v_row->'date_reported','date_assigned',v_row->'date_assigned',
                'date_started',v_row->'date_started','date_completed',v_row->'date_completed',
                'scheduled_start',v_row->'scheduled_start','scheduled_end',v_row->'scheduled_end',
                'updated_at',COALESCE(v_row->'updated_at',to_jsonb(now()))
            ));
        END IF;
    ELSE
        v_source_id:=COALESCE(NEW.update_id,OLD.update_id)::text;
        v_record_type:='maintenance_update';
        IF TG_OP='DELETE' THEN
            v_event_type:='maintenance.result.deleted';
            v_payload:=jsonb_build_object('id',v_source_id,'request_id',OLD.request_id);
        ELSE
            v_row:=to_jsonb(NEW);
            v_event_type:='maintenance.result.recorded';
            v_payload:=jsonb_strip_nulls(jsonb_build_object(
                'id',v_source_id,'request_id',v_row->'request_id','status',v_row->'status',
                'title',v_row->'title',
                'external_facility_id',v_row->'observed_facility_id','external_equipment_id',v_row->'observed_equipment_id',
                'observed_condition',v_row->'observed_condition','observed_asset_version',v_row->'observed_asset_version',
                'completion_outcome',CASE WHEN lower(COALESCE(v_row->>'status',''))='completed' THEN v_row->'update_text' ELSE NULL END,
                'created_at',v_row->'created_at'
            ));
        END IF;
    END IF;
    INSERT INTO public.maintain_sync_record_head AS h(record_type,source_id,revision)
    VALUES(v_record_type,v_source_id,1)
    ON CONFLICT(record_type,source_id) DO UPDATE SET revision=h.revision+1
    RETURNING revision INTO v_revision;
    v_payload := v_payload || jsonb_build_object('source_revision',v_revision::text);
    v_event := jsonb_build_object('event_id',v_event_id,'event_type',v_event_type,'source','MaintainTrack',
        'destination',NULL,'version','1.1','correlation_id',NULL,'occurred_at',now(),'source_id',v_source_id,
        'record_type',v_record_type,'payload',v_payload);
    UPDATE public.maintain_sync_record_head SET event=v_event
     WHERE record_type=v_record_type AND source_id=v_source_id;
    INSERT INTO public.integration_outbox(event_id,event_type,source_system,event_version,occurred_at,source_id,record_type,payload)
    VALUES(v_event_id,v_event_type,'MaintainTrack','1.1',now(),v_source_id,v_record_type,v_event);
    IF TG_OP='DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;
NOTIFY pgrst,'reload schema';
COMMIT;
