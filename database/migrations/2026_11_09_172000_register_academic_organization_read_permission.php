<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException(
                'SIGA: se requiere una transaccion activa.'
            );
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS database_name, session_user AS session_role'
        );

        if (
            $identity === null ||
            $identity->database_name !== 'siga' ||
            $identity->session_role !== 'siga_migrator'
        ) {
            throw new \RuntimeException(
                'SIGA: identidad migradora no autorizada.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            DB::table('system.permissions')->insertOrIgnore([
                'code' => 'academico.organizacion.ver',
                'name' => 'Consultar organizacion academica institucional',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'SIGA: retiro automatico de permisos bloqueado.'
        );
    }
};