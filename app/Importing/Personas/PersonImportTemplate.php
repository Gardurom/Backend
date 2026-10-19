<?php

namespace App\Importing\Personas;

final class PersonImportTemplate
{
    private const IMPORT_TYPE = 'PERSONAS';

    private const VERSION = '1';

    /**
     * @var list<string>
     */
    private const COLUMNS = [
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'sexo',
        'estado_civil',
        'correo_personal',
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
     * de validación posterior determine si son válidos.
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
            'fecha_nacimiento' => PersonImportValueNormalizer::text(
                $row['fecha_nacimiento']
            ),
            'sexo' => PersonImportValueNormalizer::uppercase(
                $row['sexo']
            ),
            'estado_civil' => PersonImportValueNormalizer::uppercase(
                $row['estado_civil']
            ),
            'correo_personal' => PersonImportValueNormalizer::lowercase(
                $row['correo_personal']
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
                'Las columnas deben coincidir exactamente con la plantilla PERSONAS v1.'
            );
        }
    }
}
