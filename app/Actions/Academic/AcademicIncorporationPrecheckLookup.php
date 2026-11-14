<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use Illuminate\Support\Facades\DB;

/**
 * Consulta existente de Persona, participante y cohorte.
 * No utiliza ni modifica propuestas C1/C9.
 */
class AcademicIncorporationPrecheckLookup
{
    public function find(
        string $participantId,
        string $cohortId
    ): ?object {
        return DB::selectOne(<<<'SQL'
SELECT
    p.id_participante,
    p.id_persona,
    persona.estatus AS estatus_persona,
    c.id_cohorte,
    c.tipo AS tipo_cohorte,
    c.numero AS numero_cohorte,
    c.anio,
    programa.codigo AS codigo_programa
FROM institutional.academic_participants p
JOIN institutional.persons persona
    ON persona.id_persona = p.id_persona
CROSS JOIN institutional.academic_cohorts c
JOIN institutional.academic_programs programa
    ON programa.id_programa = c.id_programa
WHERE p.id_participante = CAST(? AS uuid)
  AND c.id_cohorte = CAST(? AS uuid)
SQL, [
            $participantId,
            $cohortId,
        ]);
    }
    /**
     * Conciliacion preliminar, exclusivamente de lectura.
     * Una coincidencia por nombre nunca autoriza una vinculacion.
     */
    public function reconcileRoster(array $rows): array
    {
        if (count($rows) > 1000) {
            throw new \DomainException(
                'Maximo 1000 registros por archivo.'
            );
        }

        $database = DB::selectOne(
            'SELECT current_database() AS nombre'
        );

        if (($database->nombre ?? null) !== 'siga') {
            throw new \RuntimeException(
                'La conciliacion requiere la base institucional siga.'
            );
        }

        $normalize = static function (mixed $value): string {
            $text = trim((string) ($value ?? ''));

            return mb_strtoupper(trim(
                preg_replace('/\s+/u', ' ', $text) ?? $text
            ));
        };

        $rfcs = [];
        $names = [];

        foreach ($rows as $row) {
            $rfc = $normalize($row['rfc'] ?? '');
            $name = $normalize($row['nombre'] ?? '');

            if ($rfc !== '') {
                $rfcs[$rfc] = true;
            }

            if ($name !== '') {
                $names[$name] = true;
            }
        }

        $people = collect();

        if ($rfcs !== [] || $names !== []) {
            $people = DB::table('institutional.persons')
                ->select([
                    'id_persona',
                    'estatus',
                    'rfc',
                    'nombres',
                    'apellido_paterno',
                    'apellido_materno',
                ])
                ->where(function ($query) use ($rfcs, $names): void {
                    if ($rfcs !== []) {
                        $query->whereIn(
                            DB::raw('UPPER(BTRIM(rfc))'),
                            array_keys($rfcs)
                        );
                    }

                    if ($names !== []) {
                        $method = $rfcs === []
                            ? 'whereIn'
                            : 'orWhereIn';

                        $query->{$method}(
                            DB::raw(
                                "UPPER(REGEXP_REPLACE(BTRIM(CONCAT_WS(' ', apellido_paterno, apellido_materno, nombres)), '[[:space:]]+', ' ', 'g'))"
                            ),
                            array_keys($names)
                        );
                    }
                })
                ->get();
        }

        $byRfc = [];
        $byName = [];

        foreach ($people as $person) {
            $name = $normalize(implode(' ', array_filter([
                $person->apellido_paterno,
                $person->apellido_materno,
                $person->nombres,
            ], static fn ($value) =>
                $value !== null && trim((string) $value) !== ''
            )));

            $entry = [
                'id' => (string) $person->id_persona,
                'nombre' => $name,
                'estatus' => (string) $person->estatus,
            ];

            $rfc = $normalize($person->rfc);

            if ($rfc !== '') {
                $byRfc[$rfc][] = $entry;
            }

            if ($name !== '') {
                $byName[$name][] = $entry;
            }
        }

        $results = [];
        $matched = [];

        foreach ($rows as $row) {
            $name = $normalize($row['nombre'] ?? '');
            $rfc = $normalize($row['rfc'] ?? '');
            $personId = null;

            $rfcMatches = $rfc !== ''
                ? ($byRfc[$rfc] ?? [])
                : [];

            if (
                $rfc !== '' &&
                preg_match(
                    '/\A[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}\z/uD',
                    $rfc
                ) !== 1
            ) {
                $status = 'REVISION_RFC_INVALIDO';
            } elseif (count($rfcMatches) > 1) {
                $status = 'REVISION_RFC_AMBIGUO';
            } elseif (count($rfcMatches) === 1) {
                $candidate = $rfcMatches[0];

                if (
                    $name === '' ||
                    $candidate['nombre'] !== $name
                ) {
                    $status = 'REVISION_RFC_NOMBRE_DIVERGENTE';
                } elseif ($candidate['estatus'] !== 'ACTIVO') {
                    $status = 'REVISION_PERSONA_NO_ACTIVA';
                } else {
                    $personId = $candidate['id'];
                    $matched[$personId] = true;
                    $status = 'PERSONA_ACTIVA_SIN_PARTICIPANTE';
                }
            } else {
                $count = count($byName[$name] ?? []);

                $status = $count > 1
                    ? 'REVISION_NOMBRE_AMBIGUO'
                    : (
                        $count === 1
                            ? 'REVISION_NOMBRE_COINCIDENTE'
                            : 'SIN_COINCIDENCIA'
                    );
            }

            $results[] = [
                'estado' => $status,
                'id_persona' => $personId,
                'usuario_existente' => false,
            ];
        }

        if ($matched !== []) {
            $ids = array_keys($matched);

            $participants = array_fill_keys(
                DB::table('institutional.academic_participants')
                    ->whereIn('id_persona', $ids)
                    ->pluck('id_persona')
                    ->map(static fn ($id) => (string) $id)
                    ->all(),
                true
            );

            $users = array_fill_keys(
                DB::table('system.users')
                    ->whereIn('id_persona', $ids)
                    ->pluck('id_persona')
                    ->map(static fn ($id) => (string) $id)
                    ->all(),
                true
            );

            foreach ($results as &$item) {
                $id = $item['id_persona'];

                if ($id === null) {
                    continue;
                }

                $item['usuario_existente'] = isset($users[$id]);

                if (isset($participants[$id])) {
                    $item['estado'] =
                        'PERSONA_ACTIVA_YA_PARTICIPANTE';
                }
            }

            unset($item);
        }

        $counts = [];

        foreach ($results as $item) {
            $key = $item['estado'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return [
            'filas' => $results,
            'resumen' => $counts,
        ];
    }
}