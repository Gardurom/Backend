<?php

namespace Tests\Feature\Importing\Personas;

use App\Importing\Personas\DatabasePersonImportReferenceLookup;
use App\Importing\Personas\PersonImportReferenceLookup;
use App\Importing\Personas\PersonImportRowValidationPipeline;
use Tests\TestCase;

class PersonImportContainerWiringTest extends TestCase
{
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
