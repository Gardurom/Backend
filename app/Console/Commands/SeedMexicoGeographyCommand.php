<?php

namespace App\Console\Commands;

use App\Geography\MexicoGeographyLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SeedMexicoGeographyCommand extends Command
{
    protected $signature = 'siga:seed-mexico-geography
                            {--apply : Aplicar la carga geográfica de México}';

    protected $description =
        'Consulta o inicializa el catálogo de México y sus entidades federativas.';

    public function handle(): int
    {
        if ((bool) $this->option('apply')) {
            try {
                $connection = DB::connection();

                if ($connection->getDriverName() !== 'pgsql') {
                    throw new RuntimeException(
                        'La carga geográfica requiere PostgreSQL.'
                    );
                }

                $identity = $connection->selectOne(
                    'SELECT
                        session_user AS login_role,
                        current_user AS effective_role'
                );

                if (
                    $identity === null
                    || $identity->login_role !== 'siga_migrator'
                    || $identity->effective_role !== 'siga_migrator'
                ) {
                    throw new RuntimeException(
                        'La carga geográfica requiere la conexión siga_migrator.'
                    );
                }

                app(MexicoGeographyLoader::class)->execute();

                $this->info(
                    'La carga geográfica de México finalizó correctamente.'
                );

                return self::SUCCESS;
            } catch (Throwable $error) {
                $this->error($error->getMessage());

                return self::FAILURE;
            }
        }

        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'pgsql') {
                throw new RuntimeException(
                    'La consulta geográfica requiere PostgreSQL.'
                );
            }

            $countries = $connection->selectOne(
                'SELECT COUNT(*) AS total FROM institutional.countries'
            );

            $territories = $connection->selectOne(
                'SELECT COUNT(*) AS total FROM institutional.territories'
            );

            if (
                $countries === null
                || $territories === null
            ) {
                throw new RuntimeException(
                    'No fue posible consultar los catálogos geográficos.'
                );
            }

            $this->info('Carga geográfica de México');
            $this->line('Modo de consulta: no se modificarán registros.');
            $this->line('Países: ' . $countries->total);
            $this->line('Territorios: ' . $territories->total);

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error(
                'No fue posible consultar el estado geográfico: '
                . $error->getMessage()
            );

            return self::FAILURE;
        }
    }
}