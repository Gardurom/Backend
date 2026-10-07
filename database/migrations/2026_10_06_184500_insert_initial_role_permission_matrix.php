<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                "WITH expected(role_code, permission_code) AS (
                    VALUES
                        ('ROL_ADMIN_SISTEMA', 'usuarios.asignar_roles'),
                        ('ROL_ADMIN_SISTEMA', 'usuarios.crear'),
                        ('ROL_AUDITOR', 'auditoria.ver'),
                        ('ROL_CONSULTA_PERSONAS', 'personas.ver'),
                        ('ROL_GESTOR_PERSONAS', 'personas.actualizar'),
                        ('ROL_GESTOR_PERSONAS', 'personas.baja'),
                        ('ROL_GESTOR_PERSONAS', 'personas.crear'),
                        ('ROL_GESTOR_PERSONAS', 'personas.reingreso'),
                        ('ROL_GESTOR_PERSONAS', 'personas.ver')
                 )
                 INSERT INTO system.role_permissions
                    (role_id, permission_id, created_at, updated_at)
                 SELECT
                    r.id,
                    p.id,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                 FROM expected AS e
                 JOIN system.roles AS r
                   ON r.code = e.role_code
                 JOIN system.permissions AS p
                   ON p.code = e.permission_code"
            );

            $count = DB::selectOne(
                "SELECT COUNT(*) AS total
                 FROM system.role_permissions AS rp
                 JOIN system.roles AS r
                   ON r.id = rp.role_id
                 JOIN system.permissions AS p
                   ON p.id = rp.permission_id
                 WHERE (r.code, p.code) IN (
                    ('ROL_ADMIN_SISTEMA', 'usuarios.asignar_roles'),
                    ('ROL_ADMIN_SISTEMA', 'usuarios.crear'),
                    ('ROL_AUDITOR', 'auditoria.ver'),
                    ('ROL_CONSULTA_PERSONAS', 'personas.ver'),
                    ('ROL_GESTOR_PERSONAS', 'personas.actualizar'),
                    ('ROL_GESTOR_PERSONAS', 'personas.baja'),
                    ('ROL_GESTOR_PERSONAS', 'personas.crear'),
                    ('ROL_GESTOR_PERSONAS', 'personas.reingreso'),
                    ('ROL_GESTOR_PERSONAS', 'personas.ver')
                 )"
            );

            if ((int) $count->total !== 9) {
                throw new RuntimeException(
                    'No se pudo crear íntegramente la matriz inicial de roles y permisos.'
                );
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                "WITH expected(role_code, permission_code) AS (
                    VALUES
                        ('ROL_ADMIN_SISTEMA', 'usuarios.asignar_roles'),
                        ('ROL_ADMIN_SISTEMA', 'usuarios.crear'),
                        ('ROL_AUDITOR', 'auditoria.ver'),
                        ('ROL_CONSULTA_PERSONAS', 'personas.ver'),
                        ('ROL_GESTOR_PERSONAS', 'personas.actualizar'),
                        ('ROL_GESTOR_PERSONAS', 'personas.baja'),
                        ('ROL_GESTOR_PERSONAS', 'personas.crear'),
                        ('ROL_GESTOR_PERSONAS', 'personas.reingreso'),
                        ('ROL_GESTOR_PERSONAS', 'personas.ver')
                 ),
                 targets AS (
                    SELECT
                        r.id AS role_id,
                        p.id AS permission_id
                    FROM expected AS e
                    JOIN system.roles AS r
                      ON r.code = e.role_code
                    JOIN system.permissions AS p
                      ON p.code = e.permission_code
                 )
                 DELETE FROM system.role_permissions AS rp
                 USING targets AS t
                 WHERE rp.role_id = t.role_id
                   AND rp.permission_id = t.permission_id"
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
