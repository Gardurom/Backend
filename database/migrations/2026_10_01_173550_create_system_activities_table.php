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
            Schema::create('system.activities', function (Blueprint $table) {
                $table->bigIncrements('id_actividad');

                $table->unsignedBigInteger('id_usuario');

                $table->string('entidad', 50);
                $table->string('id_entidad', 64);
                $table->string('accion', 20);

                $table->jsonb('datos_anteriores')->nullable();
                $table->jsonb('datos_nuevos')->nullable();
                $table->jsonb('campos_modificados')->nullable();

                $table->timestampTz('fecha_actividad')->useCurrent();

                $table->index(
                    'id_usuario',
                    'activities_usuario_index'
                );

                $table->index(
                    ['entidad', 'id_entidad'],
                    'activities_entidad_index'
                );

                $table->index(
                    'fecha_actividad',
                    'activities_fecha_index'
                );

                $table->foreign(
                    'id_usuario',
                    'activities_usuario_fk'
                )
                    ->references('id')
                    ->on('system.users')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();
            });

            DB::statement(<<<'SQL'
                ALTER TABLE system.activities
                    ADD CONSTRAINT activities_accion_check
                        CHECK (
                            accion IN (
                                'CREACION',
                                'ACTUALIZACION',
                                'BAJA',
                                'REINGRESO'
                            )
                        )
                SQL);

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON TABLE system.activities FROM PUBLIC'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON TABLE system.activities FROM siga_app'
            );

            DB::statement(
                'GRANT SELECT, INSERT
                 ON TABLE system.activities TO siga_app'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON SEQUENCE system.activities_id_actividad_seq
                 FROM PUBLIC'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON SEQUENCE system.activities_id_actividad_seq
                 FROM siga_app'
            );

            DB::statement(
                'GRANT USAGE
                 ON SEQUENCE system.activities_id_actividad_seq
                 TO siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('system.activities');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
