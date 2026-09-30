<?php

namespace Tests\Installation;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class InstallationTestCase extends TestCase
{
    protected ?Application $app = null;

    protected ?Connection $db = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->app = require dirname(__DIR__, 2)
                . '/bootstrap/app.php';

            $this->app->make(Kernel::class)->bootstrap();

            $this->db = $this->app->make('db')->connection();

            self::assertSame(
                'pgsql',
                $this->db->getDriverName(),
                'La instalacion de SIGA debe utilizar PostgreSQL.'
            );

            self::assertSame(
                0,
                $this->db->transactionLevel(),
                'La conexion debe iniciar sin transacciones abiertas.'
            );

            $this->db->beginTransaction();

            $this->db->statement(
                'SET TRANSACTION READ ONLY'
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