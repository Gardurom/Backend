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
            'nombres' => $this->normalizeText(
                $row['nombres']
            ),
            'apellido_paterno' => $this->normalizeText(
                $row['apellido_paterno']
            ),
            'apellido_materno' => $this->normalizeText(
                $row['apellido_materno']
            ),
            'fecha_nacimiento' => $this->normalizeText(
                $row['fecha_nacimiento']
            ),
            'sexo' => $this->normalizeUppercase(
                $row['sexo']
            ),
            'estado_civil' => $this->normalizeUppercase(
                $row['estado_civil']
            ),
            'correo_personal' => $this->normalizeLowercase(
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

    private function normalizeText(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim(
            $value
        );

        return $normalized === ''
            ? null
            : $normalized;
    }

    private function normalizeUppercase(
        mixed $value
    ): mixed {
        $normalized =
            $this->normalizeText(
                $value
            );

        if (! is_string($normalized)) {
            return $normalized;
        }

        return strtoupper(
            $normalized
        );
    }

    private function normalizeLowercase(
        mixed $value
    ): mixed {
        $normalized =
            $this->normalizeText(
                $value
            );

        if (! is_string($normalized)) {
            return $normalized;
        }

        return strtolower(
            $normalized
        );
    }
}
