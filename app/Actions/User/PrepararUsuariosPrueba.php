<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class PrepararUsuariosPrueba
{
    private const CATALOGO = [
        'prueba.admin@ejemplo.test' => [
            'nombre' => 'PRUEBA ADMINISTRADOR',
            'roles' => ['ROL_ADMIN_SISTEMA'],
        ],
        'prueba.gestor@ejemplo.test' => [
            'nombre' => 'PRUEBA GESTOR PERSONAS',
            'roles' => ['ROL_GESTOR_PERSONAS'],
        ],
        'prueba.consulta@ejemplo.test' => [
            'nombre' => 'PRUEBA CONSULTA PERSONAS',
            'roles' => ['ROL_CONSULTA_PERSONAS'],
        ],
        'prueba.auditor@ejemplo.test' => [
            'nombre' => 'PRUEBA AUDITOR',
            'roles' => ['ROL_AUDITOR'],
        ],
        'prueba.sinrol@ejemplo.test' => [
            'nombre' => 'PRUEBA SIN ROL',
            'roles' => [],
        ],
        'prueba.multirrol@ejemplo.test' => [
            'nombre' => 'PRUEBA MULTIRROL',
            'roles' => [
                'ROL_CONSULTA_PERSONAS',
                'ROL_GESTOR_PERSONAS',
            ],
        ],
    ];

    public function ejecutar(string $password): array
    {
        $this->verificarAmbiente();

        if (mb_strlen($password) < 15) {
            throw new LogicException(
                'La contraseña debe tener al menos 15 caracteres.'
            );
        }

        return DB::transaction(function () use ($password): array {
            $emails = array_keys(self::CATALOGO);

            if (
                User::query()
                    ->whereIn('email', $emails)
                    ->exists()
            ) {
                throw new LogicException(
                    'Ya existe al menos una cuenta del catálogo.'
                );
            }

            $codigos = [
                'ROL_ADMIN_SISTEMA',
                'ROL_GESTOR_PERSONAS',
                'ROL_CONSULTA_PERSONAS',
                'ROL_AUDITOR',
            ];

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

            foreach (self::CATALOGO as $email => $datos) {
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
