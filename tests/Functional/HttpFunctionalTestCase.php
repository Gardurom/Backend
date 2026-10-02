<?php

namespace Tests\Functional;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

abstract class HttpFunctionalTestCase extends TestCase
{
    use DatabaseTransactions;

    protected Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'installation',
            $this->app->environment(),
            'Las pruebas HTTP funcionales requieren el entorno installation.'
        );

        $this->db = $this->app->make('db')->connection();

        self::assertSame(
            'pgsql',
            $this->db->getDriverName(),
            'Las pruebas HTTP funcionales deben utilizar PostgreSQL.'
        );

        $identity = $this->db->selectOne(
            'SELECT current_database() AS database_name,
                    session_user AS login_role,
                    current_user AS effective_role',
            [],
            false
        );

        self::assertSame(
            'siga_installation_test',
            $identity->database_name,
            'La conexion HTTP debe apuntar a siga_installation_test.'
        );

        self::assertSame(
            'siga_app',
            $identity->login_role,
            'Las pruebas HTTP deben conectarse como siga_app.'
        );

        self::assertSame(
            'siga_app',
            $identity->effective_role,
            'Las pruebas HTTP deben conservar el rol de aplicacion.'
        );

        self::assertSame(
            'database',
            config('session.driver'),
            'Las pruebas HTTP deben utilizar sesiones en base de datos.'
        );

        self::assertSame(
            'system.sessions',
            config('session.table'),
            'Las sesiones deben almacenarse en system.sessions.'
        );

        self::assertTrue(
            config('session.encrypt'),
            'Las sesiones de SIGA deben almacenarse cifradas.'
        );

        self::assertGreaterThan(
            0,
            $this->db->transactionLevel(),
            'Las pruebas HTTP deben ejecutarse dentro de una transaccion reversible.'
        );
    }
}
