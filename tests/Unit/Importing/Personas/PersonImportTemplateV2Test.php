<?php

namespace Tests\Unit\Importing\Personas;

use App\Importing\Personas\InvalidPersonImportTemplate;
use App\Importing\Personas\PersonImportTemplateV2;
use PHPUnit\Framework\TestCase;

class PersonImportTemplateV2Test extends TestCase
{
    public function test_exposes_stable_v2_contract(): void
    {
        $template = new PersonImportTemplateV2;

        self::assertSame(
            'PERSONAS',
            $template->importType()
        );

        self::assertSame(
            '2',
            $template->version()
        );

        self::assertSame(
            [
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
                'territorio_nacimiento_clave',
            ],
            $template->columns()
        );
    }

    public function test_normalizes_person_v2_representation(): void
    {
        $normalized = (new PersonImportTemplateV2)
            ->normalize([
                'nombres' => '  María Fernanda  ',
                'apellido_paterno' => ' López ',
                'apellido_materno' => '   ',
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
            ]);

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
            $normalized
        );
    }

    public function test_preserves_non_string_values_for_later_validation(): void
    {
        $normalized = (new PersonImportTemplateV2)
            ->normalize([
                'nombres' => 123,
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
            ]);

        self::assertSame(
            123,
            $normalized['nombres']
        );
    }

    public function test_rejects_missing_v2_column(): void
    {
        $this->expectException(
            InvalidPersonImportTemplate::class
        );

        (new PersonImportTemplateV2)
            ->normalize([
                'nombres' => 'MARIA',
            ]);
    }

    public function test_rejects_unknown_v2_column(): void
    {
        $row = $this->validRow();

        $row['estatus'] = 'ACTIVO';

        $this->expectException(
            InvalidPersonImportTemplate::class
        );

        (new PersonImportTemplateV2)
            ->normalize($row);
    }

    public function test_rejects_reordered_v2_columns(): void
    {
        $row = $this->validRow();

        $reordered = [
            'apellido_paterno' => $row['apellido_paterno'],
            'nombres' => $row['nombres'],
        ];

        foreach ($row as $field => $value) {
            if (
                $field === 'nombres'
                || $field === 'apellido_paterno'
            ) {
                continue;
            }

            $reordered[$field] = $value;
        }

        $this->expectException(
            InvalidPersonImportTemplate::class
        );

        (new PersonImportTemplateV2)
            ->normalize($reordered);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRow(): array
    {
        return [
            'nombres' => 'MARIA',
            'apellido_paterno' => 'LOPEZ',
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
    }
}
