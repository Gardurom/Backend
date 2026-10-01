<?php

namespace App\Actions\Persona;

use App\Models\Person;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdatePerson
{
    /**
     * Actualiza una Persona y registra los cambios
     * en la auditoría dentro de la misma transacción.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        int $userId,
        string $personId,
        array $attributes
    ): Person {
        return DB::connection()->transaction(
            function () use ($userId, $personId, $attributes): Person {
                $person = Person::query()->findOrFail($personId);

                $allowed = $person->getFillable();

                $personData = Arr::only(
                    $attributes,
                    $allowed
                );

                $person->fill($personData);

                $dirty = $person->getDirty();

                if ($dirty === []) {
                    return $person;
                }

                $previous = [];
                $new = [];

                foreach (array_keys($dirty) as $field) {
                    $previous[$field] = $person->getRawOriginal($field);
                    $new[$field] = $person->getAttributes()[$field] ?? null;
                }

                $person->save();

                DB::table('system.activities')->insert([
                    'id_usuario' => $userId,
                    'entidad' => 'PERSONA',
                    'id_entidad' => (string) $person->getKey(),
                    'accion' => 'ACTUALIZACION',
                    'datos_anteriores' => json_encode(
                        $previous,
                        JSON_THROW_ON_ERROR
                    ),
                    'datos_nuevos' => json_encode(
                        $new,
                        JSON_THROW_ON_ERROR
                    ),
                    'campos_modificados' => json_encode(
                        array_keys($dirty),
                        JSON_THROW_ON_ERROR
                    ),
                ]);

                return $person->refresh();
            }
        );
    }
}
