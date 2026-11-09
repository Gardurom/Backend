<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException(
                'SIGA: se requiere una transaccion activa.'
            );
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS database_name, session_user AS session_role'
        );

        if (
            $identity === null ||
            $identity->database_name !== 'siga' ||
            $identity->session_role !== 'siga_migrator'
        ) {
            throw new \RuntimeException(
                'SIGA: base o rol de migracion no autorizado.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            Schema::create('institutional.academic_sites', function (Blueprint $table) {
                $table->uuid('id_sede')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->string('codigo', 30)->unique();
                $table->string('nombre', 200);
                $table->boolean('activa')->default(true);

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            Schema::create('institutional.academic_groups', function (Blueprint $table) {
                $table->uuid('id_grupo')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->uuid('id_cohorte');
                $table->string('codigo', 30);
                $table->string('especializacion', 10)->nullable();

                $table->foreign('id_cohorte')
                    ->references('id_cohorte')
                    ->on('institutional.academic_cohorts')
                    ->restrictOnUpdate()
                    ->restrictOnDelete();

                $table->unique(
                    ['id_cohorte', 'codigo'],
                    'academic_groups_cohort_code_unique'
                );

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            DB::statement("
                ALTER TABLE institutional.academic_sites
                ADD CONSTRAINT academic_sites_code_check
                CHECK (codigo ~ '^[A-Z0-9]+$')
            ");

            DB::statement("
                ALTER TABLE institutional.academic_sites
                ADD CONSTRAINT academic_sites_name_check
                CHECK (length(btrim(nombre)) > 0)
            ");

            DB::statement("
                ALTER TABLE institutional.academic_groups
                ADD CONSTRAINT academic_groups_code_check
                CHECK (codigo ~ '^[A-Z0-9]+$')
            ");

            DB::statement("
                ALTER TABLE institutional.academic_groups
                ADD CONSTRAINT academic_groups_specialization_check
                CHECK (
                    especializacion IS NULL
                    OR especializacion IN ('CAMPO', 'GABINETE')
                )
            ");

            foreach ([
                'institutional.academic_sites',
                'institutional.academic_groups',
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
            'SIGA: rollback automatico bloqueado para sedes y grupos.'
        );
    }
};