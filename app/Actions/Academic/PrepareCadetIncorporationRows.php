<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Importing\Readers\XlsxImportSourceReader;

// Prepara filas de cadetes sin persistencia institucional.
// La operacion de incorporacion conserva las transacciones.
final class PrepareCadetIncorporationRows
{
    public function execute(
        XlsxImportSourceReader $reader,
        string $path,
        array $rows,
        array $academy,
        callable $normalize
    ): array {
        $canonical = static function (mixed $value) use ($normalize): string {
            $text = strtr($normalize($value), [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I',
                'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            ]);

            return str_replace(
                [' ', '_', '.', '-', '(', ')'],
                '',
                $text
            );
        };

        // Los datos identificadores solo se extraen en el servidor.
        // No se exponen RFC completos en la respuesta.
        $sourceRows = [];

        foreach ($reader->read(
            $path,
            [
                'No.',
                'ID BECARIO',
                'RFC',
                'GENERACIÓN',
                'GRUPO',
                'Gpo. No.',
            ],
            [
                'NOMBRES',
                'NOMBRE',
                'APELLIDO PATERNO',
                'APELLIDO MATERNO',
                'NOMBRE COMPLETO',
                'PERFILACIÓN',
                'PERFILACION',
                'CORREO INSTITUCIONAL',
                'EMAIL INSTITUCIONAL',
            ]
        ) as $source) {
            $number = (int) $source->rowNumber;

            if (isset($sourceRows[$number])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => 'Existe una fila Excel repetida.',
                ]);
            }

            $fields = [];

            foreach ($source->rawData as $header => $value) {
                $key = $canonical($header);

                if (isset($fields[$key])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'archivo' => 'Hay columnas identificadoras repetidas.',
                    ]);
                }

                $fields[$key] = $value;
            }

            $sourceRows[$number] = $fields;
        }

        if (count($sourceRows) !== count($rows)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'Las filas de origen y validacion no coinciden.',
            ]);
        }

        $groupsExpected = [];

        foreach ($academy['grupos_iniciales'] ?? [] as $group) {
            $code = $normalize($group['codigo'] ?? '');

            if (
                $code === ''
                || isset($groupsExpected[$code])
                || ($group['especializacion'] ?? null) !== null
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => 'Organizacion de grupos iniciales invalida.',
                ]);
            }

            $groupsExpected[$code] = true;
        }

        if ($groupsExpected === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'No existen grupos iniciales.',
            ]);
        }

        $prepared = [];
        $seenRfc = [];

        foreach ($rows as $row) {
            $number = (int) ($row['fila_excel'] ?? 0);
            $fields = $sourceRows[$number] ?? null;

            if (
                !is_array($fields)
                || ($row['errores'] ?? []) !== []
                || !empty($row['requiere_revision_nombre'])
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "La fila {$number} requiere revision.",
                ]);
            }

            $rfc = $normalize($fields['RFC'] ?? '');

            if (
                preg_match(
                    '/^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/uD',
                    $rfc
                ) !== 1
                || isset($seenRfc[$rfc])
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "La fila {$number} no tiene RFC personal valido y unico.",
                ]);
            }

            $seenRfc[$rfc] = true;

            // Solo se utiliza el dato institucional relevante.
            // Las columnas adicionales del Excel no crean atributos.
            $emailFromExcel = mb_strtolower(trim((string) (
                $fields['CORREOINSTITUCIONAL']
                ?? $fields['EMAILINSTITUCIONAL']
                ?? ''
            )));

            if (
                $emailFromExcel !== ''
                && (
                    strlen($emailFromExcel) > 255
                    || filter_var(
                        $emailFromExcel,
                        FILTER_VALIDATE_EMAIL
                    ) === false
                )
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "Correo institucional invalido en fila {$number}.",
                ]);
            }

            $names = $normalize(
                $fields['NOMBRES']
                ?? $fields['NOMBRE']
                ?? ''
            );

            $paternal = $normalize(
                $fields['APELLIDOPATERNO'] ?? ''
            );

            $maternal = $normalize(
                $fields['APELLIDOMATERNO'] ?? ''
            );

            if (
                $names === ''
                || ($paternal === '' && $maternal === '')
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "La fila {$number} necesita nombres y apellidos separados.",
                ]);
            }

            $groupCode = $normalize($row['grupo'] ?? '');

            $generation = $canonical($row['generacion'] ?? '');

            if (
                !isset($groupsExpected[$groupCode])
                || preg_match(
                    '/^0*([0-9]{1,3})[A-Z]{0,4}$/D',
                    $generation,
                    $parts
                ) !== 1
                || (int) $parts[1] !==
                    (int) $academy['numero_generacion']
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "La fila {$number} tiene grupo o generacion incompatibles.",
                ]);
            }

            $profile = $canonical(
                $fields['PERFILACION'] ?? ''
            );

            if (
                !in_array(
                    $profile,
                    ['', 'FORMACIONINICIAL', 'SINPERFILACION', 'SINESPECIALIZACION'],
                    true
                )
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "La fila {$number} no corresponde a formacion inicial.",
                ]);
            }

            if (isset($row['nombre_desglosado'])
                && is_array($row['nombre_desglosado'])) {
                foreach ([
                    'nombres' => $names,
                    'apellido_paterno' => $paternal,
                    'apellido_materno' => $maternal,
                ] as $key => $expected) {
                    if (
                        $normalize($row['nombre_desglosado'][$key] ?? '')
                        !== $expected
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'archivo' => "La fila {$number} tiene nombres inconsistentes.",
                        ]);
                    }
                }
            }

            $reconciliation = $row['conciliacion'] ?? null;

            $reconciliationState = is_array($reconciliation)
                ? (string) ($reconciliation['estado'] ?? '')
                : (is_string($reconciliation) ? $reconciliation : '');

            if (preg_match(
                '/REVISION|CONFLICTO|AMBIGU/i',
                $reconciliationState
            ) === 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "La fila {$number} tiene una conciliacion pendiente.",
                ]);
            }

            $prepared[] = [
                'fila_excel' => $number,
                'rfc' => $rfc,
                'correo_institucional' => $emailFromExcel,
                'nombres' => $names,
                'apellido_paterno' => $paternal ?: null,
                'apellido_materno' => $maternal ?: null,
                'grupo' => $groupCode,
                'matricula' => trim(
                    (string) ($row['matricula'] ?? '')
                ),
            ];
        }

        return $prepared;
    }
}
