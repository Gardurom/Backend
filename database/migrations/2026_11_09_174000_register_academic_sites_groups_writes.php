<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'SIGA: se requiere una transaccion activa.'
            );
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS db, session_user AS actor'
        );

        if (
            $identity === null
            || $identity->db !== 'siga'
            || $identity->actor !== 'siga_migrator'
        ) {
            throw new RuntimeException(
                'SIGA: migracion institucional no autorizada.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            foreach ([
                'institutional.register_academic_site(bigint,text,text)',
                'institutional.register_academic_group(bigint,uuid,text,text)',
            ] as $signature) {
                $existing = DB::selectOne(
                    'SELECT to_regprocedure(CAST(? AS text)) AS signature',
                    [$signature]
                );

                if ($existing?->signature !== null) {
                    throw new RuntimeException(
                        'SIGA: funcion existente; no se sobrescribira: '
                        .$signature
                    );
                }
            }

            DB::unprepared(<<<'SQL'
CREATE FUNCTION institutional.register_academic_site(
    p_actor_id bigint,
    p_codigo text,
    p_nombre text
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, institutional, system, pg_temp
AS $function$
DECLARE
    v_id uuid;
    v_codigo text := btrim(p_codigo);
    v_nombre text := btrim(p_nombre);
BEGIN
    IF session_user <> 'siga_app' THEN
        RAISE EXCEPTION 'SIGA: conexion de aplicacion requerida'
            USING ERRCODE = '42501';
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
          AND p.code = 'academico.organizacion.registrar'
          AND rp.effect = 'ALLOW'
    ) OR EXISTS (
        SELECT 1
        FROM system.user_roles ur
        JOIN system.role_permissions rp ON rp.role_id = ur.role_id
        JOIN system.permissions p ON p.id = rp.permission_id
        WHERE ur.user_id = p_actor_id
          AND p.code = 'academico.organizacion.registrar'
          AND rp.effect = 'DENY'
    ) THEN
        RAISE EXCEPTION 'SIGA: permiso academico insuficiente'
            USING ERRCODE = '42501';
    END IF;

    IF v_codigo IS NULL
       OR v_codigo !~ '^[A-Z0-9]{1,30}$'
       OR v_nombre IS NULL
       OR length(v_nombre) < 1
       OR length(v_nombre) > 200 THEN
        RAISE EXCEPTION 'SIGA: datos de sede invalidos'
            USING ERRCODE = '22023';
    END IF;

    INSERT INTO institutional.academic_sites (
        codigo, nombre, activa
    )
    VALUES (
        v_codigo, v_nombre, true
    )
    RETURNING id_sede INTO v_id;

    INSERT INTO system.activities (
        id_usuario, entidad, id_entidad, accion,
        datos_anteriores, datos_nuevos, campos_modificados
    )
    VALUES (
        p_actor_id,
        'ACADEMICO_SEDE',
        v_id::text,
        'CREACION',
        NULL,
        jsonb_build_object(
            'id_sede', v_id,
            'codigo', v_codigo,
            'nombre', v_nombre,
            'activa', true
        ),
        '["codigo","nombre","activa"]'::jsonb
    );

    RETURN jsonb_build_object(
        'id', v_id,
        'codigo', v_codigo,
        'nombre', v_nombre,
        'activa', true
    );
END
$function$;
SQL);

            DB::unprepared(<<<'SQL'
CREATE FUNCTION institutional.register_academic_group(
    p_actor_id bigint,
    p_id_cohorte uuid,
    p_codigo text,
    p_especializacion text
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, institutional, system, pg_temp
AS $function$
DECLARE
    v_id uuid;
    v_codigo text := btrim(p_codigo);
BEGIN
    IF session_user <> 'siga_app' THEN
        RAISE EXCEPTION 'SIGA: conexion de aplicacion requerida'
            USING ERRCODE = '42501';
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
          AND p.code = 'academico.organizacion.registrar'
          AND rp.effect = 'ALLOW'
    ) OR EXISTS (
        SELECT 1
        FROM system.user_roles ur
        JOIN system.role_permissions rp ON rp.role_id = ur.role_id
        JOIN system.permissions p ON p.id = rp.permission_id
        WHERE ur.user_id = p_actor_id
          AND p.code = 'academico.organizacion.registrar'
          AND rp.effect = 'DENY'
    ) THEN
        RAISE EXCEPTION 'SIGA: permiso academico insuficiente'
            USING ERRCODE = '42501';
    END IF;

    IF p_id_cohorte IS NULL
       OR v_codigo IS NULL
       OR v_codigo !~ '^[A-Z0-9]{1,30}$'
       OR (
           p_especializacion IS NOT NULL
           AND p_especializacion NOT IN ('CAMPO', 'GABINETE')
       ) THEN
        RAISE EXCEPTION 'SIGA: datos de grupo invalidos'
            USING ERRCODE = '22023';
    END IF;

    PERFORM 1
    FROM institutional.academic_cohorts
    WHERE id_cohorte = p_id_cohorte
    FOR KEY SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'SIGA: generacion o escalon inexistente'
            USING ERRCODE = '22023';
    END IF;

    INSERT INTO institutional.academic_groups (
        id_cohorte, codigo, especializacion
    )
    VALUES (
        p_id_cohorte, v_codigo, p_especializacion
    )
    RETURNING id_grupo INTO v_id;

    INSERT INTO system.activities (
        id_usuario, entidad, id_entidad, accion,
        datos_anteriores, datos_nuevos, campos_modificados
    )
    VALUES (
        p_actor_id,
        'ACADEMICO_GRUPO',
        v_id::text,
        'CREACION',
        NULL,
        jsonb_build_object(
            'id_grupo', v_id,
            'id_cohorte', p_id_cohorte,
            'codigo', v_codigo,
            'especializacion', p_especializacion
        ),
        '["id_cohorte","codigo","especializacion"]'::jsonb
    );

    RETURN jsonb_build_object(
        'id', v_id,
        'id_cohorte', p_id_cohorte,
        'codigo', v_codigo,
        'especializacion', p_especializacion
    );
END
$function$;
SQL);

            foreach ([
                'institutional.register_academic_site(bigint,text,text)',
                'institutional.register_academic_group(bigint,uuid,text,text)',
            ] as $signature) {
                DB::statement(
                    'REVOKE ALL ON FUNCTION '.$signature.' FROM PUBLIC'
                );

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
            'SIGA: retiro automatico de funciones academicas bloqueado.'
        );
    }
};