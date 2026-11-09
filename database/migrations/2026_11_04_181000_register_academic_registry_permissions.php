<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            foreach ([
                'academico.participantes.ver' => 'Consultar registro maestro de participantes académicos',
                'academico.participantes.registrar' => 'Registrar participantes académicos vinculados a Persona',
                'academico.formadores.ver' => 'Consultar registro maestro de formadores',
                'academico.formadores.registrar' => 'Registrar formadores vinculados a Persona',
            ] as $code => $name) {
                DB::table('system.permissions')->insertOrIgnore([
                    'code' => $code,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        // Conservar permisos y posibles referencias históricas.
        // Su retiro requiere una operación institucional independiente.
    }
};
