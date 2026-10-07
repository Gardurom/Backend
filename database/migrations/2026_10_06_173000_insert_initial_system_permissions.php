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
                    ('Consultar auditoría', 'auditoria.ver', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Actualizar personas', 'personas.actualizar', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Dar de baja personas', 'personas.baja', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Registrar personas', 'personas.crear', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Reingresar personas', 'personas.reingreso', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Consultar personas', 'personas.ver', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Asignar roles a usuarios', 'usuarios.asignar_roles', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    ('Crear usuarios', 'usuarios.crear', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
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
                "DELETE FROM system.permissions
                 WHERE code IN (
                    'auditoria.ver',
                    'personas.actualizar',
                    'personas.baja',
                    'personas.crear',
                    'personas.reingreso',
                    'personas.ver',
                    'usuarios.asignar_roles',
                    'usuarios.crear'
                 )"
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
