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
            Schema::create('institutional.persons', function (Blueprint $table) {
                $table->uuid('id_persona')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->char('curp', 18)->nullable()->unique();
                $table->char('rfc', 13)->nullable()->unique();

                $table->string('num_expediente', 13)
                    ->default(DB::raw('system.next_expediente_number()'))
                    ->unique();

                $table->string('nombres', 150);
                $table->string('apellido_paterno', 100)->nullable();
                $table->string('apellido_materno', 100)->nullable();

                $table->date('fecha_nacimiento')->nullable();
                $table->string('sexo', 10)->nullable();
                $table->string('estado_civil', 7)->nullable();

                $table->string('correo_institucional', 254)->nullable();
                $table->string('correo_personal', 254)->nullable();

                $table->uuid('id_pais_origen')->nullable();
                $table->uuid('id_pais_nacimiento')->nullable();
                $table->uuid('id_territorio_nacimiento')->nullable();

                $table->timestampTz('fecha_alta')->useCurrent();
                $table->timestampTz('fecha_baja')->nullable();
                $table->string('estatus', 10)->default('ACTIVO');

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(
                    'id_pais_origen',
                    'persons_pais_origen_index'
                );

                $table->index(
                    'id_pais_nacimiento',
                    'persons_pais_nacimiento_index'
                );

                $table->index(
                    ['id_territorio_nacimiento', 'id_pais_nacimiento'],
                    'persons_territorio_pais_index'
                );

                $table->foreign('id_pais_origen', 'persons_pais_origen_fk')
                    ->references('id_pais')
                    ->on('institutional.countries')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreign(
                    'id_pais_nacimiento',
                    'persons_pais_nacimiento_fk'
                )
                    ->references('id_pais')
                    ->on('institutional.countries')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreign(
                    ['id_territorio_nacimiento', 'id_pais_nacimiento'],
                    'persons_territorio_pais_fk'
                )
                    ->references(['id_territorio', 'id_pais'])
                    ->on('institutional.territories')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();
            });

            DB::statement(<<<'SQL'
                ALTER TABLE institutional.persons
                    ADD CONSTRAINT persons_curp_check
                        CHECK (
                            curp IS NULL
                            OR (
                                char_length(curp::TEXT) = 18
                                AND curp::TEXT = upper(curp::TEXT)
                                AND curp::TEXT !~ '[[:space:]]'
                            )
                        ),
                    ADD CONSTRAINT persons_rfc_check
                        CHECK (
                            rfc IS NULL
                            OR (
                                char_length(rfc::TEXT) = 13
                                AND rfc::TEXT = upper(rfc::TEXT)
                                AND rfc::TEXT !~ '[[:space:]]'
                            )
                        ),
                    ADD CONSTRAINT persons_expediente_check
                        CHECK (
                            num_expediente ~ '^[0-9]{4}-[0-9]{8}$'
                        ),
                    ADD CONSTRAINT persons_nombres_check
                        CHECK (
                            nombres ~ '[^[:space:]]'
                            AND nombres !~ '^[[:space:]]|[[:space:]]$'
                        ),
                    ADD CONSTRAINT persons_apellido_paterno_check
                        CHECK (
                            apellido_paterno IS NULL
                            OR (
                                apellido_paterno ~ '[^[:space:]]'
                                AND apellido_paterno !~ '^[[:space:]]|[[:space:]]$'
                            )
                        ),
                    ADD CONSTRAINT persons_apellido_materno_check
                        CHECK (
                            apellido_materno IS NULL
                            OR (
                                apellido_materno ~ '[^[:space:]]'
                                AND apellido_materno !~ '^[[:space:]]|[[:space:]]$'
                            )
                        ),
                    ADD CONSTRAINT persons_fecha_nacimiento_check
                        CHECK (
                            fecha_nacimiento IS NULL
                            OR fecha_nacimiento <= (
                                transaction_timestamp()
                                AT TIME ZONE 'America/Mexico_City'
                            )::DATE
                        ),
                    ADD CONSTRAINT persons_sexo_check
                        CHECK (sexo IN ('MASCULINO', 'FEMENINO')),
                    ADD CONSTRAINT persons_estado_civil_check
                        CHECK (estado_civil IN ('SOLTERO', 'CASADO')),
                    ADD CONSTRAINT persons_correo_institucional_check
                        CHECK (
                            correo_institucional IS NULL
                            OR (
                                correo_institucional <> ''
                                AND correo_institucional = lower(correo_institucional)
                                AND correo_institucional !~ '[[:space:]]'
                            )
                        ),
                    ADD CONSTRAINT persons_correo_personal_check
                        CHECK (
                            correo_personal IS NULL
                            OR (
                                correo_personal <> ''
                                AND correo_personal = lower(correo_personal)
                                AND correo_personal !~ '[[:space:]]'
                            )
                        ),
                    ADD CONSTRAINT persons_territorio_requiere_pais_check
                        CHECK (
                            id_territorio_nacimiento IS NULL
                            OR id_pais_nacimiento IS NOT NULL
                        ),
                    ADD CONSTRAINT persons_estatus_check
                        CHECK (
                            estatus IN (
                                'ACTIVO', 'INACTIVO', 'SUSPENDIDO', 'BAJA'
                            )
                        ),
                    ADD CONSTRAINT persons_baja_estatus_check
                        CHECK (
                            (estatus = 'BAJA') = (fecha_baja IS NOT NULL)
                        ),
                    ADD CONSTRAINT persons_fecha_baja_check
                        CHECK (
                            fecha_baja IS NULL
                            OR fecha_baja >= fecha_alta
                        )
                SQL);

            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX persons_correo_institucional_unique
                ON institutional.persons (lower(correo_institucional))
                SQL);

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON TABLE institutional.persons FROM PUBLIC'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON TABLE institutional.persons FROM siga_app'
            );

            DB::statement(
                'GRANT SELECT, INSERT, UPDATE
                 ON TABLE institutional.persons TO siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('institutional.persons');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};