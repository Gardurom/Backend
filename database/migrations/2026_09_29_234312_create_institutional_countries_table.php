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
            Schema::create('institutional.countries', function (Blueprint $table) {
                $table->uuid('id_pais')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->char('codigo_alpha2', 2)->unique();
                $table->char('codigo_alpha3', 3)->unique();
                $table->char('codigo_num3', 3)->unique();

                $table->string('nombre', 150);
                $table->string('nacionalidad_masculina', 100)->nullable();
                $table->string('nacionalidad_femenina', 100)->nullable();

                $table->boolean('activo')->default(true);

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            DB::statement(<<<'SQL'
                ALTER TABLE institutional.countries
                    ADD CONSTRAINT countries_codigo_alpha2_check
                        CHECK (codigo_alpha2 ~ '^[A-Z]{2}$'),
                    ADD CONSTRAINT countries_codigo_alpha3_check
                        CHECK (codigo_alpha3 ~ '^[A-Z]{3}$'),
                    ADD CONSTRAINT countries_codigo_num3_check
                        CHECK (codigo_num3 ~ '^[0-9]{3}$'),
                    ADD CONSTRAINT countries_nombre_check
                        CHECK (
                            nombre = btrim(nombre)
                            AND nombre ~ '[^[:space:]]'
                            AND nombre !~ '^[[:space:]]|[[:space:]]$'
                        ),
                    ADD CONSTRAINT countries_nacionalidad_masculina_check
                        CHECK (
                            nacionalidad_masculina IS NULL
                            OR (
                                nacionalidad_masculina = btrim(nacionalidad_masculina)
                                AND nacionalidad_masculina ~ '[^[:space:]]'
                                AND nacionalidad_masculina !~ '^[[:space:]]|[[:space:]]$'
                            )
                        ),
                    ADD CONSTRAINT countries_nacionalidad_femenina_check
                        CHECK (
                            nacionalidad_femenina IS NULL
                            OR (
                                nacionalidad_femenina = btrim(nacionalidad_femenina)
                                AND nacionalidad_femenina ~ '[^[:space:]]'
                                AND nacionalidad_femenina !~ '^[[:space:]]|[[:space:]]$'
                            )
                        )
                SQL);

            DB::statement(
                'GRANT USAGE ON SCHEMA institutional TO siga_app'
            );

            DB::statement(
                'GRANT SELECT ON TABLE institutional.countries TO siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('institutional.countries');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};