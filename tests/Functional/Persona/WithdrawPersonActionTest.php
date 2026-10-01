<?php

namespace Tests\Functional\Persona;

use App\Actions\Persona\WithdrawPerson;
use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class WithdrawPersonActionTest extends FunctionalTestCase
{
    public function test_da_de_baja_persona_y_registra_auditoria(): void
    {
        $userId = $this->createUser();
        $personId = $this->createPerson();

        $person = (new WithdrawPerson)->execute(
            $userId,
            $personId
        );

        self::assertSame('BAJA', $person->estatus);
        self::assertNotNull($person->fecha_baja);

        $activity = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $personId)
            ->where('accion', 'BAJA')
            ->first();

        self::assertNotNull($activity);
        self::assertSame($userId, $activity->id_usuario);

        $previous = json_decode(
            $activity->datos_anteriores,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $new = json_decode(
            $activity->datos_nuevos,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('ACTIVO', $previous['estatus']);
        self::assertNull($previous['fecha_baja']);

        self::assertSame('BAJA', $new['estatus']);
        self::assertNotNull($new['fecha_baja']);

        self::assertEquals(
            [
                'estatus',
                'fecha_baja',
            ],
            json_decode(
                $activity->campos_modificados,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function test_no_duplica_baja_si_persona_ya_esta_de_baja(): void
    {
        $userId = $this->createUser();
        $personId = $this->createPerson();

        $action = new WithdrawPerson;

        $first = $action->execute(
            $userId,
            $personId
        );

        $firstDate = $first->fecha_baja?->toISOString();

        $second = $action->execute(
            $userId,
            $personId
        );

        self::assertSame('BAJA', $second->estatus);

        self::assertSame(
            $firstDate,
            $second->fecha_baja?->toISOString()
        );

        $count = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $personId)
            ->where('accion', 'BAJA')
            ->count();

        self::assertSame(
            1,
            $count,
            'Una Persona ya dada de baja no debe generar otra auditoria BAJA.'
        );
    }

    public function test_fecha_baja_no_es_anterior_a_fecha_alta(): void
    {
        $userId = $this->createUser();
        $personId = $this->createPerson();

        $person = (new WithdrawPerson)->execute(
            $userId,
            $personId
        );

        self::assertNotNull($person->fecha_alta);
        self::assertNotNull($person->fecha_baja);

        self::assertTrue(
            $person->fecha_baja->greaterThanOrEqualTo(
                $person->fecha_alta
            )
        );
    }

    public function test_revierte_baja_si_falla_la_auditoria(): void
    {
        $personId = $this->createPerson();

        try {
            (new WithdrawPerson)->execute(
                PHP_INT_MAX,
                $personId
            );
        } catch (QueryException $error) {
            self::assertSame(
                '23503',
                $error->getCode(),
                'Debe fallar por el usuario de auditoria inexistente.'
            );

            self::assertStringContainsString(
                'activities_usuario_fk',
                $error->getMessage()
            );

            $person = $this->db->table('institutional.persons')
                ->where('id_persona', $personId)
                ->first();

            self::assertNotNull($person);
            self::assertSame('ACTIVO', $person->estatus);
            self::assertNull($person->fecha_baja);

            return;
        }

        self::fail(
            'La operacion debio fallar por el usuario inexistente.'
        );
    }

    private function createPerson(): string
    {
        return (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PRUEBA BAJA PERSONA',
                ],
                'id_persona'
            );
    }

    private function createUser(): int
    {
        return $this->db->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO PRUEBA BAJA PERSONA',
                'email' => 'baja-persona-'
                    .bin2hex(random_bytes(8))
                    .'@example.test',
                'password' => 'NO_USAR_EN_AUTENTICACION',
            ]);
    }
}
