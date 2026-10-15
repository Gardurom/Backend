<?php

namespace Tests\Functional\Auth;

use App\Actions\User\BootstrapAdmin;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\Functional\FunctionalTestCase;

class BootstrapAdminActionTest extends FunctionalTestCase
{
    public function test_crea_primer_administrador_sin_persona_asociada(): void
    {
        self::assertFalse(
            User::query()
                ->where('email', 'admin.inicial@siga.test')
                ->exists()
        );

        $user = $this->bootstrapAdmin()->execute(
            '  ADMINISTRADOR INICIAL  ',
            ' Admin.Inicial@SIGA.TEST ',
            'ClaveSegura123!'
        );

        self::assertNull(
            $user->id_persona
        );

        self::assertSame(
            'ADMINISTRADOR INICIAL',
            $user->name
        );

        self::assertSame(
            'admin.inicial@siga.test',
            $user->email
        );

        self::assertNotSame(
            'ClaveSegura123!',
            $user->password
        );

        self::assertTrue(
            Hash::check(
                'ClaveSegura123!',
                $user->password
            )
        );

        self::assertTrue(
            $user->roles()
                ->where(
                    'system.roles.code',
                    'ROL_ADMIN_SISTEMA'
                )
                ->exists()
        );

        self::assertTrue(
            User::query()
                ->where('email', 'admin.inicial@siga.test')
                ->exists()
        );
    }

    public function test_rechaza_bootstrap_si_ya_existe_un_usuario(): void
    {
        $this->expectException(
            LogicException::class
        );

        $this->bootstrapAdmin(
            usersExist: true
        )->execute(
            'OTRO ADMINISTRADOR',
            'otro.admin@siga.test',
            'OtraClaveSegura456!'
        );
    }

    public function test_revierte_usuario_si_falla_asignacion_de_rol(): void
    {
        self::assertFalse(
            User::query()
                ->where('email', 'rollback.admin@siga.test')
                ->exists()
        );

        $action = new class extends BootstrapAdmin
        {
            protected function usersExist(): bool
            {
                return false;
            }

            protected function assignAdminRole(
                User $user,
                Role $role
            ): void {
                throw new RuntimeException(
                    'Fallo simulado al asignar el rol.'
                );
            }
        };

        try {
            $action->execute(
                'ADMINISTRADOR ROLLBACK',
                'rollback.admin@siga.test',
                'ClaveSegura123!'
            );
        } catch (RuntimeException $error) {
            self::assertSame(
                'Fallo simulado al asignar el rol.',
                $error->getMessage()
            );

            self::assertFalse(
                User::query()
                    ->where(
                        'email',
                        'rollback.admin@siga.test'
                    )
                    ->exists(),
                'El usuario debe revertirse si falla la asignacion del rol.'
            );

            return;
        }

        self::fail(
            'El fallo de asignacion de rol debe propagarse.'
        );
    }

    public function test_rechaza_nombre_vacio(): void
    {
        try {
            $this->bootstrapAdmin()->execute(
                '   ',
                'admin.inicial@siga.test',
                'ClaveSegura123!'
            );
        } catch (ValidationException $error) {
            self::assertArrayHasKey(
                'name',
                $error->errors()
            );

            self::assertFalse(
                User::query()
                    ->where('email', 'admin.inicial@siga.test')
                    ->exists()
            );

            return;
        }

        self::fail(
            'El bootstrap debe rechazar un nombre vacio.'
        );
    }

    public function test_rechaza_correo_invalido(): void
    {
        try {
            $this->bootstrapAdmin()->execute(
                'ADMINISTRADOR INICIAL',
                'correo-invalido',
                'ClaveSegura123!'
            );
        } catch (ValidationException $error) {
            self::assertArrayHasKey(
                'email',
                $error->errors()
            );

            self::assertFalse(
                User::query()
                    ->where('email', 'correo-invalido')
                    ->exists()
            );

            return;
        }

        self::fail(
            'El bootstrap debe rechazar un correo invalido.'
        );
    }

    public function test_rechaza_contrasena_menor_de_quince_caracteres(): void
    {
        try {
            $this->bootstrapAdmin()->execute(
                'ADMINISTRADOR INICIAL',
                'admin.inicial@siga.test',
                'ClaveSegura123'
            );
        } catch (ValidationException $error) {
            self::assertArrayHasKey(
                'password',
                $error->errors()
            );

            self::assertFalse(
                User::query()
                    ->where('email', 'admin.inicial@siga.test')
                    ->exists()
            );

            return;
        }

        self::fail(
            'El bootstrap debe exigir al menos 15 caracteres.'
        );
    }

    private function bootstrapAdmin(
        bool $usersExist = false
    ): BootstrapAdmin {
        return new class($usersExist) extends BootstrapAdmin
        {
            public function __construct(
                private readonly bool $usersExist
            ) {
            }

            protected function usersExist(): bool
            {
                return $this->usersExist;
            }
        };
    }
}
