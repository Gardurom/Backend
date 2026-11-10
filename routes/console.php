<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
/* SIGA_USR04_MONITOREO_INTEGRIDAD_V2 */
Artisan::command('siga:verificar-operacion', function (): int {
    $db = null;
    $fallos = [];

    try {
        $db = \Illuminate\Support\Facades\DB::connection();

        if ($db->getDriverName() !== 'pgsql' || $db->transactionLevel() !== 0) {
            throw new \RuntimeException('Conexion o transaccion no autorizada');
        }

        $db->beginTransaction();

        try {
            $db->statement(
                'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
            );

            $identity = $db->selectOne(
                'SELECT current_database() AS nombre'
            );

            if ($identity->nombre !== 'siga') {
                throw new \RuntimeException('Base distinta de siga');
            }

            $this->info('[OK] conexion: PostgreSQL/siga, solo lectura');

            $users = $db->selectOne(<<<'SQL'
SELECT
    count(*) FILTER (WHERE u.access_status = 'HABILITADO') AS habilitados,
    count(*) FILTER (
        WHERE u.access_version IS NULL OR u.access_version <= 0
    ) AS versiones_invalidas,
    count(*) FILTER (
        WHERE u.id_persona IS NOT NULL AND p.id_persona IS NULL
    ) AS personas_ausentes,
    count(*) FILTER (
        WHERE u.access_status = 'HABILITADO' AND (
            u.id_persona IS NULL
            OR p.id_persona IS NULL
            OR p.estatus <> 'ACTIVO'
            OR coalesce(u.access_security_blocked, false)
            OR u.access_suspended_until > CURRENT_TIMESTAMP
            OR u.access_valid_from > CURRENT_TIMESTAMP
            OR u.access_valid_until <= CURRENT_TIMESTAMP
        )
    ) AS habilitados_no_elegibles
FROM system.users AS u
LEFT JOIN institutional.persons AS p
    ON p.id_persona = u.id_persona
SQL);

            $ok = (int) $users->versiones_invalidas === 0
                && (int) $users->personas_ausentes === 0
                && (int) $users->habilitados_no_elegibles === 0;

            $this->line(
                ($ok ? '[OK]' : '[ERROR]') .
                ' personas_usuarios: habilitados=' . $users->habilitados .
                ', no_elegibles=' . $users->habilitados_no_elegibles .
                ', versiones_invalidas=' . $users->versiones_invalidas .
                ', personas_ausentes=' . $users->personas_ausentes
            );

            if (!$ok) {
                $fallos[] = 'personas_usuarios';
            }

            $roles = $db->selectOne(<<<'SQL'
SELECT
    (SELECT count(*) FROM system.roles
     WHERE code = 'ROL_ADMIN_SISTEMA') AS catalogo_admin,

    (SELECT count(*) FROM system.permissions
     WHERE code = 'usuarios.asignar_perfiles') AS catalogo_permiso,

    (SELECT count(*) FROM system.role_permissions AS rp
     JOIN system.permissions AS p ON p.id = rp.permission_id
     WHERE p.code = 'usuarios.asignar_perfiles') AS reglas_perfiles,

    (SELECT count(*) FROM system.role_permissions AS rp
     JOIN system.permissions AS p ON p.id = rp.permission_id
     JOIN system.roles AS r ON r.id = rp.role_id
     WHERE p.code = 'usuarios.asignar_perfiles'
       AND r.code = 'ROL_ADMIN_SISTEMA'
       AND rp.effect = 'ALLOW') AS reglas_admin,

    (SELECT count(*) FROM system.role_permissions
     WHERE effect IS NULL
        OR effect NOT IN ('ALLOW', 'DENY')) AS efectos_invalidos,

    (SELECT count(*) FROM system.user_roles AS ur
     LEFT JOIN system.users AS u ON u.id = ur.user_id
     LEFT JOIN system.roles AS r ON r.id = ur.role_id
     WHERE u.id IS NULL OR r.id IS NULL) AS roles_huerfanos,

    (SELECT count(DISTINCT u.id)
     FROM system.users AS u
     JOIN system.user_roles AS ur ON ur.user_id = u.id
     JOIN system.roles AS r ON r.id = ur.role_id
     WHERE u.access_status = 'HABILITADO'
       AND r.code = 'ROL_ADMIN_SISTEMA') AS admins_habilitados
SQL);

            $ok = (int) $roles->catalogo_admin === 1
                && (int) $roles->catalogo_permiso === 1
                && (int) $roles->reglas_perfiles === 1
                && (int) $roles->reglas_admin === 1
                && (int) $roles->efectos_invalidos === 0
                && (int) $roles->roles_huerfanos === 0
                && (int) $roles->admins_habilitados >= 1;

            $this->line(
                ($ok ? '[OK]' : '[ERROR]') .
                ' autorizacion_usr03: reglas=' . $roles->reglas_perfiles .
                ', administradores_habilitados=' . $roles->admins_habilitados .
                ', efectos_invalidos=' . $roles->efectos_invalidos .
                ', roles_huerfanos=' . $roles->roles_huerfanos
            );

            if (!$ok) {
                $fallos[] = 'autorizacion_usr03';
            }

            $sessions = $db->selectOne(<<<'SQL'
SELECT
    count(*) FILTER (
        WHERE s.user_id IS NOT NULL
    ) AS autenticadas,

    count(*) FILTER (
        WHERE s.user_id IS NULL
    ) AS invitadas,

    count(*) FILTER (
        WHERE s.user_id IS NOT NULL AND (
            u.id IS NULL
            OR u.access_status <> 'HABILITADO'
            OR p.id_persona IS NULL
            OR p.estatus <> 'ACTIVO'
            OR coalesce(u.access_security_blocked, false)
            OR u.access_suspended_until > CURRENT_TIMESTAMP
            OR u.access_valid_from > CURRENT_TIMESTAMP
            OR u.access_valid_until <= CURRENT_TIMESTAMP
        )
    ) AS no_elegibles
FROM system.sessions AS s
LEFT JOIN system.users AS u ON u.id = s.user_id
LEFT JOIN institutional.persons AS p ON p.id_persona = u.id_persona
SQL);

            $ok = (int) $sessions->no_elegibles === 0;

            $this->line(
                ($ok ? '[OK]' : '[ERROR]') .
                ' sesiones: autenticadas=' . $sessions->autenticadas .
                ', invitadas=' . $sessions->invitadas .
                ', no_elegibles=' . $sessions->no_elegibles
            );

            if (!$ok) {
                $fallos[] = 'sesiones';
            }

            $profiles = $db->selectOne(<<<'SQL'
SELECT
    (SELECT count(*) FROM system.user_profiles AS up
     LEFT JOIN system.users AS u ON u.id = up.user_id
     LEFT JOIN system.profiles AS p ON p.id = up.profile_id
     WHERE u.id IS NULL OR p.id IS NULL
    ) AS asignaciones_huerfanas,

    (SELECT count(*) FROM (
        SELECT user_id
        FROM system.user_profiles
        GROUP BY user_id
        HAVING count(*) FILTER (WHERE is_default IS TRUE) <> 1
     ) AS broken
    ) AS predeterminados_invalidos
SQL);

            $ok = (int) $profiles->asignaciones_huerfanas === 0
                && (int) $profiles->predeterminados_invalidos === 0;

            $this->line(
                ($ok ? '[OK]' : '[ERROR]') .
                ' perfiles: huerfanos=' . $profiles->asignaciones_huerfanas .
                ', predeterminados_invalidos=' .
                $profiles->predeterminados_invalidos
            );

            if (!$ok) {
                $fallos[] = 'perfiles';
            }

            $audit = $db->selectOne(<<<'SQL'
SELECT
    (SELECT count(*) FROM system.activities AS a
     LEFT JOIN system.users AS u ON u.id = a.id_usuario
     WHERE u.id IS NULL
    ) AS actores_huerfanos,

    (SELECT count(*) FROM system.users AS u
     WHERE NOT EXISTS (
         SELECT 1
         FROM system.activities AS a
         WHERE a.entidad = 'USUARIO'
           AND a.id_entidad = u.id::text
     )
    ) AS usuarios_sin_bitacora,

    (SELECT count(*) FROM system.activities) AS eventos
SQL);

            $ok = (int) $audit->actores_huerfanos === 0
                && (int) $audit->usuarios_sin_bitacora === 0;

            $this->line(
                ($ok ? '[OK]' : '[ERROR]') .
                ' auditoria: eventos=' . $audit->eventos .
                ', actores_huerfanos=' . $audit->actores_huerfanos .
                ', usuarios_sin_bitacora=' . $audit->usuarios_sin_bitacora
            );

            if (!$ok) {
                $fallos[] = 'auditoria';
            }

        } finally {
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
        }

    } catch (\Throwable $error) {
        $fallos[] = 'infraestructura';

        $this->error(
            '[ERROR] infraestructura: revise conexion, estructura ' .
            'y permisos de lectura'
        );

    } finally {
        if ($db !== null) {
            $db->disconnect();
        }
    }

    if ($fallos !== []) {
        \Illuminate\Support\Facades\Log::error(
            'SIGA_MONITOREO_OPERATIVO_ERROR',
            ['controles' => $fallos]
        );

        $this->error('SIGA_MONITOREO_OPERATIVO_NO_APROBADO');

        return 1;
    }

    \Illuminate\Support\Facades\Log::info(
        'SIGA_MONITOREO_OPERATIVO_OK',
        ['controles_verificados' => 6]
    );

    $this->info('SIGA_MONITOREO_OPERATIVO_VERIFICADO');

    $this->line(
        'Nota: no se inspecciona el contenido cifrado de las sesiones.'
    );

    return 0;

})->purpose(
    'Verificar integridad de acceso, sesiones, perfiles y auditoria de SIGA'
);
