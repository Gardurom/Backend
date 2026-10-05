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
        string $password
    ): User {
        return DB::connection()->transaction(
            function () use (
                $personId,
                $email,
                $password
            ): User {
                $person = Person::query()
                    ->findOrFail($personId);

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

                return User::query()->create([
                    'id_persona' => $person->id_persona,
                    'name' => $name,
                    'email' => mb_strtolower(
                        trim($email)
                    ),
                    'password' => $password,
                ]);
            }
        );
    }
}