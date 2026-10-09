<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\HttpFunctionalTestCase;

class BootstrapAdminCommandTest extends HttpFunctionalTestCase
{
    public function test_crea_administrador_inicial_desde_artisan(): void
    {
        self::assertSame(
            0,
            User::query()->count()
        );

        $this->artisan(
            'siga:bootstrap-admin',
            [
                '--name' => '  ADMINISTRADOR INICIAL  ',
                '--email' => ' Admin.Inicial@SIGA.TEST ',
            ]
        )
            ->expectsQuestion(
                'Contraseña del administrador',
                'ClaveSegura123!'
            )
            ->expectsQuestion(
                'Confirme la contraseña',
                'ClaveSegura123!'
            )
            ->expectsOutput(
                'Administrador inicial creado correctamente.'
            )
            ->assertExitCode(0);

        $user = User::query()
            ->where(
                'email',
                'admin.inicial@siga.test'
            )
            ->first();

        self::assertNotNull(
            $user
        );

        self::assertNull(
            $user->id_persona
        );

        self::assertSame(
            'ADMINISTRADOR INICIAL',
            $user->name
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
    }

    public function test_rechaza_contrasenas_diferentes(): void
    {
        self::assertSame(
            0,
            User::query()->count()
        );

        $this->artisan(
            'siga:bootstrap-admin',
            [
                '--name' => 'ADMINISTRADOR INICIAL',
                '--email' => 'admin.inicial@siga.test',
            ]
        )
            ->expectsQuestion(
                'Contraseña del administrador',
                'ClaveSegura123!'
            )
            ->expectsQuestion(
                'Confirme la contraseña',
                'ClaveDiferente456!'
            )
            ->expectsOutput(
                'Las contraseñas no coinciden.'
            )
            ->assertExitCode(1);

        self::assertSame(
            0,
            User::query()->count()
        );
    }

    public function test_rechaza_bootstrap_si_ya_existe_un_usuario(): void
    {
        User::query()->create([
            'id_persona' => null,
            'name' => 'USUARIO EXISTENTE',
            'email' => 'existente@siga.test',
            'password' => 'ClaveSegura123!',
        ]);

        self::assertSame(
            1,
            User::query()->count()
        );

        $this->artisan(
            'siga:bootstrap-admin',
            [
                '--name' => 'OTRO ADMINISTRADOR',
                '--email' => 'otro.admin@siga.test',
            ]
        )
            ->expectsQuestion(
                'Contraseña del administrador',
                'OtraClaveSegura456!'
            )
            ->expectsQuestion(
                'Confirme la contraseña',
                'OtraClaveSegura456!'
            )
            ->expectsOutput(
                'El bootstrap administrativo solo puede ejecutarse cuando no existen usuarios.'
            )
            ->assertExitCode(1);

        self::assertSame(
            1,
            User::query()->count()
        );

        self::assertFalse(
            User::query()
                ->where(
                    'email',
                    'otro.admin@siga.test'
                )
                ->exists()
        );
    }

    public function test_muestra_error_controlado_para_nombre_vacio(): void
    {
        $this->artisan(
            'siga:bootstrap-admin',
            [
                '--name' => '   ',
                '--email' => 'admin.inicial@siga.test',
            ]
        )
            ->expectsQuestion(
                'Contraseña del administrador',
                'ClaveSegura123!'
            )
            ->expectsQuestion(
                'Confirme la contraseña',
                'ClaveSegura123!'
            )
            ->expectsOutput(
                'El nombre del administrador es obligatorio.'
            )
            ->assertExitCode(1);

        self::assertSame(
            0,
            User::query()->count()
        );
    }

    public function test_muestra_error_controlado_para_correo_invalido(): void
    {
        $this->artisan(
            'siga:bootstrap-admin',
            [
                '--name' => 'ADMINISTRADOR INICIAL',
                '--email' => 'correo-invalido',
            ]
        )
            ->expectsQuestion(
                'Contraseña del administrador',
                'ClaveSegura123!'
            )
            ->expectsQuestion(
                'Confirme la contraseña',
                'ClaveSegura123!'
            )
            ->expectsOutput(
                'El correo del administrador no es válido.'
            )
            ->assertExitCode(1);

        self::assertSame(
            0,
            User::query()->count()
        );
    }

    public function test_muestra_error_controlado_para_contrasena_corta(): void
    {
        $this->artisan(
            'siga:bootstrap-admin',
            [
                '--name' => 'ADMINISTRADOR INICIAL',
                '--email' => 'admin.inicial@siga.test',
            ]
        )
            ->expectsQuestion(
                'Contraseña del administrador',
                'ClaveSegura123'
            )
            ->expectsQuestion(
                'Confirme la contraseña',
                'ClaveSegura123'
            )
            ->expectsOutput(
                'La contraseña debe tener al menos 15 caracteres.'
            )
            ->assertExitCode(1);

        self::assertSame(
            0,
            User::query()->count()
        );
    }
}
