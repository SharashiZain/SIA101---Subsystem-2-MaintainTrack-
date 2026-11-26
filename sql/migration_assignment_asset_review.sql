-- Assignment-page asset review and LifeCycleTrack condition handoff.
-- Apply after migration_lifecycle_asset_inbox.sql and the external lifecycle
-- request migrations.

ALTER TABLE public.maintenance_requests
    ADD COLUMN IF NOT EXISTS asset_review_required BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS lifecycle_assessment_reference UUID,
    ADD COLUMN IF NOT EXISTS asset_link_generation UUID NOT NULL DEFAULT gen_random_uuid();

CREATE OR REPLACE FUNCTION public.maintain_confirm_report_asset(
    p_request UUID,
    p_admin UUID,
    p_facility TEXT,
    p_equipment TEXT
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_request public.maintenance_requests%ROWTYPE;
    v_facility_name TEXT;
    v_equipment_name TEXT;
BEGIN
    IF p_request IS NULL OR p_admin IS NULL OR NULLIF(btrim(p_facility), '') IS NULL THEN
        RETURN FALSE;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM public.users u
        WHERE u.user_id = p_admin
          AND lower(btrim(u.role)) = 'admin'
          AND lower(btrim(u.status)) = 'active'
    ) THEN
        RETURN FALSE;
    END IF;

    SELECT *
    INTO v_request
    FROM public.maintenance_requests r
    WHERE r.request_id = p_request
    FOR UPDATE;

    IF NOT FOUND OR NOT v_request.asset_review_required THEN
        RETURN FALSE;
    END IF;

    SELECT NULLIF(btrim(s.payload->>'name'), '')
    INTO v_facility_name
    FROM public.external_asset_snapshots s
    WHERE s.external_system = 'LifeCycleTrack'
      AND s.entity_type = 'facility'
      AND s.external_asset_id = p_facility
      AND s.deleted_at IS NULL
      AND lower(COALESCE(s.payload->>'lifecycle_status', '')) <> 'disposed'
      AND NULLIF(s.payload->>'disposed_at', '') IS NULL;

    IF v_facility_name IS NULL THEN
        RETURN FALSE;
    END IF;

    IF NULLIF(btrim(p_equipment), '') IS NOT NULL THEN
        SELECT NULLIF(btrim(s.payload->>'name'), '')
        INTO v_equipment_name
        FROM public.external_asset_snapshots s
        WHERE s.external_system = 'LifeCycleTrack'
          AND s.entity_type = 'equipment'
          AND s.external_asset_id = p_equipment
          AND s.deleted_at IS NULL
          AND s.payload->>'facility_id' = p_facility
          AND lower(COALESCE(s.payload->>'lifecycle_status', '')) <> 'disposed'
          AND NULLIF(s.payload->>'disposed_at', '') IS NULL;

        IF v_equipment_name IS NULL THEN
            RETURN FALSE;
        END IF;
    END IF;

    UPDATE public.maintenance_requests
    SET facility_id = NULL,
        floor_id = NULL,
        room_id = NULL,
        equipment_id = NULL,
        external_facility_id = p_facility,
        external_facility_name = v_facility_name,
        external_equipment_id = CASE
            WHEN NULLIF(btrim(p_equipment), '') IS NULL THEN NULL
            ELSE p_equipment
        END,
        external_equipment_name = v_equipment_name,
        asset_review_required = FALSE,
        lifecycle_assessment_reference = NULL,
        asset_link_generation = gen_random_uuid(),
        updated_at = now()
    WHERE request_id = p_request;

    RETURN TRUE;
END;
$$;

CREATE OR REPLACE FUNCTION public.maintain_confirm_report_assessment(
    p_request UUID,
    p_admin UUID,
    p_entity TEXT,
    p_asset TEXT,
    p_link_generation UUID,
    p_assessment UUID
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    IF p_request IS NULL OR p_admin IS NULL OR p_link_generation IS NULL
       OR p_assessment IS NULL OR p_asset IS NULL
       OR p_entity NOT IN ('equipment', 'facility') THEN
        RETURN FALSE;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM public.users u
        WHERE u.user_id = p_admin
          AND lower(btrim(u.role)) = 'admin'
          AND lower(btrim(u.status)) = 'active'
    ) THEN
        RETURN FALSE;
    END IF;

    UPDATE public.maintenance_requests
    SET lifecycle_assessment_reference = p_assessment,
        updated_at = now()
    WHERE request_id = p_request
      AND asset_review_required = FALSE
      AND asset_link_generation = p_link_generation
      AND lower(COALESCE(status, '')) NOT IN ('completed', 'cancelled', 'closed')
      AND (
          (p_entity = 'equipment' AND external_equipment_id = p_asset)
          OR (p_entity = 'facility' AND external_facility_id = p_asset)
      );

    RETURN FOUND;
END;
$$;

REVOKE ALL ON FUNCTION public.maintain_confirm_report_asset(UUID, UUID, TEXT, TEXT)
    FROM PUBLIC, anon, authenticated;
REVOKE ALL ON FUNCTION public.maintain_confirm_report_assessment(UUID, UUID, TEXT, TEXT, UUID, UUID)
    FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.maintain_confirm_report_asset(UUID, UUID, TEXT, TEXT)
    TO service_role;
GRANT EXECUTE ON FUNCTION public.maintain_confirm_report_assessment(UUID, UUID, TEXT, TEXT, UUID, UUID)
    TO service_role;

NOTIFY pgrst, 'reload schema';
