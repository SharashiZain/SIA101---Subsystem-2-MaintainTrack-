-- Capture MaintainTrack work-order changes in the same PostgreSQL transaction.
-- Apply after migration_integration_foundation.sql.
CREATE OR REPLACE FUNCTION public.maintain_capture_integration_event()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path=public,pg_temp AS $$
DECLARE
    v_event_id UUID:=gen_random_uuid();
    v_event_type TEXT;
    v_record_type TEXT;
    v_source_id TEXT;
    v_payload JSONB;
    v_row JSONB;
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
                'equipment_id',v_row->'equipment_id','category',v_row->'category','equipment_category',v_row->'equipment_category',
                'status',v_row->'status','approval_status',v_row->'approval_status','priority',v_row->'priority',
                'date_reported',v_row->'date_reported','date_assigned',v_row->'date_assigned',
                'date_started',v_row->'date_started','date_completed',v_row->'date_completed',
                'updated_at',COALESCE(v_row->'updated_at',to_jsonb(now()))
            ));
        END IF;
    ELSE
        v_source_id:=COALESCE(NEW.update_id,OLD.update_id)::text;
        v_record_type:='maintenance_update';
        IF TG_OP='DELETE' THEN
            v_event_type:='maintenance.result.recorded';
            v_payload:=jsonb_build_object('id',v_source_id,'request_id',OLD.request_id,'status','deleted');
        ELSE
            v_row:=to_jsonb(NEW);
            v_event_type:='maintenance.result.recorded';
            v_payload:=jsonb_strip_nulls(jsonb_build_object('id',v_source_id,'request_id',v_row->'request_id','status',v_row->'status','created_at',v_row->'created_at'));
        END IF;
    END IF;
    INSERT INTO public.integration_outbox(event_id,event_type,source_system,event_version,correlation_id,occurred_at,source_id,record_type,payload)
    VALUES(v_event_id,v_event_type,'MaintainTrack','1.0',gen_random_uuid(),now(),v_source_id,v_record_type,
       jsonb_build_object('event_id',v_event_id,'event_type',v_event_type,'source','MaintainTrack','destination',NULL,
          'version','1.0','correlation_id',NULL,'occurred_at',now(),'source_id',v_source_id,
          'record_type',v_record_type,'payload',v_payload));
    IF TG_OP='DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS maintain_requests_integration_capture ON public.maintenance_requests;
CREATE TRIGGER maintain_requests_integration_capture
AFTER INSERT OR UPDATE OR DELETE ON public.maintenance_requests
FOR EACH ROW EXECUTE FUNCTION public.maintain_capture_integration_event();

DROP TRIGGER IF EXISTS maintain_updates_integration_capture ON public.maintenance_updates;
CREATE TRIGGER maintain_updates_integration_capture
AFTER INSERT OR UPDATE OR DELETE ON public.maintenance_updates
FOR EACH ROW EXECUTE FUNCTION public.maintain_capture_integration_event();

REVOKE ALL ON FUNCTION public.maintain_capture_integration_event() FROM PUBLIC,anon,authenticated;
