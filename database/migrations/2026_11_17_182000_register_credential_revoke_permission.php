<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::table('system.permissions')->insertOrIgnore([
                'code' => 'credenciales.revocar',
                'name' => 'Revocar credenciales institucionales',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('system.permissions')->insertOrIgnore([
                'name' => 'Emitir credenciales institucionales',
                'code' => 'credenciales.emitir',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('system.permissions')->insertOrIgnore([
                'name' => 'Validar credenciales institucionales',
                'code' => 'credenciales.validar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        // Conservar el permiso y sus posibles referencias
        // historicas. Su retiro requiere autorizacion
        // institucional y un procedimiento independiente.
    }
};