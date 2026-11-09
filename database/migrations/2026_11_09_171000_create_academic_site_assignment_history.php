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
                'SIGA: la migracion requiere una transaccion activa.'
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
                'SIGA: identidad de migracion no autorizada.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            Schema::create(
                'institutional.academic_cohort_site_history',
                function (Blueprint $table) {
                    $table->uuid('id_movimiento')
                        ->default(DB::raw('uuidv7()'))
                        ->primary();

                    $table->uuid('id_cohorte');
                    $table->uuid('id_sede');

                    $table->timestampTz('vigente_desde');
                    $table->timestampTz('vigente_hasta')->nullable();

                    $table->timestampTz('created_at')->useCurrent();
                    $table->timestampTz('updated_at')->useCurrent();

                    $table->foreign('id_cohorte')
                        ->references('id_cohorte')
                        ->on('institutional.academic_cohorts')
                        ->restrictOnUpdate()
                        ->restrictOnDelete();

                    $table->foreign('id_sede')
                        ->references('id_sede')
                        ->on('institutional.academic_sites')
                        ->restrictOnUpdate()
                        ->restrictOnDelete();
                }
            );

            Schema::create(
                'institutional.academic_group_site_history',
                function (Blueprint $table) {
                    $table->uuid('id_movimiento')
                        ->default(DB::raw('uuidv7()'))
                        ->primary();

                    $table->uuid('id_grupo');
                    $table->uuid('id_sede');

                    $table->timestampTz('vigente_desde');
                    $table->timestampTz('vigente_hasta')->nullable();

                    $table->timestampTz('created_at')->useCurrent();
                    $table->timestampTz('updated_at')->useCurrent();

                    $table->foreign('id_grupo')
                        ->references('id_grupo')
                        ->on('institutional.academic_groups')
                        ->restrictOnUpdate()
                        ->restrictOnDelete();

                    $table->foreign('id_sede')
                        ->references('id_sede')
                        ->on('institutional.academic_sites')
                        ->restrictOnUpdate()
                        ->restrictOnDelete();
                }
            );

            DB::statement("
                ALTER TABLE institutional.academic_cohort_site_history
                ADD CONSTRAINT academic_cohort_site_dates_check
                CHECK (
                    vigente_hasta IS NULL
                    OR vigente_hasta > vigente_desde
                )
            ");

            DB::statement("
                ALTER TABLE institutional.academic_group_site_history
                ADD CONSTRAINT academic_group_site_dates_check
                CHECK (
                    vigente_hasta IS NULL
                    OR vigente_hasta > vigente_desde
                )
            ");

            DB::statement("
                CREATE UNIQUE INDEX academic_cohort_one_current_site
                ON institutional.academic_cohort_site_history (id_cohorte)
                WHERE vigente_hasta IS NULL
            ");

            DB::statement("
                CREATE UNIQUE INDEX academic_group_one_current_site
                ON institutional.academic_group_site_history (id_grupo)
                WHERE vigente_hasta IS NULL
            ");

            // SIGA: no instalar sin el operador GiST de igualdad UUID.
            // La extension se autoriza e instala por separado.
            $gistReady = DB::selectOne("
                SELECT EXISTS (
                    SELECT 1
                    FROM pg_opclass opc
                    JOIN pg_am am ON am.oid = opc.opcmethod
                    WHERE am.amname = 'gist'
                      AND opc.opcintype = 'uuid'::regtype
                ) AS ready
            ");

            if ($gistReady === null || ! $gistReady->ready) {
                throw new \RuntimeException(
                    'SIGA: requiere btree_gist antes de instalar el historial de sedes.'
                );
            }

            DB::statement("
                ALTER TABLE institutional.academic_cohort_site_history
                ADD CONSTRAINT academic_cohort_site_no_overlap
                EXCLUDE USING gist (
                    id_cohorte WITH =,
                    tstzrange(vigente_desde, vigente_hasta, '[)') WITH &&
                )
            ");

            DB::statement("
                ALTER TABLE institutional.academic_group_site_history
                ADD CONSTRAINT academic_group_site_no_overlap
                EXCLUDE USING gist (
                    id_grupo WITH =,
                    tstzrange(vigente_desde, vigente_hasta, '[)') WITH &&
                )
            ");
            foreach ([
                'institutional.academic_cohort_site_history',
                'institutional.academic_group_site_history',
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
            'SIGA: rollback automatico bloqueado para historial de sedes.'
        );
    }
};