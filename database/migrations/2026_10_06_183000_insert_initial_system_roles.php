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
                "INSERT INTO system.roles
                    (name, code, created_at, updated_at)
                 VALUES
                    ('Administrador del Sistema', 'ROL_ADMIN_SISTEMA', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Auditor', 'ROL_AUDITOR', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Consulta de Personas', 'ROL_CONSULTA_PERSONAS', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Gestor de Personas', 'ROL_GESTOR_PERSONAS', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
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
                "DELETE FROM system.roles
                 WHERE code IN (
                    'ROL_ADMIN_SISTEMA',
                    'ROL_AUDITOR',
                    'ROL_CONSULTA_PERSONAS',
                    'ROL_GESTOR_PERSONAS'
                 )"
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
