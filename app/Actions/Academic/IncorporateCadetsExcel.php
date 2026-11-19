<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// SIGA_C1_MODULO_INCORPORACION_XLSX_V1
// El controlador delega; se preserva el contrato actual.
final class IncorporateCadetsExcel
{
    // SIGA_ALTA_CADETES_XLSX_V1
    // Registro autorizado de Persona, incorporacion y Usuario pendiente.
    // Asigna PERFIL_CADETE, pero no crea roles ni habilita accesos.
    public function execute(
        Request $request,
        ValidateAcademicRosterXlsx $validator,
        \App\Importing\Readers\XlsxImportSourceReader $reader,
        \App\Actions\Persona\RegisterPerson $registerPerson,
        \App\Actions\Academic\RegisterAcademicIdentity $registerAcademic,
        \App\Actions\User\CreateUser $createUser,
        \App\Actions\User\ManageUserProfiles $manageProfiles,
        \App\Actions\Persona\UpdatePerson $updatePerson
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
            'modo' => ['required', 'in:PREVISUALIZAR,CONFIRMAR'],
            'confirmacion' => [
                'required_if:modo,CONFIRMAR', 'prohibited_if:modo,PREVISUALIZAR', 'in:INCORPORAR_CADETES_REALES',
            ],
        ]);

        $previewOnly = ($input['modo'] ?? '') === 'PREVISUALIZAR';

        // SIGA_CADETES_CONFIRMED_REAUTH_V1
        // La previsualizacion no requiere reautenticacion.
        // La confirmacion real si la requiere.
        if (! $previewOnly) {
            $reauthResponse = app(
                \App\Http\Middleware\EnsureRecentReauthentication::class
            )->handle(
                $request,
                static fn (Request $request) => response()->noContent()
            );

            if ($reauthResponse->getStatusCode() !== 204) {
                return response()->json([
                    'message' => 'Reauthentication required.',
                ], 423);
            }
        }

        $actor = $request->user();

        abort_unless($actor !== null, 401);

        // SIGA_CADETE_USUARIO_ATOMICO_V1
        // La previsualizacion nunca crea cuentas.
        if (! $previewOnly) {
            foreach ([
                'usuarios.crear',
                'usuarios.asignar_perfiles',
            ] as $permission) {
                abort_unless(
                    app(\App\Authorization\PermissionResolver::class)
                        ->allows($actor, $permission),
                    403
                );
            }
        }

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

        // SIGA_CADETES_EJEMPLOS_PREVIEW_V1
        // Los archivos de ejemplo solo pueden previsualizarse.
        // CONFIRMAR conserva obligatoriamente su bloqueo.
        if (! $previewOnly && preg_match(
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

        $prepared = app(\App\Actions\Academic\PrepareCadetIncorporationRows::class)
            ->execute(
                $reader,
                $path,
                $rows,
                $academy,
                $normalize
            );

        return \Illuminate\Support\Facades\DB::transaction(
            function () use (
                $actor, $academy, $input, $prepared,
                $normalize, $registerPerson, $registerAcademic, $previewOnly,
                $createUser, $manageProfiles, $updatePerson
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

                $resolved = app(\App\Actions\Academic\ResolveCadetIncorporationIdentities::class)
                    ->execute(
                        $db,
                        $prepared,
                        $groups,
                        $normalize,
                        $previewOnly,
                        $actor
                    );

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
                            'usuarios_nuevos_previstos' => count(array_filter(
                                $resolved,
                                static fn (array $item): bool =>
                                    $item['user_id'] === null
                                    && $item['email'] !== ''
                            )),
                            'filas_sin_correo_para_cuenta' => count(array_filter(
                                $resolved,
                                static fn (array $item): bool =>
                                    $item['user_id'] === null
                                    && $item['email'] === ''
                            )),
                            'personas_correo_por_actualizar' => count(array_filter(
                                $resolved,
                                static fn (array $item): bool =>
                                    $item['actualizar_correo_persona']
                            )),
                            'filas' => $rowResults,
                            'personas_creadas' => 0,
                            'usuarios_creados' => 0,
                            'incorporaciones_creadas' => 0,
                        ],
                    ]);
                }

                $confirmationResult = app(\App\Actions\Academic\ConfirmCadetIncorporation::class)
                    ->execute(
                        $db,
                        $actor,
                        $cohortId,
                        $prepared,
                        $resolved,
                        $registerPerson,
                        $registerAcademic,
                        $createUser,
                        $manageProfiles,
                        $updatePerson
                    );

                return response()->json($confirmationResult, 201);
            }
        );
    }

}
