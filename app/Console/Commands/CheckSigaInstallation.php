<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckSigaInstallation extends Command
{
    protected $signature = 'siga:check-installation';

    protected $description =
        'Comprueba la existencia de la base, tablas e historial de SIGA.';

    private const READY = 0;
    private const INCOMPLETE = 1;
    private const UNDETERMINED = 2;

    private const EXPECTED_TABLES = [
        'system.migrations',
        'system.users',
        'system.password_reset_tokens',
        'system.sessions',
        'system.cache',
        'system.cache_locks',
        'system.jobs',
        'system.job_batches',
        'system.failed_jobs',
        'system.counters',
        'institutional.countries',
        'institutional.territories',
        'institutional.persons',
    ];

    public function handle(): int
    {
        $connection = null;

        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'pgsql') {
                $this->error('CONFIGURACION_INVALIDA: se requiere PostgreSQL.');

                return self::UNDETERMINED;
            }

            if (config('database.migrations.table') !== 'system.migrations') {
                $this->error(
                    'CONFIGURACION_INVALIDA: el historial debe usar system.migrations.'
                );

                return self::UNDETERMINED;
            }

            try {
                $connection->getPdo();
            } catch (Throwable $error) {
                return $this->diagnoseConnectionFailure($connection, $error);
            }

            if ($connection->transactionLevel() !== 0) {
                $this->error(
                    'ESTADO_INDETERMINADO: la conexion tiene una transaccion abierta.'
                );

                return self::UNDETERMINED;
            }

            $connection->beginTransaction();

            try {
                $connection->statement(
                    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
                );

                return $this->inspectInstallation($connection);
            } finally {
                $connection->rollBack();
            }
        } catch (Throwable $error) {
            $this->error(
                'ESTADO_INDETERMINADO: no se pudo completar la comprobacion.'
            );

            $this->line(
                'Revise la conexion, los permisos y la configuracion del entorno.'
            );

            $this->line('Codigo de error: ' . (string) $error->getCode());

            return self::UNDETERMINED;
        } finally {
            if ($connection !== null) {
                $connection->disconnect();
            }
        }
    }

    private function inspectInstallation(Connection $connection): int
    {
        $identity = $connection->selectOne(
            'SELECT current_database() AS database_name,
                    current_user AS role_name'
        );

        $this->info('Comprobacion de instalacion SIGA');
        $this->line('Base: ' . $identity->database_name);
        $this->line('Rol: ' . $identity->role_name);

        $rows = $connection->select(
            "SELECT n.nspname || '.' || c.relname AS table_name
             FROM pg_catalog.pg_class AS c
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = c.relnamespace
             WHERE n.nspname IN ('system', 'institutional')
               AND c.relkind IN ('r', 'p')
             ORDER BY n.nspname, c.relname"
        );

        $tables = array_map(
            static fn (object $row): string => $row->table_name,
            $rows
        );

        if ($tables === []) {
            $this->warn('BASE_SIN_TABLAS_SIGA');
            $this->line(
                'Falta preparar los esquemas y aplicar las migraciones.'
            );

            return self::INCOMPLETE;
        }

        if (!in_array('system.migrations', $tables, true)) {
            $this->warn('TABLAS_SIN_HISTORIAL');
            $this->line(
                'Existen tablas, pero falta system.migrations. Se requiere revision.'
            );

            return self::INCOMPLETE;
        }

        $access = $connection->selectOne(
            "SELECT
                has_schema_privilege(current_user, 'system', 'USAGE')
                AND has_table_privilege(
                    current_user, 'system.migrations', 'SELECT'
                ) AS can_read_history"
        );

        if (!$access->can_read_history) {
            $this->error(
                'ESTADO_INDETERMINADO: el rol no puede consultar las migraciones.'
            );

            $this->line(
                'Ejecute esta comprobacion con el entorno de migracion.'
            );

            return self::UNDETERMINED;
        }

        $files = glob(database_path('migrations/*.php'));

        if ($files === false || $files === []) {
            $this->error(
                'ESTADO_INDETERMINADO: no se encontraron archivos de migracion.'
            );

            return self::UNDETERMINED;
        }

        $expectedMigrations = array_map(
            static fn (string $file): string => pathinfo(
                $file,
                PATHINFO_FILENAME
            ),
            $files
        );

        sort($expectedMigrations);

        $appliedMigrations = $connection
            ->table('system.migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->all();

        $pending = array_values(
            array_diff($expectedMigrations, $appliedMigrations)
        );

        $unknown = array_values(
            array_diff($appliedMigrations, $expectedMigrations)
        );

        $duplicates = array_keys(array_filter(
            array_count_values($appliedMigrations),
            static fn (int $count): bool => $count > 1
        ));

        $missingTables = array_values(
            array_diff(self::EXPECTED_TABLES, $tables)
        );

        $function = $connection->selectOne(
            "SELECT to_regprocedure(
                'system.next_expediente_number()'
             ) IS NOT NULL AS function_exists"
        );

        $this->line(
            'Migraciones en archivos: ' . count($expectedMigrations)
        );

        $this->line(
            'Registros en el historial: ' . count($appliedMigrations)
        );

        $this->showItems('Migraciones pendientes', $pending);
        $this->showItems('Migraciones sin archivo local', $unknown);
        $this->showItems('Migraciones duplicadas en el historial', $duplicates);
        $this->showItems('Tablas faltantes', $missingTables);

        if (!$function->function_exists) {
            $this->warn(
                'Falta la funcion system.next_expediente_number().'
            );
        }

        if (
            $pending !== []
            || $unknown !== []
            || $duplicates !== []
            || $missingTables !== []
            || !$function->function_exists
        ) {
            $this->warn('INSTALACION_INCOMPLETA_O_DIVERGENTE');

            return self::INCOMPLETE;
        }

        $this->info('MIGRACIONES_Y_OBJETOS_PRESENTES');
        $this->line(
            'Corresponde continuar con las pruebas de estructura y permisos.'
        );

        return self::READY;
    }

    private function diagnoseConnectionFailure(
        Connection $connection,
        Throwable $originalError
    ): int {
        $database = $connection->getConfig('database');

        if (!is_string($database) || $database === '') {
            $this->error(
                'ESTADO_INDETERMINADO: falta el nombre de la base de datos.'
            );

            return self::UNDETERMINED;
        }

        $maintenance = null;

        try {
            $configuration = $connection->getConfig();

            $configuration['database'] = 'postgres';
            $configuration['url'] = null;

            $maintenance = DB::build($configuration);
            $maintenance->beginTransaction();

            $maintenance->statement('SET TRANSACTION READ ONLY');

            $result = $maintenance->selectOne(
                'SELECT EXISTS (
                    SELECT 1
                    FROM pg_catalog.pg_database
                    WHERE datname = ?
                 ) AS database_exists',
                [$database]
            );

            if (!$result->database_exists) {
                $this->warn('BASE_INEXISTENTE: ' . $database);
                $this->line(
                    'Falta crear la base y preparar los roles y permisos.'
                );

                return self::INCOMPLETE;
            }

            $this->error('BASE_EXISTENTE_NO_ACCESIBLE: ' . $database);
            $this->line(
                'Revise los permisos de conexion y la configuracion del entorno.'
            );

            return self::UNDETERMINED;
        } catch (Throwable) {
            $this->error(
                'ESTADO_INDETERMINADO: no se pudo comprobar si la base existe.'
            );

            $this->line(
                'Revise el servicio PostgreSQL, la red, las credenciales y los permisos.'
            );

            $this->line(
                'Codigo de conexion original: '
                . (string) $originalError->getCode()
            );

            return self::UNDETERMINED;
        } finally {
            if ($maintenance !== null) {
                try {
                    if ($maintenance->transactionLevel() > 0) {
                        $maintenance->rollBack(0);
                    }
                } finally {
                    $maintenance->disconnect();
                }
            }
        }
    }

    private function showItems(string $label, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->warn($label . ':');

        foreach ($items as $item) {
            $this->line('  - ' . $item);
        }
    }
}