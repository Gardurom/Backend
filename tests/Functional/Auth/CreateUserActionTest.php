<?php

namespace Tests\Functional\Auth;

use App\Actions\User\CreateUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\FunctionalTestCase;

class CreateUserActionTest extends FunctionalTestCase
{
    public function test_crea_usuario_vinculado_a_persona_existente(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'MARIA',
                    'apellido_paterno' => 'LOPEZ',
                    'apellido_materno' => 'PEREZ',
                ],
                'id_persona'
            );

        $user = (new CreateUser)->execute(
            $personId,
            ' Maria.Login@SIGA.TEST ',
            'ClaveSegura123!'
        );

        self::assertSame(
            $personId,
            (string) $user->id_persona
        );

        self::assertSame(
            'MARIA LOPEZ PEREZ',
            $user->name
        );

        self::assertSame(
            'maria.login@siga.test',
            $user->email
        );

        self::assertTrue(
            Hash::check(
                'ClaveSegura123!',
                $user->password
            )
        );

        self::assertNotSame(
            'ClaveSegura123!',
            $user->password
        );
    }

    public function test_rechaza_persona_inexistente(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        (new CreateUser)->execute(
            '01900000-0000-7000-8000-000000000001',
            'missing.person@siga.test',
            'ClaveSegura123!'
        );
    }

    public function test_rechaza_segunda_cuenta_para_misma_persona(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA CUENTA UNICA',
                ],
                'id_persona'
            );

        $action = new CreateUser;

        $action->execute(
            $personId,
            'primera.cuenta@siga.test',
            'ClaveSegura123!'
        );

        try {
            $action->execute(
                $personId,
                'segunda.cuenta@siga.test',
                'OtraClaveSegura456!'
            );
        } catch (QueryException $error) {
            self::assertSame(
                '23505',
                $error->getCode()
            );

            return;
        }

        self::fail(
            'Una Persona no debe tener dos cuentas.'
        );
    }

    public function test_rechaza_correo_de_acceso_duplicado(): void
    {
        $firstPersonId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PRIMERA PERSONA',
                ],
                'id_persona'
            );

        $secondPersonId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'SEGUNDA PERSONA',
                ],
                'id_persona'
            );

        $action = new CreateUser;

        $action->execute(
            $firstPersonId,
            'cuenta.unica@siga.test',
            'ClaveSegura123!'
        );

        try {
            $action->execute(
                $secondPersonId,
                'CUENTA.UNICA@SIGA.TEST',
                'OtraClaveSegura456!'
            );
        } catch (QueryException $error) {
            self::assertSame(
                '23505',
                $error->getCode()
            );

            return;
        }

        self::fail(
            'El correo de acceso normalizado no debe repetirse.'
        );
    }

    public function test_usuario_creado_puede_autenticarse(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA LOGIN',
                ],
                'id_persona'
            );

        $user = (new CreateUser)->execute(
            $personId,
            'login.creado@siga.test',
            'ClaveSegura123!'
        );

        self::assertTrue(
            auth()->attempt([
                'email' => $user->email,
                'password' => 'ClaveSegura123!',
            ])
        );

        self::assertSame(
            $user->id,
            auth()->id()
        );
    }
}