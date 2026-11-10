<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('SIGA: transaccion obligatoria.');
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS db, session_user AS actor'
        );

        if (
            $identity?->db !== 'siga'
            || $identity?->actor !== 'siga_migrator'
        ) {
            throw new RuntimeException('SIGA: migrador no autorizado.');
        }

        DB::statement('SET ROLE siga_owner');

        try {
            foreach ([
                'institutional.assign_academic_site_internal(bigint,uuid,uuid,text)',
                'institutional.assign_academic_cohort_site(bigint,uuid,uuid)',
                'institutional.assign_academic_group_site(bigint,uuid,uuid)',
            ] as $signature) {
                $existing = DB::selectOne(
                    'SELECT to_regprocedure(CAST(? AS text)) AS signature',
                    [$signature]
                );

                if ($existing?->signature !== null) {
                    throw new RuntimeException(
                        'SIGA: funcion preexistente: '.$signature
                    );
                }
            }

            // Registrar el nuevo permiso con los mismos atributos
            // institucionales del permiso de registro, sin conceder roles.
            $source = DB::table('system.permissions')
                ->where('code', 'academico.organizacion.registrar')
                ->first();

            if ($source === null) {
                throw new RuntimeException(
                    'SIGA: no existe el permiso academico de referencia.'
                );
            }

            $exists = DB::table('system.permissions')
                ->where('code', 'academico.organizacion.trasladar')
                ->exists();

            if (!$exists) {
                $data = (array) $source;

                $primaryKeys = DB::select("
                    SELECT a.attname AS column_name
                    FROM pg_constraint c
                    JOIN pg_class t ON t.oid = c.conrelid
                    JOIN pg_namespace n ON n.oid = t.relnamespace
                    JOIN pg_attribute a ON a.attrelid = t.oid
                        AND a.attnum = ANY(c.conkey)
                    WHERE n.nspname = 'system'
                      AND t.relname = 'permissions'
                      AND c.contype = 'p'
                ");

                foreach ($primaryKeys as $key) {
                    unset($data[$key->column_name]);
                }

                $data['code'] = 'academico.organizacion.trasladar';

                foreach (['name', 'nombre', 'description', 'descripcion'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $data[$field] = 'Adscripcion y traslado de sedes academicas';
                    }
                }

                foreach (['created_at', 'updated_at'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $data[$field] = now();
                    }
                }

                DB::table('system.permissions')->insert($data);
            }

            DB::unprepared(<<<'SQL'
CREATE FUNCTION institutional.assign_academic_site_internal(
    p_actor_id bigint,
    p_entity_id uuid,
    p_site_id uuid,
    p_kind text
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, institutional, system, pg_temp
AS $function$
DECLARE
    v_now timestamptz := clock_timestamp();
    v_previous_site uuid;
    v_previous_start timestamptz;
    v_movement_id uuid;
    v_entity_name text;
BEGIN
    IF session_user <> 'siga_app' THEN
        RAISE EXCEPTION 'SIGA: conexion de aplicacion requerida'
            USING ERRCODE = '42501';
    END IF;

    IF p_entity_id IS NULL
       OR p_site_id IS NULL
       OR p_kind NOT IN ('COHORTE', 'GRUPO')
       OR p_kind IS NULL THEN
        RAISE EXCEPTION 'SIGA: datos de adscripcion invalidos'
            USING ERRCODE = '22023';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM system.users u
        JOIN institutional.persons persona
          ON persona.id_persona = u.id_persona
        WHERE u.id = p_actor_id
          AND u.access_status = 'HABILITADO'
          AND persona.estatus = 'ACTIVO'
          AND COALESCE(u.access_security_blocked, false) = false
    ) THEN
        RAISE EXCEPTION 'SIGA: operador no habilitado'
            USING ERRCODE = '42501';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM system.user_roles ur
        JOIN system.role_permissions rp ON rp.role_id = ur.role_id
        JOIN system.permissions p ON p.id = rp.permission_id
        WHERE ur.user_id = p_actor_id
          AND p.code = 'academico.organizacion.trasladar'
          AND rp.effect = 'ALLOW'
    ) OR EXISTS (
        SELECT 1
        FROM system.user_roles ur
        JOIN system.role_permissions rp ON rp.role_id = ur.role_id
        JOIN system.permissions p ON p.id = rp.permission_id
        WHERE ur.user_id = p_actor_id
          AND p.code = 'academico.organizacion.trasladar'
          AND rp.effect = 'DENY'
    ) THEN
        RAISE EXCEPTION 'SIGA: permiso de traslado insuficiente'
            USING ERRCODE = '42501';
    END IF;

    PERFORM 1 FROM institutional.academic_sites
    WHERE id_sede = p_site_id AND activa = true
    FOR KEY SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'SIGA: sede inexistente o inactiva'
            USING ERRCODE = '22023';
    END IF;

    IF p_kind = 'COHORTE' THEN
        PERFORM 1 FROM institutional.academic_cohorts
        WHERE id_cohorte = p_entity_id FOR UPDATE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'SIGA: cohorte inexistente'
                USING ERRCODE = '22023';
        END IF;

        SELECT id_sede, vigente_desde
        INTO v_previous_site, v_previous_start
        FROM institutional.academic_cohort_site_history
        WHERE id_cohorte = p_entity_id
          AND vigente_hasta IS NULL
        FOR UPDATE;

        IF FOUND THEN
            IF v_previous_site = p_site_id THEN
                RAISE EXCEPTION 'SIGA: sede vigente sin cambios'
                    USING ERRCODE = '22023';
            END IF;

            IF v_now <= v_previous_start THEN
                RAISE EXCEPTION 'SIGA: vigencia temporal invalida'
                    USING ERRCODE = '22023';
            END IF;

            UPDATE institutional.academic_cohort_site_history
            SET vigente_hasta = v_now,
                updated_at = v_now
            WHERE id_cohorte = p_entity_id
              AND vigente_hasta IS NULL;
        END IF;

        INSERT INTO institutional.academic_cohort_site_history (
            id_cohorte, id_sede, vigente_desde
        ) VALUES (
            p_entity_id, p_site_id, v_now
        ) RETURNING id_movimiento INTO v_movement_id;

        v_entity_name := 'ACADEMICO_COHORTE_SEDE';
    ELSE
        PERFORM 1 FROM institutional.academic_groups
        WHERE id_grupo = p_entity_id FOR UPDATE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'SIGA: grupo inexistente'
                USING ERRCODE = '22023';
        END IF;

        SELECT id_sede, vigente_desde
        INTO v_previous_site, v_previous_start
        FROM institutional.academic_group_site_history
        WHERE id_grupo = p_entity_id
          AND vigente_hasta IS NULL
        FOR UPDATE;

        IF FOUND THEN
            IF v_previous_site = p_site_id THEN
                RAISE EXCEPTION 'SIGA: sede vigente sin cambios'
                    USING ERRCODE = '22023';
            END IF;

            IF v_now <= v_previous_start THEN
                RAISE EXCEPTION 'SIGA: vigencia temporal invalida'
                    USING ERRCODE = '22023';
            END IF;

            UPDATE institutional.academic_group_site_history
            SET vigente_hasta = v_now,
                updated_at = v_now
            WHERE id_grupo = p_entity_id
              AND vigente_hasta IS NULL;
        END IF;

        INSERT INTO institutional.academic_group_site_history (
            id_grupo, id_sede, vigente_desde
        ) VALUES (
            p_entity_id, p_site_id, v_now
        ) RETURNING id_movimiento INTO v_movement_id;

        v_entity_name := 'ACADEMICO_GRUPO_SEDE';
    END IF;

    INSERT INTO system.activities (
        id_usuario, entidad, id_entidad, accion,
        datos_anteriores, datos_nuevos, campos_modificados
    )
    VALUES (
        p_actor_id,
        v_entity_name,
        v_movement_id::text,
        CASE
            WHEN v_previous_site IS NULL THEN 'CREACION'
            ELSE 'ACTUALIZACION'
        END,
        CASE
            WHEN v_previous_site IS NULL THEN NULL
            ELSE jsonb_build_object('id_sede', v_previous_site)
        END,
        jsonb_build_object(
            'tipo', p_kind,
            'id_entidad', p_entity_id,
            'id_sede', p_site_id,
            'id_movimiento', v_movement_id,
            'vigente_desde', v_now
        ),
        '["id_sede","vigente_desde"]'::jsonb
    );

    RETURN jsonb_build_object(
        'id_movimiento', v_movement_id,
        'tipo', p_kind,
        'id_entidad', p_entity_id,
        'id_sede', p_site_id,
        'id_sede_anterior', v_previous_site,
        'vigente_desde', v_now
    );
END
$function$;
SQL);

            DB::unprepared(<<<'SQL'
CREATE FUNCTION institutional.assign_academic_cohort_site(
    p_actor_id bigint,
    p_cohort_id uuid,
    p_site_id uuid
)
RETURNS jsonb
LANGUAGE sql
SECURITY DEFINER
SET search_path = pg_catalog, institutional, system, pg_temp
AS $function$
    SELECT institutional.assign_academic_site_internal(
        p_actor_id, p_cohort_id, p_site_id, 'COHORTE'
    )
$function$;
SQL);

            DB::unprepared(<<<'SQL'
CREATE FUNCTION institutional.assign_academic_group_site(
    p_actor_id bigint,
    p_group_id uuid,
    p_site_id uuid
)
RETURNS jsonb
LANGUAGE sql
SECURITY DEFINER
SET search_path = pg_catalog, institutional, system, pg_temp
AS $function$
    SELECT institutional.assign_academic_site_internal(
        p_actor_id, p_group_id, p_site_id, 'GRUPO'
    )
$function$;
SQL);

            foreach ([
                'institutional.assign_academic_site_internal(bigint,uuid,uuid,text)',
                'institutional.assign_academic_cohort_site(bigint,uuid,uuid)',
                'institutional.assign_academic_group_site(bigint,uuid,uuid)',
            ] as $signature) {
                DB::statement(
                    'REVOKE ALL ON FUNCTION '.$signature.' FROM PUBLIC'
                );
                DB::statement(
                    'REVOKE ALL ON FUNCTION '.$signature.' FROM siga_app'
                );
            }

            foreach ([
                'institutional.assign_academic_cohort_site(bigint,uuid,uuid)',
                'institutional.assign_academic_group_site(bigint,uuid,uuid)',
            ] as $signature) {
                DB::statement(
                    'GRANT EXECUTE ON FUNCTION '.$signature.' TO siga_app'
                );
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'SIGA: retiro automatico bloqueado.'
        );
    }
};