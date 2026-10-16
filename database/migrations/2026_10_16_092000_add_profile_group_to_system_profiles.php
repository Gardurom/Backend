<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::table('system.profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('profile_group_id')->nullable();
            });

            DB::statement(
                "UPDATE system.profiles AS p
                 SET profile_group_id = pg.id
                 FROM system.profile_groups AS pg
                 WHERE
                    (p.code = 'PERFIL_PERSONAS'
                     AND pg.code = 'GRUPO_OPERACION')
                    OR
                    (p.code = 'PERFIL_AUDITORIA'
                     AND pg.code = 'GRUPO_CONTROL_SUPERVISION')
                    OR
                    (p.code = 'PERFIL_ADMINISTRACION'
                     AND pg.code = 'GRUPO_ADMINISTRACION')"
            );

            $profilesWithoutGroup = DB::table('system.profiles')
                ->whereNull('profile_group_id')
                ->count();

            if ($profilesWithoutGroup > 0) {
                throw new RuntimeException(
                    'Todos los perfiles deben tener un grupo antes de establecer profile_group_id como obligatorio.'
                );
            }

            DB::statement(
                'ALTER TABLE system.profiles
                 ALTER COLUMN profile_group_id SET NOT NULL'
            );

            DB::statement(
                'ALTER TABLE system.profiles
                 ADD CONSTRAINT profiles_profile_group_id_foreign
                 FOREIGN KEY (profile_group_id)
                 REFERENCES system.profile_groups(id)
                 ON DELETE RESTRICT'
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
                'ALTER TABLE system.profiles
                 DROP CONSTRAINT IF EXISTS profiles_profile_group_id_foreign'
            );

            Schema::table('system.profiles', function (Blueprint $table) {
                $table->dropColumn('profile_group_id');
            });
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
