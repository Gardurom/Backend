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
                'ALTER TABLE system.role_permissions
                 ADD COLUMN effect varchar(5)'
            );

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
                 UPDATE system.role_permissions AS rp
                 SET effect = 'ALLOW'
                 FROM targets AS t
                 WHERE rp.role_id = t.role_id
                   AND rp.permission_id = t.permission_id"
            );

            $withoutEffect = DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM system.role_permissions
                 WHERE effect IS NULL'
            );

            if ((int) $withoutEffect->total !== 0) {
                throw new RuntimeException(
                    'Existen relaciones rol-permiso no reconocidas sin efecto explícito.'
                );
            }

            DB::statement(
                "ALTER TABLE system.role_permissions
                 ADD CONSTRAINT role_permissions_effect_check
                 CHECK (effect IN ('ALLOW', 'DENY'))"
            );

            DB::statement(
                'ALTER TABLE system.role_permissions
                 ALTER COLUMN effect SET NOT NULL'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                'ALTER TABLE system.role_permissions
                 DROP CONSTRAINT IF EXISTS role_permissions_effect_check'
            );

            DB::statement(
                'ALTER TABLE system.role_permissions
                 DROP COLUMN IF EXISTS effect'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
