<?php

namespace Tests\Functional;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class FunctionalTestCase extends TestCase
{
    protected ?Application $app = null;

    protected ?Connection $db = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $projectRoot = dirname(__DIR__, 2);

            self::assertFileExists(
                $projectRoot . '/.env.installation',
                'Debe existir el entorno exclusivo de pruebas.'
            );

            $this->app = require $projectRoot . '/bootstrap/app.php';

            self::assertFalse(
                $this->app->configurationIsCached(),
                'Las pruebas funcionales requieren revisar la configuracion cacheada antes de continuar.'
            );

            $this->app->make(Kernel::class)->bootstrap();

            self::assertSame(
                'installation',
                $this->app->environment(),
                'Las pruebas funcionales requieren el entorno installation.'
            );

            $this->db = $this->app->make('db')->connection();

            self::assertSame(
                'pgsql',
                $this->db->getDriverName(),
                'Las pruebas funcionales deben utilizar PostgreSQL.'
            );

            self::assertSame(
                0,
                $this->db->transactionLevel(),
                'La conexion debe iniciar sin transacciones abiertas.'
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
                'La conexion efectiva debe apuntar a siga_installation_test.'
            );

            self::assertSame(
                'siga_app',
                $identity->login_role,
                'Las pruebas funcionales deben conectarse como siga_app.'
            );

            self::assertSame(
                'siga_app',
                $identity->effective_role,
                'Las pruebas funcionales deben conservar el rol de aplicacion.'
            );

            $this->db->beginTransaction();

            $this->db->statement(
                'SET TRANSACTION READ WRITE'
            );
        } catch (Throwable $error) {
            $this->releaseResources();

            throw $error;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->releaseResources();
        } finally {
            parent::tearDown();
        }
    }

    private function releaseResources(): void
    {
        try {
            if ($this->db !== null) {
                try {
                    if ($this->db->transactionLevel() > 0) {
                        $this->db->rollBack(0);
                    }
                } finally {
                    $this->db->disconnect();
                }
            }
        } finally {
            $this->db = null;

            try {
                if ($this->app !== null) {
                    $this->app->flush();
                }
            } finally {
                $this->app = null;

                Facade::clearResolvedInstances();
                Facade::setFacadeApplication(null);
                Application::setInstance(null);

                HandleExceptions::flushState($this);
            }
        }
    }
}