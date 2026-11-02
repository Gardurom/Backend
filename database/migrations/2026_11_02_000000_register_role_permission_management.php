<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $code = 'roles.administrar_permisos';

        if (DB::table('system.permissions')->where('code', $code)->exists()) {
            return;
        }

        DB::table('system.permissions')->insert([
            'code' => $code,
            'name' => 'Administrar reglas de permisos de roles',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Su retiro requiere una operación institucional autorizada.
    }
};
