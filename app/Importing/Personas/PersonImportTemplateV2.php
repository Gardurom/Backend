<?php

namespace App\Importing\Personas;

final class PersonImportTemplateV2
{
    private const IMPORT_TYPE = 'PERSONAS';

    private const VERSION = '2';

    /**
     * @var list<string>
     */
    private const COLUMNS = [
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
    ];

    public function importType(): string
    {
        return self::IMPORT_TYPE;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Normaliza representación, pero no valida semántica.
     *
     * Los valores no string se conservan para que la capa
     * posterior pueda reportar el error correspondiente.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalize(
        array $row
    ): array {
        $this->assertColumns($row);

        return [
            'nombres' => PersonImportValueNormalizer::text(
                $row['nombres']
            ),
            'apellido_paterno' => PersonImportValueNormalizer::text(
                $row['apellido_paterno']
            ),
            'apellido_materno' => PersonImportValueNormalizer::text(
                $row['apellido_materno']
            ),
            'curp' => PersonImportValueNormalizer::uppercase(
                $row['curp']
            ),
            'rfc' => PersonImportValueNormalizer::uppercase(
                $row['rfc']
            ),
            'fecha_nacimiento' => PersonImportValueNormalizer::text(
                $row['fecha_nacimiento']
            ),
            'sexo' => PersonImportValueNormalizer::uppercase(
                $row['sexo']
            ),
            'estado_civil' => PersonImportValueNormalizer::uppercase(
                $row['estado_civil']
            ),
            'correo_institucional' => PersonImportValueNormalizer::lowercase(
                $row['correo_institucional']
            ),
            'correo_personal' => PersonImportValueNormalizer::lowercase(
                $row['correo_personal']
            ),
            'pais_origen_alpha3' => PersonImportValueNormalizer::uppercase(
                $row['pais_origen_alpha3']
            ),
            'pais_nacimiento_alpha3' => PersonImportValueNormalizer::uppercase(
                $row['pais_nacimiento_alpha3']
            ),
            'territorio_nacimiento_clave' => PersonImportValueNormalizer::text(
                $row['territorio_nacimiento_clave']
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertColumns(
        array $row
    ): void {
        if (
            array_keys($row)
            !== self::COLUMNS
        ) {
            throw new InvalidPersonImportTemplate(
                'Las columnas deben coincidir exactamente con la plantilla PERSONAS v2.'
            );
        }
    }
}
