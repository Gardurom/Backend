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
            Schema::create('institutional.territories', function (Blueprint $table) {
                $table->uuid('id_territorio')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->uuid('id_pais');
                $table->uuid('id_territorio_padre')->nullable();

                $table->string('tipo_territorio', 25);
                $table->string('clave_oficial', 20)->nullable();
                $table->string('nombre', 150);

                $table->boolean('activo')->default(true);

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->unique(
                    ['id_territorio', 'id_pais'],
                    'territories_id_pais_unique'
                );

                $table->index('id_pais', 'territories_pais_index');

                $table->index(
                    ['id_territorio_padre', 'id_pais'],
                    'territories_padre_pais_index'
                );

                $table->foreign('id_pais', 'territories_pais_fk')
                    ->references('id_pais')
                    ->on('institutional.countries')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreign(
                    ['id_territorio_padre', 'id_pais'],
                    'territories_padre_pais_fk'
                )
                    ->references(['id_territorio', 'id_pais'])
                    ->on('institutional.territories')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();
            });

            DB::statement(<<<'SQL'
                ALTER TABLE institutional.territories
                    ADD CONSTRAINT territories_padre_distinto_check
                        CHECK (
                            id_territorio_padre IS NULL
                            OR id_territorio_padre <> id_territorio
                        ),
                    ADD CONSTRAINT territories_tipo_check
                        CHECK (
                            tipo_territorio ~ '[^[:space:]]'
                            AND tipo_territorio !~ '^[[:space:]]|[[:space:]]$'
                        ),
                    ADD CONSTRAINT territories_clave_oficial_check
                        CHECK (
                            clave_oficial IS NULL
                            OR (
                                clave_oficial ~ '[^[:space:]]'
                                AND clave_oficial !~ '^[[:space:]]|[[:space:]]$'
                            )
                        ),
                    ADD CONSTRAINT territories_nombre_check
                        CHECK (
                            nombre ~ '[^[:space:]]'
                            AND nombre !~ '^[[:space:]]|[[:space:]]$'
                        )
                SQL);

            DB::statement(
                'GRANT SELECT ON TABLE institutional.territories TO siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('institutional.territories');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};