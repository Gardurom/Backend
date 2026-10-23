<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class PrepararUsuariosPrueba
{

    public function ejecutar(string $password): array
    {
        $this->verificarAmbiente();

        if (mb_strlen($password) < 15) {
            throw new LogicException(
                'La contraseña debe tener al menos 15 caracteres.'
            );
        }

        return DB::transaction(function () use ($password): array {
            $emails = CatalogoUsuariosPrueba::correos();

            if (
                User::query()
                    ->whereIn('email', $emails)
                    ->exists()
            ) {
                throw new LogicException(
                    'Ya existe al menos una cuenta del catálogo.'
                );
            }

            $codigos = CatalogoUsuariosPrueba::ROLES_REQUERIDOS;

            $roles = Role::query()
                ->whereIn('code', $codigos)
                ->get()
                ->keyBy('code');

            if ($roles->count() !== count($codigos)) {
                throw new LogicException(
                    'No está completo el catálogo de roles.'
                );
            }

            $resultado = [];

            foreach (CatalogoUsuariosPrueba::CUENTAS as $email => $datos) {
                $usuario = User::query()->create([
                    'id_persona' => null,
                    'name' => $datos['nombre'],
                    'email' => $email,
                    'password' => $password,
                ]);

                foreach ($datos['roles'] as $codigo) {
                    $usuario->roles()->attach(
                        $roles->get($codigo)->getKey()
                    );
                }

                $resultado[] = [
                    'id' => $usuario->getKey(),
                    'email' => $email,
                    'roles' => $datos['roles'],
                ];
            }

            return $resultado;
        });
    }

    private function verificarAmbiente(): void
    {
        if (getenv('SIGA_PERMITIR_USUARIOS_PRUEBA') !== '1') {
            throw new LogicException(
                'La preparación de usuarios de prueba no está habilitada.'
            );
        }

        if (
            ! app()->environment('local')
            || config('database.default') !== 'pgsql'
        ) {
            throw new LogicException(
                'La preparación solo está autorizada en desarrollo local.'
            );
        }

        $identidad = DB::selectOne(
            'SELECT current_database() AS base,
                    current_user AS usuario,
                    inet_server_addr()::text AS servidor'
        );

        if (
            $identidad->base !== 'siga'
            || $identidad->usuario !== 'siga_app'
            || ! in_array(
                $identidad->servidor,
                ['127.0.0.1/32', '127.0.0.1', '::1/128', '::1'],
                true
            )
        ) {
            throw new LogicException(
                'El destino PostgreSQL no está autorizado.'
            );
        }
    }
}
