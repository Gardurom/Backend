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
                'institutional.register_academic_program(bigint,text,text)',
                'institutional.register_academic_cohort(bigint,uuid,text,integer,integer)',
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

            DB::table('system.permissions')->insertOrIgnore([
                'code' => 'academico.organizacion.registrar',
                'name' => 'Registrar organizacion academica institucional',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::unprepared(<<<'SQL'
CREATE FUNCTION institutional.register_academic_program(
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
        RAISE EXCEPTION 'SIGA: datos de programa invalidos'
            USING ERRCODE = '22023';
    END IF;

    INSERT INTO institutional.academic_programs (codigo, nombre)
    VALUES (v_codigo, v_nombre)
    RETURNING id_programa INTO v_id;

    INSERT INTO system.activities (
        id_usuario, entidad, id_entidad, accion,
        datos_anteriores, datos_nuevos, campos_modificados
    )
    VALUES (
        p_actor_id,
        'ACADEMICO_PROGRAMA',
        v_id::text,
        'CREACION',
        NULL,
        jsonb_build_object(
            'id_programa', v_id,
            'codigo', v_codigo,
            'nombre', v_nombre
        ),
        '["codigo","nombre"]'::jsonb
    );

    RETURN jsonb_build_object(
        'id', v_id,
        'codigo', v_codigo,
        'nombre', v_nombre
    );
END
$function$;
SQL);

            DB::unprepared(<<<'SQL'
CREATE FUNCTION institutional.register_academic_cohort(
    p_actor_id bigint,
    p_id_programa uuid,
    p_tipo text,
    p_numero integer,
    p_anio integer
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, institutional, system, pg_temp
AS $function$
DECLARE
    v_id uuid;
    v_codigo_programa text;
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

    IF p_id_programa IS NULL
       OR p_tipo IS NULL
       OR p_tipo NOT IN ('G', 'E')
       OR p_numero IS NULL
       OR p_numero < 1
       OR p_anio IS NULL
       OR p_anio NOT BETWEEN 1900 AND 9999 THEN
        RAISE EXCEPTION 'SIGA: datos de cohorte invalidos'
            USING ERRCODE = '22023';
    END IF;

    SELECT codigo INTO v_codigo_programa
    FROM institutional.academic_programs
    WHERE id_programa = p_id_programa
    FOR KEY SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'SIGA: programa inexistente'
            USING ERRCODE = '22023';
    END IF;

    INSERT INTO institutional.academic_cohorts (
        id_programa, tipo, numero, anio
    )
    VALUES (
        p_id_programa, p_tipo, p_numero, p_anio
    )
    RETURNING id_cohorte INTO v_id;

    INSERT INTO system.activities (
        id_usuario, entidad, id_entidad, accion,
        datos_anteriores, datos_nuevos, campos_modificados
    )
    VALUES (
        p_actor_id,
        'ACADEMICO_COHORTE',
        v_id::text,
        'CREACION',
        NULL,
        jsonb_build_object(
            'id_cohorte', v_id,
            'id_programa', p_id_programa,
            'tipo', p_tipo,
            'numero', p_numero,
            'anio', p_anio
        ),
        '["id_programa","tipo","numero","anio"]'::jsonb
    );

    RETURN jsonb_build_object(
        'id', v_id,
        'id_programa', p_id_programa,
        'codigo', v_codigo_programa || '-'
            || p_tipo || lpad(p_numero::text, 2, '0')
            || '-' || p_anio::text,
        'tipo', p_tipo,
        'numero', p_numero,
        'anio', p_anio
    );
END
$function$;
SQL);

            foreach ([
                'institutional.register_academic_program(bigint,text,text)',
                'institutional.register_academic_cohort(bigint,uuid,text,integer,integer)',
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