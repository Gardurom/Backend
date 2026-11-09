<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SIGA: exigir la transaccion administrada por Laravel.
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException(
                'SIGA: la migracion requiere una transaccion activa.'
            );
        }

        // SIGA: comprobar identidad real de la sesion.
        $identity = DB::selectOne(
            'SELECT current_database() AS database_name, session_user AS session_role'
        );

        if (
            $identity === null ||
            $identity->database_name !== 'siga' ||
            $identity->session_role !== 'siga_migrator'
        ) {
            throw new \RuntimeException(
                'SIGA: migracion no autorizada para esta base o rol.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            Schema::create('institutional.academic_programs', function (Blueprint $table) {
                $table->uuid('id_programa')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->string('codigo', 30)->unique();
                $table->string('nombre', 200);

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            Schema::create('institutional.academic_cohorts', function (Blueprint $table) {
                $table->uuid('id_cohorte')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->uuid('id_programa');
                $table->string('tipo', 1);
                $table->integer('numero');
                $table->integer('anio');

                $table->foreign('id_programa')
                    ->references('id_programa')
                    ->on('institutional.academic_programs')
                    ->restrictOnUpdate()
                    ->restrictOnDelete();

                $table->unique(
                    ['id_programa', 'tipo', 'numero', 'anio'],
                    'academic_cohorts_identity_unique'
                );

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            DB::statement("
                ALTER TABLE institutional.academic_programs
                ADD CONSTRAINT academic_programs_code_check
                CHECK (codigo ~ '^[A-Z0-9]+$')
            ");

            DB::statement("
                ALTER TABLE institutional.academic_programs
                ADD CONSTRAINT academic_programs_name_check
                CHECK (length(btrim(nombre)) > 0)
            ");

            DB::statement("
                ALTER TABLE institutional.academic_cohorts
                ADD CONSTRAINT academic_cohorts_type_check
                CHECK (tipo IN ('G', 'E'))
            ");

            DB::statement("
                ALTER TABLE institutional.academic_cohorts
                ADD CONSTRAINT academic_cohorts_number_check
                CHECK (numero > 0)
            ");

            DB::statement("
                ALTER TABLE institutional.academic_cohorts
                ADD CONSTRAINT academic_cohorts_year_check
                CHECK (anio BETWEEN 1900 AND 9999)
            ");

            foreach ([
                'institutional.academic_programs',
                'institutional.academic_cohorts',
            ] as $table) {
                DB::statement("REVOKE ALL ON TABLE {$table} FROM PUBLIC");
                DB::statement("REVOKE ALL ON TABLE {$table} FROM siga_app");
                DB::statement("GRANT SELECT ON TABLE {$table} TO siga_app");
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'SIGA: rollback automatico bloqueado para programas y cohortes academicas. Requiere procedimiento institucional autorizado.'
        );
    }
};