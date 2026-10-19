<?php

namespace Tests\Unit\Importing\Personas;

use App\Importing\Personas\PersonImportReferenceLookup;
use App\Importing\Personas\PersonImportReferenceResolver;
use PHPUnit\Framework\TestCase;

class PersonImportReferenceResolverTest extends TestCase
{
    public function test_resolves_country_and_territory_references(): void
    {
        $lookup = new FakePersonImportReferenceLookup(
            countries: [
                'MEX' => 'pais-mex',
            ],
            territories: [
                'pais-mex|18' => [
                    'territorio-nayarit',
                ],
            ]
        );

        $result = (new PersonImportReferenceResolver(
            $lookup
        ))->resolve([
            'pais_origen_alpha3' => 'MEX',
            'pais_nacimiento_alpha3' => 'MEX',
            'territorio_nacimiento_clave' => '18',
        ]);

        self::assertTrue(
            $result->isValid()
        );

        self::assertSame(
            [],
            $result->errors
        );

        self::assertSame(
            [
                'id_pais_origen' => 'pais-mex',
                'id_pais_nacimiento' => 'pais-mex',
                'id_territorio_nacimiento' => 'territorio-nayarit',
            ],
            $result->resolvedData
        );
    }

    public function test_accepts_null_optional_references(): void
    {
        $result = (new PersonImportReferenceResolver(
            new FakePersonImportReferenceLookup
        ))->resolve([
            'pais_origen_alpha3' => null,
            'pais_nacimiento_alpha3' => null,
            'territorio_nacimiento_clave' => null,
        ]);

        self::assertTrue(
            $result->isValid()
        );

        self::assertSame(
            [
                'id_pais_origen' => null,
                'id_pais_nacimiento' => null,
                'id_territorio_nacimiento' => null,
            ],
            $result->resolvedData
        );

        self::assertSame(
            [],
            $result->errors
        );
    }

    public function test_rejects_country_that_cannot_be_resolved(): void
    {
        $result = (new PersonImportReferenceResolver(
            new FakePersonImportReferenceLookup
        ))->resolve([
            'pais_origen_alpha3' => 'ZZZ',
            'pais_nacimiento_alpha3' => null,
            'territorio_nacimiento_clave' => null,
        ]);

        self::assertFalse(
            $result->isValid()
        );

        self::assertArrayHasKey(
            'pais_origen_alpha3',
            $result->errors
        );

        self::assertNull(
            $result->resolvedData['id_pais_origen']
        );
    }

    public function test_rejects_birth_country_that_cannot_be_resolved(): void
    {
        $result = (new PersonImportReferenceResolver(
            new FakePersonImportReferenceLookup
        ))->resolve([
            'pais_origen_alpha3' => null,
            'pais_nacimiento_alpha3' => 'ZZZ',
            'territorio_nacimiento_clave' => '18',
        ]);

        self::assertFalse(
            $result->isValid()
        );

        self::assertArrayHasKey(
            'pais_nacimiento_alpha3',
            $result->errors
        );

        self::assertNull(
            $result->resolvedData['id_pais_nacimiento']
        );

        self::assertNull(
            $result->resolvedData['id_territorio_nacimiento']
        );
    }

    public function test_rejects_unknown_territory_for_resolved_country(): void
    {
        $lookup = new FakePersonImportReferenceLookup(
            countries: [
                'MEX' => 'pais-mex',
            ]
        );

        $result = (new PersonImportReferenceResolver(
            $lookup
        ))->resolve([
            'pais_origen_alpha3' => null,
            'pais_nacimiento_alpha3' => 'MEX',
            'territorio_nacimiento_clave' => '99',
        ]);

        self::assertFalse(
            $result->isValid()
        );

        self::assertArrayHasKey(
            'territorio_nacimiento_clave',
            $result->errors
        );

        self::assertSame(
            'pais-mex',
            $result->resolvedData['id_pais_nacimiento']
        );

        self::assertNull(
            $result->resolvedData['id_territorio_nacimiento']
        );
    }

    public function test_rejects_ambiguous_territory_reference(): void
    {
        $lookup = new FakePersonImportReferenceLookup(
            countries: [
                'MEX' => 'pais-mex',
            ],
            territories: [
                'pais-mex|18' => [
                    'territorio-uno',
                    'territorio-dos',
                ],
            ]
        );

        $result = (new PersonImportReferenceResolver(
            $lookup
        ))->resolve([
            'pais_origen_alpha3' => null,
            'pais_nacimiento_alpha3' => 'MEX',
            'territorio_nacimiento_clave' => '18',
        ]);

        self::assertFalse(
            $result->isValid()
        );

        self::assertArrayHasKey(
            'territorio_nacimiento_clave',
            $result->errors
        );

        self::assertNull(
            $result->resolvedData['id_territorio_nacimiento']
        );
    }

    public function test_scopes_territory_to_birth_country(): void
    {
        $lookup = new FakePersonImportReferenceLookup(
            countries: [
                'MEX' => 'pais-mex',
                'USA' => 'pais-usa',
            ],
            territories: [
                'pais-mex|18' => [
                    'territorio-mex',
                ],
                'pais-usa|18' => [
                    'territorio-usa',
                ],
            ]
        );

        $result = (new PersonImportReferenceResolver(
            $lookup
        ))->resolve([
            'pais_origen_alpha3' => null,
            'pais_nacimiento_alpha3' => 'USA',
            'territorio_nacimiento_clave' => '18',
        ]);

        self::assertTrue(
            $result->isValid()
        );

        self::assertSame(
            'pais-usa',
            $result->resolvedData['id_pais_nacimiento']
        );

        self::assertSame(
            'territorio-usa',
            $result->resolvedData['id_territorio_nacimiento']
        );
    }
}

final class FakePersonImportReferenceLookup implements PersonImportReferenceLookup
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
