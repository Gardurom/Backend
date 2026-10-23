<?php

namespace App\Actions\Persona;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

class FindPersonRegistrationOrigin
{
    /**
     * Consulta la procedencia documentada de una Persona.
     *
     * No presupone que la ausencia de una importación
     * equivalga a un registro individual.
     *
     * @return array<string, mixed>
     */
    public function execute(string $idPersona): array
    {
        $person = Person::query()->findOrFail($idPersona);

        $creation = DB::table('system.activities as a')
            ->leftJoin(
                'system.users as u',
                'u.id',
                '=',
                'a.id_usuario'
            )
            ->where('a.entidad', 'PERSONA')
            ->where('a.id_entidad', $idPersona)
            ->where('a.accion', 'CREACION')
            ->orderBy('a.id_actividad')
            ->first([
                'u.name as registrado_por',
                'a.datos_nuevos as datos_nuevos',
            ]);

        $origin = [
            'fecha_registro' => $person->created_at?->toISOString(),
            'forma_registro' => 'NO_DETERMINADO',
            'registrado_por' => $creation?->registrado_por,
            'archivo_origen' => null,
            'fila_archivo' => null,
            'lote_uuid' => null,
        ];

        $matches = DB::table('system.import_rows as r')
            ->join(
                'system.import_batches as b',
                'b.id',
                '=',
                'r.import_batch_id'
            )
            ->where('b.import_type', 'PERSONAS')
            ->where('r.status', 'PROCESSED')
            ->whereRaw(
                "r.result_data ->> 'id_persona' = ?",
                [$idPersona]
            )
            ->limit(2)
            ->get([
                'b.uuid as lote_uuid',
                'b.original_filename as archivo_origen',
                'r.row_number as fila_archivo',
            ]);

        // La auditoría puede no tener este campo en registros
        // anteriores a la incorporación de la trazabilidad.
        $auditData = null;

        if (is_string($creation?->datos_nuevos)) {
            $auditData = json_decode(
                $creation->datos_nuevos,
                true
            );
        } elseif (is_array($creation?->datos_nuevos)) {
            $auditData = $creation->datos_nuevos;
        }

        $declaredOrigin = is_array($auditData)
            ? ($auditData['origen_registro'] ?? null)
            : null;

        // Una importación procesada identifica el origen masivo,
        // salvo si contradice una declaración individual.
        if (
            $matches->count() === 1
            && $declaredOrigin !== 'INDIVIDUAL'
        ) {
            $match = $matches->first();

            $origin['forma_registro'] = 'MASIVO';
            $origin['archivo_origen'] = $match->archivo_origen;
            $origin['fila_archivo'] = (int) $match->fila_archivo;
            $origin['lote_uuid'] = $match->lote_uuid;
        } elseif (
            $matches->isEmpty()
            && $declaredOrigin === 'INDIVIDUAL'
        ) {
            // No atribuir origen individual por simple ausencia
            // de importaciones: debe constar en la auditoría.
            $origin['forma_registro'] = 'INDIVIDUAL';
        }

        // Una declaración MASIVO sin fila vinculada, o varias
        // filas coincidentes, permanece NO_DETERMINADO.

        return $origin;
    }
}