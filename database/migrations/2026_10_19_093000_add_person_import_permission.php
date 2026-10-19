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
                 VALUES
                    (
                        'Importar personas',
                        'personas.importar',
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )"
            );

            DB::statement(
                "INSERT INTO system.role_permissions
                    (
                        role_id,
                        permission_id,
                        effect,
                        created_at,
                        updated_at
                    )
                 SELECT
                    r.id,
                    p.id,
                    'ALLOW',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                 FROM system.roles AS r
                 CROSS JOIN system.permissions AS p
                 WHERE r.code = 'ROL_GESTOR_PERSONAS'
                   AND p.code = 'personas.importar'"
            );

            $relationship = DB::selectOne(
                "SELECT COUNT(*) AS total
                 FROM system.role_permissions AS rp
                 JOIN system.roles AS r
                   ON r.id = rp.role_id
                 JOIN system.permissions AS p
                   ON p.id = rp.permission_id
                 WHERE r.code = 'ROL_GESTOR_PERSONAS'
                   AND p.code = 'personas.importar'
                   AND rp.effect = 'ALLOW'"
            );

            if ((int) $relationship->total !== 1) {
                throw new RuntimeException(
                    'No se pudo asignar personas.importar a ROL_GESTOR_PERSONAS.'
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
                   AND r.code = 'ROL_GESTOR_PERSONAS'
                   AND p.code = 'personas.importar'"
            );

            DB::statement(
                "DELETE FROM system.permissions
                 WHERE code = 'personas.importar'"
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
