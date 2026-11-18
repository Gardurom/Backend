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
            Schema::create('institutional.credentials', function (Blueprint $table) {
                $table->uuid('id_credencial')->primary();
                $table->uuid('id_persona');

                $table->string('folio', 40)->unique();
                $table->uuid('uid_qr')->unique();

                $table->string('categoria', 40);
                $table->string('estado', 20);

                $table->timestampTz('vigente_desde')->nullable();
                $table->timestampTz('vigente_hasta')->nullable();
                $table->timestampTz('revocada_en')->nullable();

                $table->timestampsTz();

                $table->foreign('id_persona', 'credentials_person_fk')
                    ->references('id_persona')
                    ->on('institutional.persons')
                    ->restrictOnDelete();

                $table->index('id_persona');
                $table->index('estado');
            });

            DB::statement("
                CREATE UNIQUE INDEX credentials_one_current_per_person
                ON institutional.credentials (id_persona)
                WHERE estado = 'VIGENTE'
            ");

            DB::statement("
                GRANT SELECT, INSERT
                ON TABLE institutional.credentials
                TO siga_app
            ");

            DB::statement("
                GRANT UPDATE (estado, revocada_en, updated_at)
                ON TABLE institutional.credentials
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
            Schema::dropIfExists('institutional.credentials');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};