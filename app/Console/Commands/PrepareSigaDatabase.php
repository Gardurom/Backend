<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PrepareSigaDatabase extends Command
{
    protected $signature = 'siga:prepare-database
                            {--apply : Aplicar la preparacion inicial}';

    protected $description =
        'Comprueba o prepara los esquemas y el historial inicial de SIGA.';

    private const SYSTEM_MIGRATION =
        '2026_09_23_172623_create_system_schema';

    private const INSTITUTIONAL_MIGRATION =
        '2026_09_23_160534_create_institutional_schema';

    public function handle(): int
    {
        $connection = null;

        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'pgsql') {
                throw new RuntimeException(
                    'La preparacion requiere PostgreSQL.'
                );
            }

            if (config('database.migrations.table') !== 'system.migrations') {
                throw new RuntimeException(
                    'El historial debe estar configurado como system.migrations.'
                );
            }

            if ($connection->transactionLevel() !== 0) {
                throw new RuntimeException(
                    'La conexion tiene una transaccion abierta.'
                );
            }

            $identity = $connection->selectOne(
                'SELECT current_database() AS database_name,
                        session_user AS login_role,
                        current_user AS effective_role'
            );

            if (
                $identity->login_role !== 'siga_migrator'
                || $identity->effective_role !== 'siga_migrator'
            ) {
                throw new RuntimeException(
                    'Ejecute el comando con la conexion de siga_migrator.'
                );
            }

            $this->info('Preparacion de base SIGA');
            $this->line('Base: ' . $identity->database_name);

            $repository = new DatabaseMigrationRepository(
                DB::getFacadeRoot(),
                'system.migrations'
            );

            $repository->setSource($connection->getName());

            $apply = (bool) $this->option('apply');

            $connection->beginTransaction();

            try {
                if ($apply) {
                    $connection->statement("SET LOCAL lock_timeout = '10s'");

                    $lock = $connection->selectOne(
                        'SELECT pg_try_advisory_xact_lock(73421, 1) AS acquired'
                    );

                    if (!$lock->acquired) {
                        throw new RuntimeException(
                            'Otra preparacion de SIGA esta en curso.'
                        );
                    }
                } else {
                    $connection->statement(
                        'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
                    );
                }

                $connection->statement('SET LOCAL ROLE siga_owner');
                $connection->statement('RESET ROLE');

                $state = $this->inspectState($connection, $repository);

                $this->line('Estado: ' . $state);

                if ($state === 'PREPARACION_EXISTENTE') {
                    $connection->rollBack();

                    $this->info(
                        'Los esquemas y su historial inicial ya estan preparados.'
                    );

                    return self::SUCCESS;
                }

                if (!$apply) {
                    $connection->rollBack();

                    $this->warn(
                        'Preparacion pendiente. La comprobacion no aplico cambios.'
                    );

                    return self::FAILURE;
                }

                if ($state === 'BASE_POR_PREPARAR') {
                    $this->runSchemaMigration(self::SYSTEM_MIGRATION);

                    $connection->statement('SET LOCAL ROLE siga_owner');

                    $repository->createRepository();

                    $connection->statement(
                        'REVOKE ALL PRIVILEGES
                         ON TABLE system.migrations FROM PUBLIC, siga_app'
                    );

                    $connection->statement(
                        'REVOKE ALL PRIVILEGES
                         ON SEQUENCE system.migrations_id_seq
                         FROM PUBLIC, siga_app'
                    );

                    $connection->statement(
                        'GRANT USAGE ON SCHEMA system TO siga_migrator'
                    );

                    $connection->statement(
                        'GRANT SELECT, INSERT, DELETE
                         ON TABLE system.migrations TO siga_migrator'
                    );

                    $connection->statement(
                        'GRANT USAGE
                         ON SEQUENCE system.migrations_id_seq
                         TO siga_migrator'
                    );

                    $repository->log(self::SYSTEM_MIGRATION, 1);

                    $connection->statement('RESET ROLE');
                }

                $batch = $repository->getNextBatchNumber();

                $this->runSchemaMigration(self::INSTITUTIONAL_MIGRATION);

                $repository->log(self::INSTITUTIONAL_MIGRATION, $batch);

                $connection->commit();

                $this->info(
                    'Preparacion aplicada: system, institutional e historial inicial.'
                );

                $this->line(
                    'El siguiente paso es revisar y ejecutar las migraciones pendientes.'
                );

                return self::SUCCESS;
            } catch (Throwable $error) {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack(0);
                }

                throw $error;
            }
        } catch (Throwable $error) {
            $this->error('Preparacion detenida: ' . $error->getMessage());

            return self::FAILURE;
        } finally {
            if ($connection !== null) {
                $connection->disconnect();
            }
        }
    }

    private function inspectState(
        Connection $connection,
        DatabaseMigrationRepository $repository
    ): string {
        $schemas = $connection->select(
            "SELECT nspname AS schema_name,
                    pg_get_userbyid(nspowner) AS owner_name
             FROM pg_catalog.pg_namespace
             WHERE nspname IN ('system', 'institutional')"
        );

        $owners = [];

        foreach ($schemas as $schema) {
            $owners[$schema->schema_name] = $schema->owner_name;

            if ($schema->owner_name !== 'siga_owner') {
                throw new RuntimeException(
                    "El esquema {$schema->schema_name} no pertenece a siga_owner."
                );
            }
        }

        if (!isset($owners['system'])) {
            if (isset($owners['institutional'])) {
                throw new RuntimeException(
                    'Existe institutional sin system. Se requiere revisar el estado previo.'
                );
            }

            $legacyTables = $connection->select(
                "SELECT c.relname AS table_name
                 FROM pg_catalog.pg_class AS c
                 JOIN pg_catalog.pg_namespace AS n
                   ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public'
                   AND c.relkind IN ('r', 'p')
                   AND c.relname IN (
                       'migrations', 'users', 'password_reset_tokens',
                       'sessions', 'cache', 'cache_locks', 'jobs',
                       'job_batches', 'failed_jobs', 'counters',
                       'countries', 'territories', 'persons'
                   )"
            );

            if ($legacyTables !== []) {
                throw new RuntimeException(
                    'Existen posibles tablas de SIGA en public. Se requiere revision.'
                );
            }

            $this->requireMigrationFile(self::SYSTEM_MIGRATION);
            $this->requireMigrationFile(self::INSTITUTIONAL_MIGRATION);

            return 'BASE_POR_PREPARAR';
        }

        if (!$repository->repositoryExists()) {
            throw new RuntimeException(
                'Existe system pero falta system.migrations. Se requiere revision.'
            );
        }

        $ran = $repository->getRan();

        if (count($ran) !== count(array_unique($ran))) {
            throw new RuntimeException(
                'El historial contiene migraciones duplicadas.'
            );
        }

        foreach ($ran as $migration) {
            if (!is_file(database_path('migrations/' . $migration . '.php'))) {
                throw new RuntimeException(
                    "Falta el archivo de la migracion registrada: {$migration}."
                );
            }
        }

        if (!in_array(self::SYSTEM_MIGRATION, $ran, true)) {
            throw new RuntimeException(
                'Existe system pero su migracion no esta registrada.'
            );
        }

        $institutionalRan = in_array(
            self::INSTITUTIONAL_MIGRATION,
            $ran,
            true
        );

        if (isset($owners['institutional'])) {
            if (!$institutionalRan) {
                throw new RuntimeException(
                    'Existe institutional pero su migracion no esta registrada.'
                );
            }

            return 'PREPARACION_EXISTENTE';
        }

        if ($institutionalRan) {
            throw new RuntimeException(
                'La migracion de institutional esta registrada, pero falta el esquema.'
            );
        }

        if (count($ran) !== 1) {
            throw new RuntimeException(
                'Falta institutional y existen otras migraciones registradas. Se requiere revision.'
            );
        }

        $this->requireMigrationFile(self::INSTITUTIONAL_MIGRATION);

        return 'FALTA_PREPARAR_INSTITUTIONAL';
    }

    private function requireMigrationFile(string $name): string
    {
        $path = database_path('migrations/' . $name . '.php');

        if (!is_file($path)) {
            throw new RuntimeException(
                "No se encontro el archivo de migracion: {$name}."
            );
        }

        return $path;
    }

    private function runSchemaMigration(string $name): void
    {
        $migration = require $this->requireMigrationFile($name);

        if (!$migration instanceof Migration) {
            throw new RuntimeException(
                "El archivo {$name} no devuelve una migracion Laravel."
            );
        }

        $migration->up();
    }
}