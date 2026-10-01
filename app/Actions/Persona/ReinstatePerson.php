<?php

namespace App\Actions\Persona;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

class ReinstatePerson
{
    /**
     * Reingresa a una Persona dada de baja y registra
     * la auditoría dentro de la misma transacción.
     */
    public function execute(
        int $userId,
        string $personId
    ): Person {
        return DB::connection()->transaction(
            function () use ($userId, $personId): Person {
                $person = Person::query()->findOrFail($personId);

                if ($person->estatus !== 'BAJA') {
                    return $person;
                }

                $previous = [
                    'estatus' => $person->estatus,
                    'fecha_baja' => $person->fecha_baja?->toISOString(),
                ];

                $person->estatus = 'ACTIVO';
                $person->fecha_baja = null;

                $person->save();
                $person->refresh();

                $new = [
                    'estatus' => $person->estatus,
                    'fecha_baja' => null,
                ];

                DB::table('system.activities')->insert([
                    'id_usuario' => $userId,
                    'entidad' => 'PERSONA',
                    'id_entidad' => (string) $person->getKey(),
                    'accion' => 'REINGRESO',
                    'datos_anteriores' => json_encode(
                        $previous,
                        JSON_THROW_ON_ERROR
                    ),
                    'datos_nuevos' => json_encode(
                        $new,
                        JSON_THROW_ON_ERROR
                    ),
                    'campos_modificados' => json_encode(
                        [
                            'estatus',
                            'fecha_baja',
                        ],
                        JSON_THROW_ON_ERROR
                    ),
                ]);

                return $person;
            }
        );
    }
}
