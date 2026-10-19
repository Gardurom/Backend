<?php

namespace Tests\Unit\Importing\Personas;

use App\Importing\Personas\PersonImportReferenceLookup;
use App\Importing\Personas\PersonImportReferenceResolver;
use App\Importing\Personas\PersonImportRowValidationPipeline;
use App\Importing\Personas\PersonImportRowValidator;
use App\Importing\Personas\PersonImportTemplateV2;
use Illuminate\Contracts\Validation\Factory;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

class PersonImportRowValidationPipelineTest extends TestCase
{
    public function test_validates_and_resolves_complete_valid_row(): void
    {
        $lookup = new PipelineFakeReferenceLookup(
            countries: [
                'MEX' => 'pais-mex',
            ],
            territories: [
                'pais-mex|18' => [
                    'territorio-nayarit',
                ],
            ]
        );

        $result = $this->pipeline(
            $lookup
        )->validate(
            $this->validRow([
                'nombres' => '  MARIA  ',
                'pais_origen_alpha3' => ' mex ',
                'pais_nacimiento_alpha3' => ' mex ',
                'territorio_nacimiento_clave' => ' 18 ',
            ])
        );

        self::assertTrue(
            $result->isValid()
        );

        self::assertSame(
            [],
            $result->errors
        );

        self::assertSame(
            'MARIA',
            $result->normalizedData['nombres']
        );

        self::assertSame(
            'MEX',
            $result->normalizedData['pais_origen_alpha3']
        );

        self::assertSame(
            'MEX',
            $result->normalizedData['pais_nacimiento_alpha3']
        );

        self::assertSame(
            '18',
            $result->normalizedData['territorio_nacimiento_clave']
        );

        self::assertSame(
            'pais-mex',
            $result->normalizedData['id_pais_origen']
        );

        self::assertSame(
            'pais-mex',
            $result->normalizedData['id_pais_nacimiento']
        );

        self::assertSame(
            'territorio-nayarit',
            $result->normalizedData['id_territorio_nacimiento']
        );
    }

    public function test_local_validation_failure_skips_reference_lookup(): void
    {
        $result = $this->pipeline(
            new ThrowingPersonImportReferenceLookup
        )->validate(
            $this->validRow([
                'nombres' => 123,
                'pais_origen_alpha3' => 'MEX',
            ])
        );

        self::assertFalse(
            $result->isValid()
        );

        self::assertArrayHasKey(
            'nombres',
            $result->errors
        );

        self::assertSame(
            123,
            $result->normalizedData['nombres']
        );

        self::assertSame(
            'MEX',
            $result->normalizedData['pais_origen_alpha3']
        );

        self::assertNull(
            $result->normalizedData['id_pais_origen']
        );

        self::assertNull(
            $result->normalizedData['id_pais_nacimiento']
        );

        self::assertNull(
            $result->normalizedData['id_territorio_nacimiento']
        );
    }

    public function test_reference_failure_marks_locally_valid_row_invalid(): void
    {
        $result = $this->pipeline(
            new PipelineFakeReferenceLookup
        )->validate(
            $this->validRow([
                'pais_origen_alpha3' => 'ZZZ',
            ])
        );

        self::assertFalse(
            $result->isValid()
        );

        self::assertArrayHasKey(
            'pais_origen_alpha3',
            $result->errors
        );

        self::assertSame(
            'ZZZ',
            $result->normalizedData['pais_origen_alpha3']
        );

        self::assertNull(
            $result->normalizedData['id_pais_origen']
        );
    }

    public function test_valid_row_without_references_keeps_null_internal_ids(): void
    {
        $result = $this->pipeline(
            new PipelineFakeReferenceLookup
        )->validate(
            $this->validRow()
        );

        self::assertTrue(
            $result->isValid()
        );

        self::assertSame(
            [],
            $result->errors
        );

        self::assertNull(
            $result->normalizedData['id_pais_origen']
        );

        self::assertNull(
            $result->normalizedData['id_pais_nacimiento']
        );

        self::assertNull(
            $result->normalizedData['id_territorio_nacimiento']
        );
    }

    private function pipeline(
        PersonImportReferenceLookup $lookup
    ): PersonImportRowValidationPipeline {
        return new PersonImportRowValidationPipeline(
            new PersonImportRowValidator(
                $this->app->make(
                    Factory::class
                ),
                new PersonImportTemplateV2
            ),
            new PersonImportReferenceResolver(
                $lookup
            )
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRow(
        array $overrides = []
    ): array {
        $row = [
            'nombres' => 'PERSONA',
            'apellido_paterno' => 'PRUEBA',
            'apellido_materno' => null,
            'curp' => null,
            'rfc' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'estado_civil' => null,
            'correo_institucional' => null,
            'correo_personal' => null,
            'pais_origen_alpha3' => null,
            'pais_nacimiento_alpha3' => null,
            'territorio_nacimiento_clave' => null,
        ];

        foreach ($overrides as $field => $value) {
            $row[$field] = $value;
        }

        return $row;
    }
}

final class PipelineFakeReferenceLookup implements PersonImportReferenceLookup
{
    /**
     * @param  array<string, string>  $countries
     * @param  array<string, list<string>>  $territories
     */
    public function __construct(
        private readonly array $countries = [],
        private readonly array $territories = []
    ) {}

    public function findActiveCountryIdByAlpha3(
        string $alpha3
    ): ?string {
        return $this->countries[$alpha3]
            ?? null;
    }

    public function findActiveTerritoryIds(
        string $countryId,
        string $officialKey
    ): array {
        return $this->territories[
            $countryId.'|'.$officialKey
        ] ?? [];
    }
}

final class ThrowingPersonImportReferenceLookup implements PersonImportReferenceLookup
{
    public function findActiveCountryIdByAlpha3(
        string $alpha3
    ): ?string {
        throw new AssertionFailedError(
            'No debía consultarse el catálogo para una fila localmente inválida.'
        );
    }

    public function findActiveTerritoryIds(
        string $countryId,
        string $officialKey
    ): array {
        throw new AssertionFailedError(
            'No debía consultarse el catálogo para una fila localmente inválida.'
        );
    }
}
