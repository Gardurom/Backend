<?php

namespace Tests\Feature\Importing\Personas;

use App\Importing\Personas\DatabasePersonImportReferenceLookup;
use App\Importing\Personas\PersonImportReferenceLookup;
use App\Importing\Personas\PersonImportRowValidationPipeline;
use Tests\TestCase;

class PersonImportContainerWiringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Verifica la construccion del servicio con el controlador
        // PostgreSQL, sin establecer una conexion real.
        config()->set('database.connections.siga_wiring_sin_conexion', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'siga_no_conectar',
            'username' => 'sin_acceso',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        config()->set(
            'database.default',
            'siga_wiring_sin_conexion'
        );
    }

    public function test_container_resolves_database_reference_lookup(): void
    {
        $lookup = $this->app->make(
            PersonImportReferenceLookup::class
        );

        self::assertInstanceOf(
            DatabasePersonImportReferenceLookup::class,
            $lookup
        );
    }

    public function test_container_resolves_complete_row_validation_pipeline(): void
    {
        $pipeline = $this->app->make(
            PersonImportRowValidationPipeline::class
        );

        self::assertInstanceOf(
            PersonImportRowValidationPipeline::class,
            $pipeline
        );
    }
}
