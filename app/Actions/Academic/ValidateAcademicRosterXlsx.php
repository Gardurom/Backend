<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Importing\Readers\XlsxImportSourceReader;
use DomainException;

final class ValidateAcademicRosterXlsx
{
    private const REQUIRED_HEADERS = [
        'No.',
        'ID BECARIO',
        'RFC',
        'GENERACIÓN',
        'GRUPO',
        'Gpo. No.',
    ];

    public function __construct(
        private XlsxImportSourceReader $reader
    ) {
    }

    public function inspect(string $path, bool $conciliar = false, array $desgloses = []): array
    {
        $rows = [];
        $candidates = []; $filasEncontradas = [];
        $numbers = [];
        $matriculas = [];
        $rfcs = [];
        $positions = [];
        $groups = [];
        $baseGeneration = null;

        foreach ($this->reader->read($path) as $source) {
            if (count($rows) >= 1000) {
                throw new DomainException(
                    'Se permiten hasta 1000 registros por archivo.'
                );
            }

            $raw = $source->rawData;
            // SIGA_PERFILACION_XLSX_V1
            // El valor proviene del Excel y no otorga permisos.
            $perfilacionHeaderPresente =
                array_key_exists('PERFILACIÓN', $raw)
                || array_key_exists('PERFILACION', $raw);

            $perfilacion = null;

            if ($perfilacionHeaderPresente) {
                $valorPerfilacion =
                    $raw['PERFILACIÓN']
                    ?? $raw['PERFILACION']
                    ?? '';

                $perfilacionNormalizada = mb_strtoupper(
                    trim((string) $valorPerfilacion),
                    'UTF-8'
                );

                $perfilacionNormalizada = preg_replace(
                    '/\s+/u',
                    ' ',
                    $perfilacionNormalizada
                ) ?? '';

                $perfilacionNormalizada = strtr(
                    $perfilacionNormalizada,
                    [
                        'Á' => 'A',
                        'É' => 'E',
                        'Í' => 'I',
                        'Ó' => 'O',
                        'Ú' => 'U',
                        'Ü' => 'U',
                    ]
                );

                $perfilacion = match ($perfilacionNormalizada) {
                    'FORMACION INICIAL' => 'FORMACIÓN INICIAL',
                    'PERFILACION CAMPO' => 'PERFILACIÓN CAMPO',
                    'PERFILACION GABINETE' => 'PERFILACIÓN GABINETE',
                    default => null,
                };
            }

            // SIGA_XLSX_NOMBRES_SEPARADOS
            $availableHeaders = array_keys($raw);

            $hasSeparateNames =
                array_key_exists('NOMBRES', $raw)
                && array_key_exists('APELLIDO PATERNO', $raw)
                && array_key_exists('APELLIDO MATERNO', $raw);

            $hasFullName = array_key_exists(
                'NOMBRE COMPLETO',
                $raw
            );

            if (
                array_diff(
                    self::REQUIRED_HEADERS,
                    $availableHeaders
                ) !== []
                || (! $hasSeparateNames && ! $hasFullName)
            ) {
                throw new DomainException(
                    'Faltan columnas necesarias para la importacion academica.'
                );
            }


            $no = self::integer($raw['No.']);
            $matricula = self::text($raw['ID BECARIO']);
            $rfc = self::text($raw['RFC']);
                        if ($hasSeparateNames) {
                $parts = [
                    'nombres' => trim(
                        self::text($raw['NOMBRES'])
                    ),
                    'apellido_paterno' => trim(
                        self::text($raw['APELLIDO PATERNO'])
                    ),
                    'apellido_materno' => trim(
                        self::text($raw['APELLIDO MATERNO'])
                    ),
                ];

                $name = trim(implode(' ', array_filter(
                    [
                        $parts['apellido_paterno'],
                        $parts['apellido_materno'],
                        $parts['nombres'],
                    ],
                    static fn (string $value): bool => $value !== ''
                )));

                // Reutilizar el mecanismo de desglose ya
                // validado, sin crear otra logica de nombres.
                $excelRow = (int) $source->rowNumber;

                if (! array_key_exists($excelRow, $desgloses)) {
                    $desgloses[$excelRow] = $parts;
                }
            } else {
                // Compatibilidad con archivos anteriores.
                $name = self::text(
                    $raw['NOMBRE COMPLETO'] ?? null
                );
            }
            $generation = self::text($raw['GENERACIÓN']);
            $group = self::integer($raw['GRUPO']);
            $groupNo = self::integer($raw['Gpo. No.']);

            $matriculaKey = strtoupper(trim($matricula));
            $rfcKey = strtoupper(trim($rfc));
            $generationKey = strtoupper(
                preg_replace(
                    '/\s+/u',
                    '',
                    trim($generation)
                ) ?? ''
            );

            $errors = []; $warnings = [];
            if ($perfilacionHeaderPresente && $perfilacion === null) {
                $errors[] =
                    'PERFILACION_INVALIDA: usar FORMACION INICIAL, PERFILACION CAMPO o PERFILACION GABINETE.';
            }

            if (
                $perfilacion !== null
                && $perfilacion !== 'FORMACIÓN INICIAL'
            ) {
                $warnings[] =
                    'PERFILACION_POSTERIOR: requiere cadete existente, autorizacion e historial academico.';
            }

            $filasEncontradas[(int) $source->rowNumber] = true;
            $nombreDesglosado = null;
            $desglose = $desgloses[(int) $source->rowNumber] ?? null;

            if ($desglose !== null) {
                if (! is_array($desglose)) {
                    $errors[] = 'Desglose de nombre invalido';
                } else {
                    $nombres = trim((string) ($desglose['nombres'] ?? ''));
                    $paterno = trim((string) ($desglose['apellido_paterno'] ?? ''));
                    $materno = trim((string) ($desglose['apellido_materno'] ?? ''));

                    if (
                        $nombres === '' ||
                        ($paterno === '' && $materno === '') ||
                        mb_strlen($nombres) > 150 ||
                        mb_strlen($paterno) > 100 ||
                        mb_strlen($materno) > 100
                    ) {
                        $errors[] = 'Nombres y apellidos desglosados invalidos';
                    } else {
                        $originalWords = preg_split(
                            '/\s+/u',
                            mb_strtoupper(trim($name)),
                            -1,
                            PREG_SPLIT_NO_EMPTY
                        );

                        $reviewedWords = preg_split(
                            '/\s+/u',
                            mb_strtoupper(trim(
                                implode(' ', array_filter([
                                    $nombres, $paterno, $materno
                                ], static fn ($value) => $value !== ''))
                            )),
                            -1,
                            PREG_SPLIT_NO_EMPTY
                        );

                        if (
                            $originalWords === false ||
                            $reviewedWords === false
                        ) {
                            $errors[] = 'No fue posible comparar el nombre';
                        } else {
                            sort($originalWords, SORT_STRING);
                            sort($reviewedWords, SORT_STRING);

                            if ($originalWords !== $reviewedWords) {
                                // SIGA: correccion manual, no alta automatica.
                                $warnings[] = 'NOMBRE_CORREGIDO_RESPECTO_EXCEL: requiere confirmacion de identidad antes del alta.';
                            }
                            $nombreDesglosado = [
                                    'nombres' => $nombres,
                                    'apellido_paterno' => $paterno === '' ? null : $paterno,
                                    'apellido_materno' => $materno === '' ? null : $materno,
                                ];
                        }
                    }
                }
            }

            if ($no === null || $no > 1000000) {
                $errors[] = 'No. invalido';
            } elseif (isset($numbers[$no])) {
                $errors[] = 'No. duplicado';
            } else {
                $numbers[$no] = true;
            }

            if ($matriculaKey !== '') {
                if (
                    preg_match('/\A[A-Z0-9]{5,32}\z/D', $matriculaKey) !== 1
                ) {
                    $errors[] = 'Matricula con formato invalido';
                } elseif (isset($matriculas[$matriculaKey])) {
                    $errors[] = 'Matricula duplicada';
                } else {
                    $matriculas[$matriculaKey] = true;
                }
            }

            if ($rfcKey !== '') {
                if (
                    preg_match(
                        '/\A[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}\z/uD',
                        $rfcKey
                    ) !== 1
                ) {
                    $warnings[] = 'RFC requiere revision de formato';
                }

                if (isset($rfcs[$rfcKey])) {
                    $errors[] = 'RFC duplicado';
                } else {
                    $rfcs[$rfcKey] = true;
                }
            }

            if (
                trim($name) === '' ||
                mb_strlen($name) > 250
            ) {
                $errors[] = 'Nombre completo invalido';
            }

            if (
                $generationKey === '' ||
                strlen($generationKey) > 30 ||
                preg_match(
                    '/\A[0-9]{1,3}[A-Z]{0,4}\z/D',
                    $generationKey
                ) !== 1
            ) {
                $errors[] = 'Generacion no reconocida';
            } elseif ($baseGeneration === null) {
                $baseGeneration = $generationKey;
            } elseif ($baseGeneration !== $generationKey) {
                $errors[] = 'Generacion diferente en el mismo archivo';
            }

            if ($group === null || $group > 999) {
                $errors[] = 'Grupo invalido';
            } else {
                $key = (string) $group;
                $groups[$key] = ($groups[$key] ?? 0) + 1;
            }

            if ($groupNo === null || $groupNo > 1000) {
                $errors[] = 'Gpo. No. invalido';
            }

            if (
                $group !== null &&
                $groupNo !== null &&
                $generationKey !== ''
            ) {
                $position = $generationKey.'|'.$group.'|'.$groupNo;

                if (isset($positions[$position])) {
                    $errors[] = 'Posicion repetida dentro del grupo';
                } else {
                    $positions[$position] = true;
                }
            }

            // Solo advertencia. No altera la matricula.
            if (
                $no !== null &&
                preg_match(
                    '/([0-9]{3})\z/D',
                    $matricula,
                    $parts
                ) === 1 &&
                (int) $parts[1] !== $no
            ) {
                $warnings[] =
                    'El consecutivo de la matricula difiere de No.';
            }

            $status = $errors !== []
                ? 'ERROR'
                : (
                    $warnings !== []
                        ? 'OBSERVACION'
                        : 'VALIDO'
                );

            $candidates[] = [
                'rfc' => $rfcKey,
                'nombre' => $name,
            ];

            $rows[] = [
                'fila_excel' => $source->rowNumber,
                'perfilacion' => $perfilacion,
                'momento_academico' => $perfilacion === null
                    ? 'SIN_DEFINIR'
                    : (
                        $perfilacion === 'FORMACIÓN INICIAL'
                            ? 'FORMACION_INICIAL'
                            : 'PERFILACION_POSTERIOR'
                    ),
                'requiere_participante_existente' =>
                    $perfilacion !== null
                    && $perfilacion !== 'FORMACIÓN INICIAL',

                'numero' => $no,
                'matricula' => $matricula,
                'rfc_mascarado' => $rfcKey === ''
                    ? ''
                    : '****'.substr($rfcKey, -4),
                'nombre_completo' => $name,
                'nombre_desglosado' => $nombreDesglosado,
                'requiere_revision_nombre' => $nombreDesglosado === null,
                'generacion' => $generation,
                'grupo' => $group,
                'numero_grupo' => $groupNo,
                'estado' => $status,
                'errores' => $errors,
                'advertencias' => $warnings,
            ];
        }

        foreach (array_keys($desgloses) as $fila) {
            if (! isset($filasEncontradas[(int) $fila])) {
                throw new DomainException(
                    'Se proporciono un desglose para una fila inexistente.'
                );
            }
        }

        if ($rows === []) {
            throw new DomainException(
                'El Excel no contiene registros.'
            );
        }

        $summary = [
            'total' => count($rows),
            'validos' => 0,
            'con_observaciones' => 0,
            'con_errores' => 0,
            'advertencias' => 0,
            'nombres_pendientes_revision' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['requiere_revision_nombre']) {
                $summary['nombres_pendientes_revision']++;
            }

            if ($row['estado'] === 'VALIDO') {
                $summary['validos']++;
            } elseif ($row['estado'] === 'OBSERVACION') {
                $summary['con_observaciones']++;
            } else {
                $summary['con_errores']++;
            }

            $summary['advertencias'] += count(
                $row['advertencias']
            );
        }

        $conciliation = null;

        if ($conciliar) {
            $conciliation = app(
                AcademicIncorporationPrecheckLookup::class
            )->reconcileRoster($candidates);

            foreach ($rows as $index => &$row) {
                $row['conciliacion'] =
                    $conciliation['filas'][$index];
            }

            unset($row);
        }

        ksort($groups, SORT_NUMERIC);

        return [
            'fase' => 'H4-IMP-F1-R1',
            'generacion_detectada' => $baseGeneration,
            'summary' => $summary,
            'grupos' => $groups,
            'filas' => $rows,
            'conciliacion_personas_realizada' => $conciliar,
            'conciliacion' => $conciliation['resumen'] ?? null,
            'persistido' => false,
            'matriculas_generadas' => 0,
            'usuarios_creados' => 0,
        ];
    }

    private static function text(mixed $value): string
    {
        if (
            is_string($value) ||
            is_int($value) ||
            is_float($value)
        ) {
            return (string) $value;
        }

        return '';
    }

    private static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (
            is_float($value) &&
            is_finite($value) &&
            floor($value) === $value &&
            $value > 0 &&
            $value <= PHP_INT_MAX
        ) {
            return (int) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if (
                preg_match('/\A[0-9]+\z/D', $value) === 1
            ) {
                $number = filter_var(
                    $value,
                    FILTER_VALIDATE_INT
                );

                return $number !== false &&
                    $number > 0
                        ? $number
                        : null;
            }
        }

        return null;
    }
    // SIGA_ACADEMIA_SHEET_V3
    public function inspectAcademicSheet(string $path): ?array
    {
        $data = $this->reader->readAcademicSheetMetadata($path);

        if ($data === null) {
            return null;
        }

        $errors = [];
        $warnings = [];

        $code = strtoupper(trim($data['codigo_programa'] ?? ''));
        $name = trim($data['nombre_programa'] ?? '');
        $type = strtoupper(trim($data['tipo_cohorte'] ?? ''));
        $numberText = trim($data['numero_generacion'] ?? '');
        $yearText = trim($data['anio_generacion'] ?? '');

        $number = ctype_digit($numberText)
            ? (int) $numberText : null;

        $year = ctype_digit($yearText)
            ? (int) $yearText : null;

        if (preg_match('/^[A-Z0-9]{1,30}$/D', $code) !== 1) {
            $errors[] = 'ACADEMIA_CODIGO_PROGRAMA_INVALIDO';
        }

        if ($name === '' || mb_strlen($name) > 200) {
            $errors[] = 'ACADEMIA_NOMBRE_PROGRAMA_INVALIDO';
        }

        if ($type !== 'G') {
            $errors[] = 'ACADEMIA_TIPO_COHORTE_INVALIDO';
        }

        if ($number === null || $number < 1) {
            $errors[] = 'ACADEMIA_GENERACION_INVALIDA';
        }

        if ($yearText === '') {
            $warnings[] = 'ACADEMIA_ANIO_OFICIAL_PENDIENTE';
        } elseif ($year === null || $year < 1900 || $year > 9999) {
            $errors[] = 'ACADEMIA_ANIO_OFICIAL_INVALIDO';
        }

        $groups = [];
        $seen = [];

        foreach ($data['grupos'] ?? [] as $group) {
            $groupCode = strtoupper(trim($group['codigo'] ?? ''));

            $specialization = mb_strtoupper(
                trim($group['especializacion'] ?? ''),
                'UTF-8'
            );

            $specialization = strtr($specialization, [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I',
                'Ó' => 'O', 'Ú' => 'U',
            ]);

            if (preg_match('/^[A-Z0-9]{1,30}$/D', $groupCode) !== 1) {
                $errors[] = 'ACADEMIA_CODIGO_GRUPO_INVALIDO';
                continue;
            }

            if (isset($seen[$groupCode])) {
                $errors[] = 'ACADEMIA_GRUPO_DUPLICADO';
                continue;
            }

            $seen[$groupCode] = true;

            // Formacion inicial no equivale a Campo ni Gabinete.
            $initialValues = [
                '',
                'FORMACION INICIAL',
                'SIN PERFILACION',
                'SIN ESPECIALIZACION',
            ];

            if (!in_array($specialization, $initialValues, true)) {
                $errors[] =
                    'ACADEMIA_GRUPO_INICIAL_CON_ESPECIALIZACION';
            }

            $groups[] = [
                'codigo' => $groupCode,
                'especializacion' => null,
            ];
        }

        if ($groups === []) {
            $errors[] = 'ACADEMIA_SIN_GRUPOS';
        }

        return [
            'codigo_programa' => $code,
            'nombre_programa' => $name,
            'tipo_cohorte' => $type,
            'numero_generacion' => $number,
            'anio_generacion' => $year,
            'sede_inicial' => $data['sede_inicial'] ?? '',
            'grupos_iniciales' => $groups,
            'errores' => $errors,
            'advertencias' => $warnings,
            'lista_para_registro' =>
                $errors === [] && $warnings === [],
            'persistido' => false,
        ];
    }

}
