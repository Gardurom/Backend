<?php

namespace App\Actions\Persona;

use App\Models\Person;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RegisterPerson
{
    /**
     * Registra una Persona y su evidencia de auditoría
     * dentro de la misma transacción.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(int $userId, array $attributes): Person
    {
        return DB::connection()->transaction(
            function () use ($userId, $attributes): Person {
                $allowed = (new Person)->getFillable();

                $personData = Arr::only(
                    $attributes,
                    $allowed
                );

                $personId = DB::table('institutional.persons')
                    ->insertGetId(
                        $personData,
                        'id_persona'
                    );

                $person = Person::query()->findOrFail($personId);

                DB::table('system.activities')->insert([
                    'id_usuario' => $userId,
                    'entidad' => 'PERSONA',
                    'id_entidad' => (string) $person->getKey(),
                    'accion' => 'CREACION',
                    'datos_anteriores' => null,
                    'datos_nuevos' => json_encode(
                        [
                            'num_expediente' => $person->num_expediente,
                            'estatus' => $person->estatus,
                        ],
                        JSON_THROW_ON_ERROR
                    ),
                    'campos_modificados' => json_encode(
                        array_keys($personData),
                        JSON_THROW_ON_ERROR
                    ),
                ]);

                return $person;
            }
        );
    }
}
