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
            Schema::create(
                'institutional.credential_events',
                function (Blueprint $table) {
                    $table->uuid('id_evento')->primary();

                    $table->uuid('id_credencial');
                    $table->unsignedBigInteger('id_usuario_actor');

                    $table->string('tipo_evento', 30);
                    $table->text('motivo')->nullable();

                    $table->timestampTz('ocurrido_en')
                        ->useCurrent();

                    $table->foreign(
                        'id_credencial',
                        'credential_events_credential_fk'
                    )
                        ->references('id_credencial')
                        ->on('institutional.credentials')
                        ->restrictOnDelete();

                    $table->foreign(
                        'id_usuario_actor',
                        'credential_events_actor_fk'
                    )
                        ->references('id')
                        ->on('system.users')
                        ->restrictOnDelete();

                    $table->index(
                        ['id_credencial', 'ocurrido_en'],
                        'credential_events_history_idx'
                    );

                    $table->index(
                        ['id_usuario_actor', 'ocurrido_en'],
                        'credential_events_actor_idx'
                    );
                }
            );

            DB::statement("
                ALTER TABLE institutional.credential_events
                ADD CONSTRAINT credential_events_type_check
                CHECK (
                    tipo_evento IN (
                        'EMISION',
                        'RATIFICACION',
                        'REPOSICION',
                        'REVOCACION'
                    )
                )
            ");

            DB::statement("
                GRANT SELECT, INSERT
                ON TABLE institutional.credential_events
                TO siga_app
            ");
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('institutional.credential_events');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};