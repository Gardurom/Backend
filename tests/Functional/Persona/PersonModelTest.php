<?php

namespace Tests\Functional\Persona;

use App\Models\Person;
use Carbon\CarbonInterface;
use Tests\Functional\FunctionalTestCase;

class PersonModelTest extends FunctionalTestCase
{
    public function test_model_uses_persons_table_and_primary_key(): void
    {
        $id = $this->db->table('institutional.persons')
            ->insertGetId([
                'nombres' => 'PRUEBA MODELO PERSONA',
            ], 'id_persona');

        $person = Person::find($id);

        self::assertNotNull($person);
        self::assertSame($id, $person->getKey());
        self::assertSame('institutional.persons', $person->getTable());
        self::assertSame('id_persona', $person->getKeyName());
        self::assertFalse($person->getIncrementing());
        self::assertSame('string', $person->getKeyType());
        self::assertSame(
            'PRUEBA MODELO PERSONA',
            $person->nombres
        );
    }

    public function test_model_casts_person_dates_correctly(): void
    {
        $id = $this->db->table('institutional.persons')
            ->insertGetId([
                'nombres' => 'PRUEBA CASTS PERSONA',
                'fecha_nacimiento' => '2000-01-02',
            ], 'id_persona');

        $person = Person::find($id);

        self::assertNotNull($person);

        self::assertInstanceOf(
            CarbonInterface::class,
            $person->fecha_nacimiento
        );

        self::assertSame(
            '2000-01-02',
            $person->fecha_nacimiento->format('Y-m-d')
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $person->fecha_alta
        );

        self::assertNull($person->fecha_baja);

        self::assertInstanceOf(
            CarbonInterface::class,
            $person->created_at
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $person->updated_at
        );
    }
}
