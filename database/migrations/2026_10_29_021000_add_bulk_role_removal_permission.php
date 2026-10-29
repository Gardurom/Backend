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
                "INSERT INTO system.permissions
                    (name, code, created_at, updated_at)
                 VALUES (
                    'Retirar roles masivamente a usuarios',
                    'usuarios.retirar_roles_masivamente',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                 )"
            );

            DB::statement(
                "INSERT INTO system.role_permissions
                    (role_id, permission_id, effect, created_at, updated_at)
                 SELECT
                    r.id,
                    p.id,
                    'ALLOW',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                 FROM system.roles AS r
                 CROSS JOIN system.permissions AS p
                 WHERE r.code = 'ROL_ADMIN_SISTEMA'
                   AND p.code = 'usuarios.retirar_roles_masivamente'"
            );

            $result = DB::selectOne(
                "SELECT COUNT(*) AS total
                 FROM system.role_permissions AS rp
                 JOIN system.roles AS r
                   ON r.id = rp.role_id
                 JOIN system.permissions AS p
                   ON p.id = rp.permission_id
                 WHERE r.code = 'ROL_ADMIN_SISTEMA'
                   AND p.code = 'usuarios.retirar_roles_masivamente'
                   AND rp.effect = 'ALLOW'"
            );

            if ((int) $result->total !== 1) {
                throw new RuntimeException(
                    'No se pudo configurar el permiso administrativo de retirada masiva.'
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
                "DELETE FROM system.role_permissions AS rp
                 USING system.roles AS r,
                       system.permissions AS p
                 WHERE rp.role_id = r.id
                   AND rp.permission_id = p.id
                   AND r.code = 'ROL_ADMIN_SISTEMA'
                   AND p.code = 'usuarios.retirar_roles_masivamente'"
            );

            DB::statement(
                "DELETE FROM system.permissions
                 WHERE code = 'usuarios.retirar_roles_masivamente'"
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
