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

        // SIGA_GUARD_ORGANIZAR_EJEMPLOS_V1
        // Un archivo identificado como ejemplo solo puede previsualizarse.
        $originalName = $input['archivo']->getClientOriginalName();

        if (preg_match(
            '/ejempl|fictici|plantilla|demo|prueba/i',
            $originalName
        ) === 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' => 'No se permite registrar organizacion academica desde archivos de ejemplo.',
            ]);
        }
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
        foreach ($reader->read(
            $path,
            ['No.'],
            ['PERFILACIÓN', 'PERFILACION']
        ) as $excelSource) {
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

    // SIGA_C1_DELEGACION_INCORPORACION_XLSX_V1
    public function incorporateCadetsExcel(
        Request $request,
        ValidateAcademicRosterXlsx $validator,
        \App\Importing\Readers\XlsxImportSourceReader $reader,
        \App\Actions\Persona\RegisterPerson $registerPerson,
        \App\Actions\Academic\RegisterAcademicIdentity $registerAcademic,
        \App\Actions\User\CreateUser $createUser,
        \App\Actions\User\ManageUserProfiles $manageProfiles,
        \App\Actions\Persona\UpdatePerson $updatePerson
    ): JsonResponse {
        return app(\App\Actions\Academic\IncorporateCadetsExcel::class)
            ->execute(
                $request,
                $validator,
                $reader,
                $registerPerson,
                $registerAcademic,
                $createUser,
                $manageProfiles,
                $updatePerson
            );
    }

}
