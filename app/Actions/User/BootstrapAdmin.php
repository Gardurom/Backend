<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;

class BootstrapAdmin
{
    public function execute(
        string $name,
        string $email,
        string $password
    ): User {
        $attributes = [
            'name' => trim($name),
            'email' => mb_strtolower(
                trim($email)
            ),
            'password' => $password,
        ];

        return DB::connection()->transaction(
            function () use ($attributes): User {
                if (User::query()->exists()) {
                    throw new LogicException(
                        'El bootstrap administrativo solo puede ejecutarse cuando no existen usuarios.'
                    );
                }

                $validated = Validator::make(
                    $attributes,
                    [
                        'name' => [
                            'required',
                            'string',
                        ],
                        'email' => [
                            'required',
                            'email',
                        ],
                        'password' => [
                            'required',
                            'string',
                            'min:15',
                        ],
                    ],
                    [
                        'name.required' =>
                            'El nombre del administrador es obligatorio.',
                        'email.required' =>
                            'El correo del administrador es obligatorio.',
                        'email.email' =>
                            'El correo del administrador no es válido.',
                        'password.required' =>
                            'La contraseña del administrador es obligatoria.',
                        'password.min' =>
                            'La contraseña debe tener al menos 15 caracteres.',
                    ]
                )->validate();

                $role = Role::query()
                    ->where(
                        'code',
                        'ROL_ADMIN_SISTEMA'
                    )
                    ->first();

                if ($role === null) {
                    throw new LogicException(
                        'No existe el rol ROL_ADMIN_SISTEMA.'
                    );
                }

                $user = User::query()->create([
                    'id_persona' => null,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                ]);

                $this->assignAdminRole(
                    $user,
                    $role
                );

                return $user;
            }
        );
    }

    protected function assignAdminRole(
        User $user,
        Role $role
    ): void {
        $user->roles()->attach(
            $role->getKey()
        );
    }
}
