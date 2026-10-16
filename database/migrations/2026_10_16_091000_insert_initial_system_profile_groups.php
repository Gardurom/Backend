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
                "INSERT INTO system.profile_groups
                    (
                        name,
                        code,
                        sort_order,
                        is_active,
                        created_at,
                        updated_at
                    )
                 VALUES
                    (
                        'Operación',
                        'GRUPO_OPERACION',
                        10,
                        TRUE,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    ),
                    (
                        'Control y supervisión',
                        'GRUPO_CONTROL_SUPERVISION',
                        20,
                        TRUE,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    ),
                    (
                        'Administración',
                        'GRUPO_ADMINISTRACION',
                        30,
                        TRUE,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )"
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
                "DELETE FROM system.profile_groups
                 WHERE code IN (
                    'GRUPO_OPERACION',
                    'GRUPO_CONTROL_SUPERVISION',
                    'GRUPO_ADMINISTRACION'
                 )"
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
