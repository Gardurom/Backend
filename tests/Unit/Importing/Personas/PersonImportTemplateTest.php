<?php

namespace Tests\Unit\Importing\Personas;

use App\Importing\Personas\InvalidPersonImportTemplate;
use App\Importing\Personas\PersonImportTemplate;
use PHPUnit\Framework\TestCase;

class PersonImportTemplateTest extends TestCase
{
    public function test_exposes_stable_v1_contract(): void
    {
        $template = new PersonImportTemplate;

        self::assertSame(
            'PERSONAS',
            $template->importType()
        );

        self::assertSame(
            '1',
            $template->version()
        );

        self::assertSame(
            [
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'fecha_nacimiento',
                'sexo',
                'estado_civil',
                'correo_personal',
            ],
            $template->columns()
        );
    }

    public function test_normalizes_supported_values_without_inventing_types(): void
    {
        $normalized = (new PersonImportTemplate)
            ->normalize([
                'nombres' => '  María Fernanda  ',
                'apellido_paterno' => '  López ',
                'apellido_materno' => '   ',
                'fecha_nacimiento' => ' 1990-04-15 ',
                'sexo' => ' femenino ',
                'estado_civil' => ' soltero ',
                'correo_personal' => ' PERSONA@EXAMPLE.TEST ',
            ]);

        self::assertSame(
            [
                'nombres' => 'María Fernanda',
                'apellido_paterno' => 'López',
                'apellido_materno' => null,
                'fecha_nacimiento' => '1990-04-15',
                'sexo' => 'FEMENINO',
                'estado_civil' => 'SOLTERO',
                'correo_personal' => 'persona@example.test',
            ],
            $normalized
        );

        $numericName = (new PersonImportTemplate)
            ->normalize([
                'nombres' => 123,
                'apellido_paterno' => 'PRUEBA',
                'apellido_materno' => null,
                'fecha_nacimiento' => null,
                'sexo' => null,
                'estado_civil' => null,
                'correo_personal' => null,
            ]);

        self::assertSame(
            123,
            $numericName['nombres']
        );
    }

    public function test_rejects_missing_template_column(): void
    {
        $this->expectException(
            InvalidPersonImportTemplate::class
        );

        (new PersonImportTemplate)
            ->normalize([
                'nombres' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
                'apellido_materno' => null,
                'fecha_nacimiento' => null,
                'sexo' => null,
                'estado_civil' => null,
            ]);
    }

    public function test_rejects_unknown_template_column(): void
    {
        $this->expectException(
            InvalidPersonImportTemplate::class
        );

        (new PersonImportTemplate)
            ->normalize([
                'nombres' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
                'apellido_materno' => null,
                'fecha_nacimiento' => null,
                'sexo' => null,
                'estado_civil' => null,
                'correo_personal' => null,
                'curp' => 'GARM900101HNTRRR01',
            ]);
    }

    public function test_rejects_reordered_template_columns(): void
    {
        $this->expectException(
            InvalidPersonImportTemplate::class
        );

        (new PersonImportTemplate)
            ->normalize([
                'apellido_paterno' => 'LOPEZ',
                'nombres' => 'MARIA',
                'apellido_materno' => null,
                'fecha_nacimiento' => null,
                'sexo' => null,
                'estado_civil' => null,
                'correo_personal' => null,
            ]);
    }
}
