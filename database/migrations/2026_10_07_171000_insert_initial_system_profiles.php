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
                "INSERT INTO system.profiles
                    (name, code, created_at, updated_at)
                 VALUES
                    ('Administración', 'PERFIL_ADMINISTRACION', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Auditoría', 'PERFIL_AUDITORIA', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Personas', 'PERFIL_PERSONAS', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
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
                "DELETE FROM system.profiles
                 WHERE code IN (
                    'PERFIL_ADMINISTRACION',
                    'PERFIL_AUDITORIA',
                    'PERFIL_PERSONAS'
                 )"
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
