<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SIGA_PROFILE_CADETE_CATALOGO_V1
        // Solo registra catalogos. No asigna Usuarios, roles ni permisos.

        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'SIGA: se requiere transaccion institucional.'
            );
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS db, session_user AS actor'
        );

        if (
            $identity?->db !== 'siga' ||
            $identity?->actor !== 'siga_migrator'
        ) {
            throw new RuntimeException(
                'SIGA: se requiere el migrador institucional autorizado.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            if (
                DB::table('system.profile_groups')
                    ->where('code', 'GRUPO_ACADEMICO')
                    ->exists()
                ||
                DB::table('system.profiles')
                    ->where('code', 'PERFIL_CADETE')
                    ->exists()
            ) {
                throw new RuntimeException(
                    'SIGA: el catalogo academico requiere conciliacion.'
                );
            }

            $groupId = DB::table('system.profile_groups')
                ->insertGetId([
                    'name' => 'Academico',
                    'code' => 'GRUPO_ACADEMICO',
                    'description' => 'Perfiles del ambito academico',
                    'sort_order' => 15,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id');

            DB::table('system.profiles')->insert([
                'name' => 'Cadete',
                'code' => 'PERFIL_CADETE',
                'profile_group_id' => $groupId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $verified = DB::table('system.profiles')
                ->where('code', 'PERFIL_CADETE')
                ->where('profile_group_id', $groupId)
                ->count();

            if ($verified !== 1) {
                throw new RuntimeException(
                    'SIGA: el perfil de cadete no fue conciliado.'
                );
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'SIGA: el retiro del catalogo requiere autorizacion expresa.'
        );
    }
};