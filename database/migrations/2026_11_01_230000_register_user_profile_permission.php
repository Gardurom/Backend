<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn('system.permissions', 'code')
            || ! Schema::hasColumn('system.permissions', 'name')
        ) {
            throw new RuntimeException(
                'La estructura del catalogo de permisos no es compatible.'
            );
        }

        $code = 'usuarios.asignar_perfiles';

        if (
            DB::table('system.permissions')
                ->where('code', $code)
                ->exists()
        ) {
            return;
        }

        DB::table('system.permissions')->insert([
            'code' => $code,
            'name' => 'Administrar perfiles de usuarios',
        ]);
    }

    public function down(): void
    {
        // No eliminar un permiso que pudiera estar en uso.
        // Su retiro requiere un procedimiento institucional.
    }
};
