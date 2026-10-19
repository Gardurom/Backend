<?php

namespace Tests\Unit\Importing\Personas;

use App\Importing\Personas\PersonImportRowValidator;
use App\Importing\Personas\PersonImportTemplateV2;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PersonImportRowValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::parse(
                '2026-10-19 12:00:00',
                'America/Mexico_City'
            )
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_accepts_valid_v2_row_and_returns_normalized_data(): void
    {
        $result = $this->validator()->validate(
            $this->validRow([
                'nombres' => '  María Fernanda  ',
                'apellido_paterno' => ' López ',
                'curp' => ' garm900101hntrrr01 ',
                'rfc' => ' garm900101abc ',
                'fecha_nacimiento' => ' 1990-01-01 ',
                'sexo' => ' femenino ',
                'estado_civil' => ' soltero ',
                'correo_institucional' => ' PERSONA@SIGA.TEST ',
                'correo_personal' => ' PERSONAL@EXAMPLE.TEST ',
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
            [
                'nombres' => 'María Fernanda',
                'apellido_paterno' => 'López',
                'apellido_materno' => null,
                'curp' => 'GARM900101HNTRRR01',
                'rfc' => 'GARM900101ABC',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'FEMENINO',
                'estado_civil' => 'SOLTERO',
                'correo_institucional' => 'persona@siga.test',
                'correo_personal' => 'personal@example.test',
                'pais_origen_alpha3' => 'MEX',
                'pais_nacimiento_alpha3' => 'MEX',
                'territorio_nacimiento_clave' => '18',
            ],
            $result->normalizedData
        );
    }

    public function test_accepts_maternal_surname_as_only_surname(): void
    {
        $result = $this->validator()->validate(
            $this->validRow([
                'apellido_paterno' => null,
                'apellido_materno' => 'LOPEZ',
            ])
        );

        self::assertTrue(
            $result->isValid()
        );

        self::assertSame(
            [],
            $result->errors
        );
    }

    public function test_collects_person_and_reference_errors_by_field(): void
    {
        $result = $this->validator()->validate(
            $this->validRow([
                'nombres' => 123,
                'apellido_paterno' => null,
                'apellido_materno' => null,
                'curp' => 'ABC',
                'rfc' => 'ABC',
                'fecha_nacimiento' => '2026-10-20',
                'sexo' => 'OTRO',
                'estado_civil' => 'DIVORCIADO',
                'correo_institucional' => 123,
                'correo_personal' => 123,
                'pais_origen_alpha3' => 'MX',
                'pais_nacimiento_alpha3' => null,
                'territorio_nacimiento_clave' => '18',
            ])
        );

        self::assertFalse(
            $result->isValid()
        );

        foreach ([
            'nombres',
            'apellido_paterno',
            'apellido_materno',
            'curp',
            'rfc',
            'fecha_nacimiento',
            'sexo',
            'estado_civil',
            'correo_institucional',
            'correo_personal',
            'pais_origen_alpha3',
            'pais_nacimiento_alpha3',
        ] as $field) {
            self::assertArrayHasKey(
                $field,
                $result->errors
            );

            self::assertNotSame(
                [],
                $result->errors[$field]
            );
        }
    }

    public function test_rejects_internal_whitespace_in_storage_constrained_values(): void
    {
        $result = $this->validator()->validate(
            $this->validRow([
                'curp' => 'GARM900101HNTRR 01',
                'rfc' => 'GARM9001 1ABC',
                'correo_institucional' => 'persona @siga.test',
                'correo_personal' => 'personal @example.test',
            ])
        );

        self::assertFalse(
            $result->isValid()
        );

        foreach ([
            'curp',
            'rfc',
            'correo_institucional',
            'correo_personal',
        ] as $field) {
            self::assertArrayHasKey(
                $field,
                $result->errors
            );
        }
    }

    public function test_requires_birth_country_when_territory_is_provided(): void
    {
        $result = $this->validator()->validate(
            $this->validRow([
                'pais_nacimiento_alpha3' => null,
                'territorio_nacimiento_clave' => '18',
            ])
        );

        self::assertFalse(
            $result->isValid()
        );

        self::assertArrayHasKey(
            'pais_nacimiento_alpha3',
            $result->errors
        );
    }

    private function validator(): PersonImportRowValidator
    {
        return new PersonImportRowValidator(
            $this->app->make(
                Factory::class
            ),
            new PersonImportTemplateV2
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
