<?php

namespace Tests\Functional\Auth;

use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class UserEmailIntegrityTest extends FunctionalTestCase
{
    public function test_database_rejects_email_that_differs_only_by_case(): void
    {
        $this->db->table('system.users')->insert([
            'name' => 'PRIMER USUARIO EMAIL',
            'email' => 'correo.unico@siga.test',
            'password' => 'NO_USAR_EN_AUTENTICACION',
        ]);

        try {
            $this->db->table('system.users')->insert([
                'name' => 'SEGUNDO USUARIO EMAIL',
                'email' => 'CORREO.UNICO@SIGA.TEST',
                'password' => 'NO_USAR_EN_AUTENTICACION',
            ]);
        } catch (QueryException $error) {
            self::assertSame('23505', $error->getCode());

            return;
        }

        self::fail(
            'PostgreSQL debe rechazar correos equivalentes sin distinguir mayúsculas.'
        );
    }
}
