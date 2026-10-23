<?php

namespace App\Console\Commands;

use App\Actions\User\CatalogoUsuariosPrueba;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class VerificarUsuariosPrueba extends Command
{
    protected $signature = 'siga:usuarios-prueba
                            {--verificar : Verificar el catálogo de desarrollo}';

    protected $description =
        'Verifica las condiciones para usuarios de prueba de SIGA.';

    public function handle(): int
    {
        if (! $this->option('verificar')) {
            $this->error('Utilice exclusivamente --verificar.');

            return self::FAILURE;
        }

        if (
            ! app()->environment('local')
            || config('database.default') !== 'pgsql'
        ) {
            $this->error('Ambiente o conexión no autorizados.');

            return self::FAILURE;
        }

        try {
            $db = DB::connection();

            $identity = $db->selectOne(
                "SELECT
                    current_database() AS database_name,
                    current_user AS database_user,
                    inet_server_addr()::text AS server_address"
            );

            if (
                $identity->database_name !== 'siga'
                || $identity->database_user !== 'siga_app'
                || ! in_array(
                    $identity->server_address,
                    ['127.0.0.1/32', '127.0.0.1', '::1/128', '::1'],
                    true
                )
            ) {
                $this->error('Destino PostgreSQL no autorizado.');

                return self::FAILURE;
            }

            $catalogo = CatalogoUsuariosPrueba::CUENTAS;

            $rolesEsperados = CatalogoUsuariosPrueba::ROLES_REQUERIDOS;

            $rolesExistentes = $db->table('system.roles')
                ->whereIn('code', $rolesEsperados)
                ->pluck('code')
                ->all();

            $usuariosExistentes = $db->table('system.users')
                ->whereIn('email', array_keys($catalogo))
                ->pluck('email')
                ->all();

            $this->info('SIGA - Verificación de usuarios de prueba');
            $this->line('Ambiente: local');
            $this->line('Base de datos: siga');
            $this->line('Servidor: PostgreSQL local');
            $this->line(
                'Roles encontrados: ' .
                count($rolesExistentes) . '/4'
            );

            foreach ($catalogo as $email => $roles) {
                $estado = in_array(
                    $email,
                    $usuariosExistentes,
                    true
                ) ? 'YA EXISTE' : 'DISPONIBLE';

                $this->line($email . ': ' . $estado);
            }

            if (
                count($rolesExistentes) !== 4
                || count($usuariosExistentes) !== 0
            ) {
                $this->warn(
                    'El catálogo no está listo para creación.'
                );

                return self::FAILURE;
            }

            $this->info('Catálogo listo para preparación.');
            $this->info('No se modificaron registros.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error(
                'No fue posible completar la verificación.'
            );

            return self::FAILURE;
        }
    }
}
