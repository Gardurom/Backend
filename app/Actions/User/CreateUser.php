<?php

namespace App\Actions\User;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    public function execute(
        string $personId,
        string $email,
        string $password,
        ?int $actorId = null
    ): User {
        return DB::connection()->transaction(
            function () use (
                $personId,
                $email,
                $password,
                $actorId
            ): User {
                $person = Person::query()
                    ->findOrFail($personId);

                if ($person->estatus !== 'ACTIVO') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'id_persona' => 'La Persona debe estar ACTIVA para crear una cuenta.',
                    ]);
                }

                $name = collect([
                    $person->nombres,
                    $person->apellido_paterno,
                    $person->apellido_materno,
                ])
                    ->filter(
                        fn ($value) => $value !== null
                            && trim((string) $value) !== ''
                    )
                    ->map(
                        fn ($value) => trim((string) $value)
                    )
                    ->implode(' ');

                $user = User::query()->create([
                    'id_persona' => $person->id_persona,
                    'name' => $name,
                    'email' => mb_strtolower(
                        trim($email)
                    ),
                    'password' => $password,
                ]);
                $user->refresh();
                DB::table('system.activities')->insert([
                    'id_usuario' => $actorId ?? $user->getKey(),
                    'entidad' => 'USUARIO',
                    'id_entidad' => (string) $user->getKey(),
                    'accion' => 'CREACION',
                    'datos_nuevos' => json_encode([
                        'id_persona' => $user->id_persona,
                        'access_status' => $user->access_status,
                        'origen' => $actorId === null ? 'CONTROL_AUTOMATICO' : 'OPERADOR',
                    ], JSON_THROW_ON_ERROR),
                    'campos_modificados' => json_encode(['id_persona', 'access_status'], JSON_THROW_ON_ERROR),
                ]);
                return $user;
            }
        );
    }
}
