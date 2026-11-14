<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Academic\ValidateAcademicRosterXlsx;
use App\Http\Controllers\Controller;
use App\Importing\InvalidXlsxImportSource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AcademicRosterValidationController extends Controller
{
    public function store(
        Request $request,
        ValidateAcademicRosterXlsx $validator
    ): JsonResponse {
        $data = $request->validate([
            'archivo' => [
                'required',
                'file',
                'extensions:xlsx',
                'mimes:xlsx',
                'max:5120',
            ],
            'desgloses' => ['sometimes', 'array', 'max:1000'],
            'desgloses.*.fila_excel' => ['required', 'integer', 'min:2'],
            'desgloses.*.nombres' => ['required', 'string', 'max:150'],
            'desgloses.*.apellido_paterno' => ['nullable', 'string', 'max:100'],
            'desgloses.*.apellido_materno' => ['nullable', 'string', 'max:100'],
        ]);

        $path = $data['archivo']->getRealPath();

        if (!is_string($path) || !is_file($path)) {
            throw ValidationException::withMessages([
                'archivo' => 'No fue posible leer el Excel recibido.',
            ]);
        }

                $desgloses = [];

        foreach ($data['desgloses'] ?? [] as $item) {
            $fila = (int) $item['fila_excel'];

            if (isset($desgloses[$fila])) {
                throw ValidationException::withMessages([
                    'desgloses' => 'La misma fila fue revisada mas de una vez.',
                ]);
            }

            $desgloses[$fila] = [
                'nombres' => $item['nombres'],
                'apellido_paterno' => $item['apellido_paterno'] ?? null,
                'apellido_materno' => $item['apellido_materno'] ?? null,
            ];
        }

        try {
            $result = $validator->inspect($path, true, $desgloses);
        // SIGA_ACADEMIA_SHEET_V3
        $result['academia'] = $validator->inspectAcademicSheet($path);
        } catch (
            DomainException|InvalidXlsxImportSource $exception
        ) {
            throw ValidationException::withMessages([
                'archivo' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'data' => $result,
        ]);
    }
    // SIGA_ORGANIZACION_XLSX_V3
    // Organiza programa, generacion y grupos desde el Excel.
    // No crea Personas, Usuarios, participantes ni matriculas.
    public function organizeAcademicExcel(
        Request $request,
        ValidateAcademicRosterXlsx $validator,
        \App\Importing\Readers\XlsxImportSourceReader $reader,
        \App\Actions\Academic\RegisterAcademicOrganization $organization,
        \App\Actions\Academic\RegisterAcademicSitesGroups $groupService
    ): JsonResponse {
        $input = $request->validate([
            'archivo' => [
                'required', 'file', 'extensions:xlsx',
                'mimes:xlsx', 'max:5120',
            ],
        ]);

        $actor = $request->user();

        abort_unless(
            $actor !== null
            && app(\App\Authorization\PermissionResolver::class)
                ->allows($actor, 'academico.organizacion.registrar'),
            403
        );

        $path = $input['archivo']->getRealPath();

        if (!is_string($path) || !is_file($path)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'No se puede leer el Excel.',
            ]);
        }

        try {
            $academy = $validator->inspectAcademicSheet($path);
            $roster = $validator->inspect($path, true);
        } catch (
            \DomainException|\App\Importing\InvalidXlsxImportSource $error
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => $error->getMessage(),
            ]);
        }

        if (
            $academy === null
            || ($academy['lista_para_registro'] ?? false) !== true
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'La hoja ACADEMIA no esta completa o es invalida.',
            ]);
        }

        $rows = $roster['filas'] ?? [];
        $groups = $academy['grupos_iniciales'] ?? [];

        if ($rows === [] || $groups === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'No se encontraron cadetes o grupos.',
            ]);
        }

        // SIGA_FORMACION_INICIAL_CONTRATO_V1
        // El lector obtiene el dato directamente del Excel.
        // No se requiere momento_academico en cada fila.
        foreach ($reader->read($path) as $excelSource) {
            $raw = $excelSource->rawData;

            $profile = mb_strtoupper(
                trim((string) (
                    $raw['PERFILACIÓN']
                    ?? $raw['PERFILACION']
                    ?? ''
                )),
                'UTF-8'
            );

            $profile = preg_replace(
                '/\s+/u',
                ' ',
                $profile
            ) ?? '';

            $profile = strtr($profile, [
                'Á' => 'A',
                'É' => 'E',
                'Í' => 'I',
                'Ó' => 'O',
                'Ú' => 'U',
                'Ü' => 'U',
            ]);

            if (!in_array($profile, [
                '',
                'FORMACION INICIAL',
                'SIN PERFILACION',
                'SIN ESPECIALIZACION',
            ], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' =>
                        'La fila '
                        . $excelSource->rowNumber
                        . ' no pertenece a formacion inicial.',
                ]);
            }
        }
        $allowedGroups = [];

        foreach ($groups as $group) {
            $code = (string) ($group['codigo'] ?? '');

            if (
                $code === ''
                || isset($allowedGroups[$code])
                || ($group['especializacion'] ?? null) !== null
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => 'Los grupos iniciales son invalidos.',
                ]);
            }

            $allowedGroups[$code] = true;
        }

        foreach ($rows as $row) {
            $generation = strtoupper(
                preg_replace(
                    '/\s+/u',
                    '',
                    (string) ($row['generacion'] ?? '')
                ) ?? ''
            );

            $validGeneration = preg_match(
                '/^0*([0-9]{1,3})[A-Z]{0,4}$/D',
                $generation,
                $parts
            ) === 1;

            if (
                ($row['errores'] ?? []) !== []
                || !empty($row['requiere_revision_nombre'])
                || !isset(
                    $allowedGroups[(string) ($row['grupo'] ?? '')]
                )
                || !$validGeneration
                || (int) $parts[1] !==
                    (int) $academy['numero_generacion']
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => 'Las filas no coinciden con la formacion inicial de ACADEMIA.',
                ]);
            }
        }

        return \Illuminate\Support\Facades\DB::transaction(
            function () use (
                $actor, $academy, $groups, $rows,
                $organization, $groupService
            ): JsonResponse {
                $db = \Illuminate\Support\Facades\DB::connection();

                $database = $db->selectOne(
                    'SELECT current_database() AS nombre'
                );

                if (($database->nombre ?? null) !== 'siga') {
                    throw new \RuntimeException(
                        'Solo se permite la base institucional siga.'
                    );
                }

                $code = (string) $academy['codigo_programa'];
                $name = (string) $academy['nombre_programa'];
                $type = (string) $academy['tipo_cohorte'];
                $number = (int) $academy['numero_generacion'];
                $year = (int) $academy['anio_generacion'];

                $db->selectOne(
                    'SELECT pg_advisory_xact_lock(524876, hashtext(CAST(? AS text)))',
                    [$code]
                );

                $program = $db->table(
                    'institutional.academic_programs'
                )->where('codigo', $code)->first();

                if ($program !== null) {
                    if (trim((string) $program->nombre) !== $name) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'archivo' => 'El programa existente tiene otro nombre.',
                        ]);
                    }

                    $programId = (string) $program->id_programa;
                } else {
                    $created = $organization->program(
                        $actor, $code, $name
                    );

                    $programId = (string) $created['id'];
                }

                $cohort = $db->table(
                    'institutional.academic_cohorts'
                )
                    ->where('id_programa', $programId)
                    ->where('tipo', $type)
                    ->where('numero', $number)
                    ->where('anio', $year)
                    ->first();

                if ($cohort !== null) {
                    $cohortId = (string) $cohort->id_cohorte;
                } else {
                    $created = $organization->cohort(
                        $actor, $programId, $type, $number, $year
                    );

                    $cohortId = (string) $created['id'];
                }

                $resolved = [];

                foreach ($groups as $group) {
                    $groupCode = (string) $group['codigo'];

                    $existing = $db->table(
                        'institutional.academic_groups'
                    )
                        ->where('id_cohorte', $cohortId)
                        ->where('codigo', $groupCode)
                        ->first();

                    if ($existing !== null) {
                        if ($existing->especializacion !== null) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'archivo' => 'El grupo existente ya tiene especializacion.',
                            ]);
                        }

                        $groupId = (string) $existing->id_grupo;
                    } else {
                        $created = $groupService->group(
                            $actor, $cohortId, $groupCode, null
                        );

                        $groupId = (string) $created['id'];
                    }

                    $resolved[] = [
                        'codigo' => $groupCode,
                        'id_grupo' => $groupId,
                        'especializacion' => null,
                    ];
                }

                return response()->json([
                    'data' => [
                        'id_programa' => $programId,
                        'codigo_programa' => $code,
                        'id_cohorte' => $cohortId,
                        'numero_generacion' => $number,
                        'anio_generacion' => $year,
                        'grupos' => $resolved,
                        'cadetes_validados' => count($rows),
                        'personas_creadas' => 0,
                        'usuarios_creados' => 0,
                        'incorporaciones_creadas' => 0,
                    ],
                ]);
            }
        );
    }

    // SIGA_ALTA_CADETES_XLSX_V1
    // Registro academico autorizado. No crea Usuarios ni roles.
    public function incorporateCadetsExcel(
        Request $request,
        ValidateAcademicRosterXlsx $validator,
        \App\Importing\Readers\XlsxImportSourceReader $reader,
        \App\Actions\Persona\RegisterPerson $registerPerson,
        \App\Actions\Academic\RegisterAcademicIdentity $registerAcademic
    ): JsonResponse {
        // SIGA_PREVIEW_SIN_COHORTE_V1
        // Revisión preliminar sin cohorte, sin altas ni cambios.
        // No altera la rama institucional de CONFIRMAR.
        if (
            $request->input('modo') === 'PREVISUALIZAR'
            && !$request->filled('id_cohorte')
        ) {
            $previewInput = $request->validate([
                'archivo' => [
                    'required', 'file', 'extensions:xlsx',
                    'mimes:xlsx', 'max:5120',
                ],
                'modo' => ['required', 'in:PREVISUALIZAR'],
                'confirmacion' => ['prohibited'],
            ]);

            $actor = $request->user();
            abort_unless($actor !== null, 401);

            foreach ([
                'personas.crear',
                'academico.participantes.registrar',
            ] as $permission) {
                abort_unless(
                    app(\App\Authorization\PermissionResolver::class)
                        ->allows($actor, $permission),
                    403
                );
            }

            $path = $previewInput['archivo']->getRealPath();

            if (!is_string($path) || !is_file($path)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => 'No fue posible leer el archivo Excel.',
                ]);
            }

            return \Illuminate\Support\Facades\DB::transaction(
                function () use ($validator, $path): JsonResponse {
                    $db = \Illuminate\Support\Facades\DB::connection();

                    // PostgreSQL impide las escrituras en esta transaccion.
                    $db->statement('SET TRANSACTION READ ONLY');

                    $database = $db->selectOne(
                        'SELECT current_database() AS nombre'
                    );

                    if (($database->nombre ?? null) !== 'siga') {
                        throw new \RuntimeException(
                            'La previsualizacion requiere la base institucional siga.'
                        );
                    }

                    try {
                        $academy = $validator->inspectAcademicSheet($path);
                        $roster = $validator->inspect($path, true);
                    } catch (
                        \DomainException|\App\Importing\InvalidXlsxImportSource $error
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'archivo' => $error->getMessage(),
                        ]);
                    }

                    if (!is_array($academy)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'archivo' => 'No se encontro una hoja ACADEMIA valida.',
                        ]);
                    }

                    $summary = $roster['summary'] ?? [];
                    $rows = $roster['filas'] ?? [];

                    if (!is_array($rows) || count($rows) > 1000) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'archivo' => 'El listado supera el limite permitido.',
                        ]);
                    }

                    // No exponer nombres, RFC, CURP ni identificadores personales.
                    $safeRows = [];

                    foreach ($rows as $row) {
                        $warnings = [];

                        foreach ($row['advertencias'] ?? [] as $warning) {
                            $warnings[] = is_scalar($warning)
                                ? (string) $warning
                                : 'Observacion de validacion';
                        }

                        $safeRows[] = [
                            'fila_excel' => (int) ($row['fila_excel'] ?? 0),
                            'grupo' => (string) (
                                $row['grupo'] ?? $row['numero_grupo'] ?? ''
                            ),
                            'estado' => (string) ($row['estado'] ?? ''),
                            'advertencias' => $warnings,
                        ];
                    }

                    $groups = array_map(
                        static fn (array $group): string =>
                            (string) ($group['codigo'] ?? ''),
                        $academy['grupos_iniciales'] ?? []
                    );

                    return response()->json([
                        'data' => [
                            'estado' => 'PREVISUALIZADO_SIN_COHORTE',
                            'filas_procesadas' => count($safeRows),
                            'resumen' => [
                                'total' => (int) ($summary['total'] ?? 0),
                                'validos' => (int) ($summary['validos'] ?? 0),
                                'con_observaciones' => (int) (
                                    $summary['con_observaciones'] ?? 0
                                ),
                                'con_errores' => (int) (
                                    $summary['con_errores'] ?? 0
                                ),
                                'nombres_pendientes_revision' => (int) (
                                    $summary['nombres_pendientes_revision'] ?? 0
                                ),
                            ],
                            'academia' => [
                                'numero_generacion' =>
                                    $academy['numero_generacion'] ?? null,
                                'anio_generacion' =>
                                    $academy['anio_generacion'] ?? null,
                                'lista_para_registro' =>
                                    (bool) ($academy['lista_para_registro'] ?? false),
                            ],
                            'grupos' => $groups,
                            'conciliacion' => $roster['conciliacion'] ?? null,
                            'errores_academia' => $academy['errores'] ?? [],
                            'advertencias_academia' =>
                                $academy['advertencias'] ?? [],
                            'filas' => $safeRows,
                            'confirmacion_habilitada' => false,
                            'persistido' => false,
                        ],
                    ]);
                }
            );
        }

        $input = $request->validate([
            'archivo' => [
                'required', 'file', 'extensions:xlsx',
                'mimes:xlsx', 'max:5120',
            ],
            'id_cohorte' => ['required', 'uuid'],
            'modo' => ['sometimes', 'in:PREVISUALIZAR,CONFIRMAR'],
            'confirmacion' => [
                'required_unless:modo,PREVISUALIZAR', 'in:INCORPORAR_CADETES_REALES',
            ],
        ]);

        $previewOnly = ($input['modo'] ?? '') === 'PREVISUALIZAR';

        $actor = $request->user();

        abort_unless($actor !== null, 401);

        foreach ([
            'personas.crear',
            'academico.participantes.registrar',
        ] as $permission) {
            abort_unless(
                app(\App\Authorization\PermissionResolver::class)
                    ->allows($actor, $permission),
                403
            );
        }

        $file = $input['archivo'];

        if (preg_match(
            '/ejempl|fictici|plantilla|demo|prueba/i',
            $file->getClientOriginalName()
        ) === 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'No se admiten archivos identificados como ejemplos o pruebas.',
            ]);
        }

        $path = $file->getRealPath();

        if (!is_string($path) || !is_file($path)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'No fue posible leer el archivo.',
            ]);
        }

        try {
            $academy = $validator->inspectAcademicSheet($path);
            $preview = $validator->inspect($path, true);
        } catch (
            \DomainException|\App\Importing\InvalidXlsxImportSource $error
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => $error->getMessage(),
            ]);
        }

        $rows = $preview['filas'] ?? [];
        $summary = $preview['summary'] ?? [];

        if (
            !is_array($academy)
            || ($academy['lista_para_registro'] ?? false) !== true
            || ($summary['con_errores'] ?? 1) !== 0
            || ($summary['nombres_pendientes_revision'] ?? 1) !== 0
            || !is_array($rows)
            || $rows === []
            || count($rows) > 1000
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'El Excel debe estar completamente validado.',
            ]);
        }

        $normalize = static function (mixed $value): string {
            $value = preg_replace(
                '/\s+/u',
                ' ',
                trim((string) ($value ?? ''))
            ) ?? '';

            return mb_strtoupper($value, 'UTF-8');
        };

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

        foreach ($reader->read($path) as $source) {
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
                'nombres' => $names,
                'apellido_paterno' => $paternal ?: null,
                'apellido_materno' => $maternal ?: null,
                'grupo' => $groupCode,
                'matricula' => trim(
                    (string) ($row['matricula'] ?? '')
                ),
            ];
        }

        return \Illuminate\Support\Facades\DB::transaction(
            function () use (
                $actor, $academy, $input, $prepared,
                $normalize, $registerPerson, $registerAcademic, $previewOnly
            ): JsonResponse {
                $db = \Illuminate\Support\Facades\DB::connection();

                if ($previewOnly) {
                    // SIGA_PREVISUALIZAR_CADETES_V1
                    // Prohibe escrituras dentro de esta transaccion.
                    $db->statement('SET TRANSACTION READ ONLY');
                }

                $database = $db->selectOne(
                    'SELECT current_database() AS nombre'
                );

                if (($database->nombre ?? null) !== 'siga') {
                    throw new \RuntimeException(
                        'Solo se admite la base institucional siga.'
                    );
                }

                $cohortId = (string) $input['id_cohorte'];

                $db->selectOne(
                    'SELECT pg_advisory_xact_lock(524878, hashtext(CAST(? AS text)))',
                    [$cohortId]
                );

                $cohort = $db->table(
                    'institutional.academic_cohorts as c'
                )
                    ->join(
                        'institutional.academic_programs as p',
                        'p.id_programa', '=', 'c.id_programa'
                    )
                    ->where('c.id_cohorte', $cohortId)
                    ->first([
                        'c.tipo',
                        'c.numero',
                        'c.anio',
                        'p.codigo as codigo_programa',
                    ]);

                if (
                    $cohort === null
                    || $cohort->tipo !== 'G'
                    || $cohort->codigo_programa
                        !== $academy['codigo_programa']
                    || (int) $cohort->numero
                        !== (int) $academy['numero_generacion']
                    || (int) $cohort->anio
                        !== (int) $academy['anio_generacion']
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'id_cohorte' => 'La cohorte no coincide con el Excel validado.',
                    ]);
                }

                $groups = $db->table(
                    'institutional.academic_groups'
                )
                    ->where('id_cohorte', $cohortId)
                    ->get([
                        'id_grupo',
                        'codigo',
                        'especializacion',
                    ])
                    ->keyBy('codigo');

                $resolved = [];

                // Resolver TODAS las identidades antes de crear alguna.
                foreach ($prepared as $row) {
                    $group = $groups->get($row['grupo']);

                    if (
                        $group === null
                        || $group->especializacion !== null
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'archivo' => "Grupo no disponible en fila {$row['fila_excel']}.",
                        ]);
                    }

                    $person = $db->table(
                        'institutional.persons'
                    )
                        ->where('rfc', $row['rfc'])
                        ->first([
                            'id_persona',
                            'estatus',
                            'nombres',
                            'apellido_paterno',
                            'apellido_materno',
                        ]);

                    if ($person !== null) {
                        if (
                            $person->estatus !== 'ACTIVO'
                            || $normalize($person->nombres)
                                !== $row['nombres']
                            || $normalize($person->apellido_paterno)
                                !== ($row['apellido_paterno'] ?? '')
                            || $normalize($person->apellido_materno)
                                !== ($row['apellido_materno'] ?? '')
                        ) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'archivo' => "Identidad institucional contradictoria en fila {$row['fila_excel']}.",
                            ]);
                        }
                    } else {
                        $sameName = $db->table(
                            'institutional.persons'
                        )
                            ->whereRaw(
                                'UPPER(BTRIM(nombres)) = ?',
                                [$row['nombres']]
                            )
                            ->where(function ($q) use ($row) {
                                if ($row['apellido_paterno'] === null) {
                                    $q->whereNull('apellido_paterno');
                                } else {
                                    $q->whereRaw(
                                        'UPPER(BTRIM(apellido_paterno)) = ?',
                                        [$row['apellido_paterno']]
                                    );
                                }
                            })
                            ->where(function ($q) use ($row) {
                                if ($row['apellido_materno'] === null) {
                                    $q->whereNull('apellido_materno');
                                } else {
                                    $q->whereRaw(
                                        'UPPER(BTRIM(apellido_materno)) = ?',
                                        [$row['apellido_materno']]
                                    );
                                }
                            })
                            ->exists();

                        if ($sameName) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'archivo' => "Posible Persona preexistente en fila {$row['fila_excel']}.",
                            ]);
                        }
                    }

                    $resolved[] = [
                        'row' => $row,
                        'id_persona' => $person?->id_persona,
                        'id_grupo' => (string) $group->id_grupo,
                    ];
                }

                if ($previewOnly) {
                    $newPeople = 0;
                    $existingPeople = 0;
                    $rowResults = [];

                    foreach ($resolved as $item) {
                        $isNew = $item['id_persona'] === null;

                        if ($isNew) {
                            $newPeople++;
                        } else {
                            $existingPeople++;
                        }

                        $rowResults[] = [
                            'fila_excel' => $item['row']['fila_excel'],
                            'resultado' => $isNew
                                ? 'PERSONA_NUEVA_PREVISTA'
                                : 'PERSONA_EXISTENTE_PREVISTA',
                        ];
                    }

                    // No retorna RFC, CURP, UUID de Persona
                    // ni informacion de credenciales.
                    return response()->json([
                        'data' => [
                            'estado' => 'PREVISUALIZADO',
                            'id_cohorte' => $cohortId,
                            'filas_procesadas' => count($prepared),
                            'personas_nuevas_previstas' => $newPeople,
                            'personas_reutilizables_previstas' => $existingPeople,
                            'filas' => $rowResults,
                            'personas_creadas' => 0,
                            'usuarios_creados' => 0,
                            'incorporaciones_creadas' => 0,
                        ],
                    ]);
                }

                $created = 0;
                $reused = 0;
                $confirmation = [];

                foreach ($resolved as $item) {
                    $row = $item['row'];
                    $personId = $item['id_persona'];

                    if ($personId === null) {
                        $person = $registerPerson->execute(
                            (int) $actor->getKey(),
                            [
                                'rfc' => $row['rfc'],
                                'nombres' => $row['nombres'],
                                'apellido_paterno' => $row['apellido_paterno'],
                                'apellido_materno' => $row['apellido_materno'],
                            ],
                            'MASIVO'
                        );

                        $personId = (string) $person->getKey();
                        $created++;
                    } else {
                        $reused++;
                    }

                    $confirmation[] = [
                        'id_persona' => $personId,
                        'id_grupo' => $item['id_grupo'],
                        'matricula_historica' => $row['matricula'] ?: null,
                    ];
                }

                $incorporations = $registerAcademic
                    ->confirmIncorporations(
                        $actor,
                        $cohortId,
                        $confirmation
                    );

                return response()->json([
                    'data' => [
                        'id_cohorte' => $cohortId,
                        'filas_procesadas' => count($prepared),
                        'personas_creadas' => $created,
                        'personas_reutilizadas' => $reused,
                        'incorporaciones' => $incorporations,
                        'usuarios_creados' => 0,
                        'roles_asignados' => 0,
                    ],
                ], 201);
            }
        );
    }

}
