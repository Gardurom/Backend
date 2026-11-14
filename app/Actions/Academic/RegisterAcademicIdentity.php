<?php

namespace App\Actions\Academic;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAcademicIdentity
{
    public function execute(User $actor, string $type, string $personId): object
    {
        [$table, $key, $permission] = match ($type) {
            'PARTICIPANTE' => [
                'institutional.academic_participants',
                'id_participante',
                'academico.participantes.registrar',
            ],
            'FORMADOR' => [
                'institutional.academic_trainers',
                'id_formador',
                'academico.formadores.registrar',
            ],
            default => throw new \InvalidArgumentException('Tipo académico inválido.'),
        };

        abort_unless(
            app(PermissionResolver::class)->allows($actor, $permission),
            403
        );

        return DB::transaction(function () use ($actor, $type, $personId, $table, $key) {
            // Serializa registros de la misma Persona y sus cambios de estado.
            $person = DB::table('institutional.persons')
                ->where('id_persona', $personId)
                ->lockForUpdate()
                ->first(['id_persona', 'estatus']);

            if ($person === null) {
                throw ValidationException::withMessages([
                    'id_persona' => 'La Persona seleccionada no existe.',
                ]);
            }

            if ($person->estatus !== 'ACTIVO') {
                throw ValidationException::withMessages([
                    'id_persona' => 'El registro académico inicial requiere una Persona ACTIVA.',
                ]);
            }

            if (DB::table($table)->where('id_persona', $personId)->exists()) {
                throw ValidationException::withMessages([
                    'id_persona' => 'La Persona ya tiene este registro académico.',
                ]);
            }

            $id = DB::table($table)->insertGetId(
                ['id_persona' => $personId],
                $key
            );

            DB::table('system.activities')->insert([
                'id_usuario' => (int) $actor->getKey(),
                'entidad' => 'ACADEMICO_'.$type,
                'id_entidad' => (string) $id,
                'accion' => 'CREACION',
                'datos_anteriores' => null,
                'datos_nuevos' => json_encode([
                    'id_persona' => $personId,
                    'tipo_registro' => $type,
                ], JSON_THROW_ON_ERROR),
                'campos_modificados' => json_encode(
                    ['id_persona'],
                    JSON_THROW_ON_ERROR
                ),
            ]);

            return DB::table($table)
                ->where($key, $id)
                ->firstOrFail();
        });
    }

    /**
     * Confirma un lote de incorporaciones dentro de una transaccion.
     * No crea Usuarios ni asigna roles.
     */
    public function confirmIncorporations(
        User $actor,
        string $cohortId,
        array $rows
    ): array {
        abort_unless(
            app(PermissionResolver::class)->allows(
                $actor,
                'academico.participantes.registrar'
            ),
            403
        );

        if ($rows === [] || count($rows) > 1000) {
            throw ValidationException::withMessages([
                'filas' => 'Se requieren entre 1 y 1000 registros.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $cohortId,
            $rows
        ): array {
            // Serializa las emisiones por cohorte durante la transaccion.
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(524874, hashtext(CAST(? AS text)))',
                [$cohortId]
            );

            $cohort = DB::table('institutional.academic_cohorts as c')
                ->join(
                    'institutional.academic_programs as p',
                    'p.id_programa',
                    '=',
                    'c.id_programa'
                )
                ->where('c.id_cohorte', $cohortId)
                ->first([
                    'c.id_cohorte',
                    'c.tipo',
                    'c.numero',
                    'c.anio',
                    'p.codigo as codigo_programa',
                ]);

            if ($cohort === null || $cohort->tipo !== 'G') {
                throw ValidationException::withMessages([
                    'id_cohorte' => 'Generacion academica no valida.',
                ]);
            }

            $requested = [];

            foreach ($rows as $row) {
                $personId = (string) $row['id_persona'];

                if (isset($requested[$personId])) {
                    throw ValidationException::withMessages([
                        'filas' => 'La misma Persona aparece mas de una vez.',
                    ]);
                }

                $requested[$personId] = [
                    'id_grupo' => (string) $row['id_grupo'],
                    'matricula_historica' => isset($row['matricula_historica'])
                        ? trim((string) $row['matricula_historica'])
                        : null,
                ];
            }

            $groupIds = array_values(array_unique(
                array_column($requested, 'id_grupo')
            ));

            $groupCount = DB::table('institutional.academic_groups')
                ->where('id_cohorte', $cohortId)
                ->whereIn('id_grupo', $groupIds)
                ->count();

            if ($groupCount !== count($groupIds)) {
                throw ValidationException::withMessages([
                    'filas' => 'Un grupo no pertenece a la generacion.',
                ]);
            }

            $people = DB::table('institutional.persons')
                ->whereIn('id_persona', array_keys($requested))
                ->orderByRaw("COALESCE(apellido_paterno, '')")
                ->orderByRaw("COALESCE(apellido_materno, '')")
                ->orderBy('nombres')
                ->orderBy('id_persona')
                ->get([
                    'id_persona',
                    'estatus',
                ]);

            if ($people->count() !== count($requested)) {
                throw ValidationException::withMessages([
                    'filas' => 'Existen Personas no registradas.',
                ]);
            }

            $next = (int) (
                DB::table('institutional.academic_incorporations')
                    ->where('id_cohorte', $cohortId)
                    ->max('consecutivo') ?? 0
            );

            $reserved = [];
            $confirmed = [];

            foreach ($people as $person) {
                if ($person->estatus !== 'ACTIVO') {
                    throw ValidationException::withMessages([
                        'filas' => 'Una Persona no se encuentra ACTIVA.',
                    ]);
                }

                $personId = (string) $person->id_persona;
                $data = $requested[$personId];

                $participant = DB::table(
                    'institutional.academic_participants'
                )
                    ->where('id_persona', $personId)
                    ->first(['id_participante']);

                if ($participant === null) {
                    $participant = $this->execute(
                        $actor,
                        'PARTICIPANTE',
                        $personId
                    );
                }

                $participantId = (string) $participant->id_participante;

                                // SIGA_INCORPORACION_IDEMPOTENTE
                $existing = DB::table(
                    'institutional.academic_incorporations'
                )
                    ->where('id_participante', $participantId)
                    ->where('id_cohorte', $cohortId)
                    ->first([
                        'id_incorporacion',
                        'id_grupo',
                        'matricula',
                    ]);

                if ($existing !== null) {
                    $historical = trim(
                        (string) ($data['matricula_historica'] ?? '')
                    );

                    if (
                        (string) $existing->id_grupo !== $data['id_grupo']
                        || (
                            $historical !== ''
                            && strcasecmp(
                                (string) $existing->matricula,
                                $historical
                            ) !== 0
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'filas' => 'La incorporacion existente tiene datos diferentes.',
                        ]);
                    }

                    $confirmed[] = [
                        'id_incorporacion' => (string) $existing->id_incorporacion,
                        'id_persona' => $personId,
                        'id_grupo' => (string) $existing->id_grupo,
                        'matricula' => (string) $existing->matricula,
                        'estado' => 'EXISTENTE',
                    ];

                    continue;
                }

                // El reingreso requiere resolucion administrativa individual.
                if (
                    DB::table('institutional.academic_incorporations')
                        ->where('id_participante', $participantId)
                        ->where('id_cohorte', '<>', $cohortId)
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'filas' => 'El reingreso a otra generacion requiere resolucion administrativa individual.',
                    ]);
                }

                $historical = $data['matricula_historica'];

                if ($historical !== null && $historical !== '') {
                    $matricula = $historical;
                    $sequence = null;
                } else {
                    do {
                        $next++;

                        $matricula =
                            \App\Domain\Academic\AcademicEnrollmentCode::format(
                                (string) $cohort->codigo_programa,
                                'G'.(string) $cohort->numero,
                                (int) $cohort->anio,
                                'C',
                                $next
                            );

                        $key = mb_strtoupper($matricula);
                    } while (
                        isset($reserved[$key]) ||
                        DB::table('institutional.academic_incorporations')
                            ->whereRaw('UPPER(matricula) = ?', [$key])
                            ->exists()
                    );

                    $sequence = $next;
                }

                $key = mb_strtoupper($matricula);

                if (
                    isset($reserved[$key]) ||
                    DB::table('institutional.academic_incorporations')
                        ->whereRaw('UPPER(matricula) = ?', [$key])
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'filas' => 'Matricula institucional duplicada.',
                    ]);
                }

                $reserved[$key] = true;

                $incorporationId = DB::table(
                    'institutional.academic_incorporations'
                )->insertGetId([
                    'id_participante' => $participantId,
                    'id_cohorte' => $cohortId,
                    'id_grupo' => $data['id_grupo'],
                    'matricula' => $matricula,
                    'consecutivo' => $sequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id_incorporacion');

                DB::table('system.activities')->insert([
                    'id_usuario' => (int) $actor->getKey(),
                    'entidad' => 'ACADEMICO_INCORPORACION',
                    'id_entidad' => (string) $incorporationId,
                    'accion' => 'CREACION',
                    'datos_anteriores' => null,
                    'datos_nuevos' => json_encode([
                        'id_persona' => $personId,
                        'id_participante' => $participantId,
                        'id_cohorte' => $cohortId,
                        'id_grupo' => $data['id_grupo'],
                        'matricula' => $matricula,
                        'origen_matricula' => $sequence === null
                            ? 'HISTORICA'
                            : 'EMITIDA',
                    ], JSON_THROW_ON_ERROR),
                    'campos_modificados' => json_encode([
                        'id_participante',
                        'id_cohorte',
                        'id_grupo',
                        'matricula',
                    ], JSON_THROW_ON_ERROR),
                ]);

                $confirmed[] = [
                    'id_incorporacion' => (string) $incorporationId,
                    'id_persona' => $personId,
                    'id_grupo' => $data['id_grupo'],
                    'matricula' => $matricula,
                ];
            }

            return [
                'total' => count($confirmed),
                'incorporaciones' => $confirmed,
            ];
        }, 3);
    }
}
