<?php

namespace Tests\Functional\Persona;

use App\Actions\Persona\ReinstatePerson;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class ReinstatePersonActionTest extends FunctionalTestCase
{
    public function test_reingresa_persona_y_conserva_identidad_expediente_y_alta(): void
    {
        $userId = $this->createUser();
        $before = $this->createWithdrawnPerson();

        $originalId = (string) $before->id_persona;
        $originalExpediente = $before->num_expediente;
        $originalFechaAlta = $before->fecha_alta->toISOString();

        $person = (new ReinstatePerson)->execute(
            $userId,
            $originalId
        );

        self::assertSame($originalId, (string) $person->id_persona);
        self::assertSame(
            $originalExpediente,
            $person->num_expediente
        );
        self::assertSame(
            $originalFechaAlta,
            $person->fecha_alta->toISOString()
        );

        self::assertSame('ACTIVO', $person->estatus);
        self::assertNull($person->fecha_baja);

        $activity = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $originalId)
            ->where('accion', 'REINGRESO')
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

        self::assertSame('BAJA', $previous['estatus']);
        self::assertNotNull($previous['fecha_baja']);

        self::assertSame('ACTIVO', $new['estatus']);
        self::assertNull($new['fecha_baja']);

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

    public function test_no_duplica_reingreso_si_persona_ya_esta_activa(): void
    {
        $userId = $this->createUser();
        $person = $this->createWithdrawnPerson();

        $action = new ReinstatePerson;

        $first = $action->execute(
            $userId,
            (string) $person->id_persona
        );

        $second = $action->execute(
            $userId,
            (string) $person->id_persona
        );

        self::assertSame('ACTIVO', $first->estatus);
        self::assertSame('ACTIVO', $second->estatus);
        self::assertNull($second->fecha_baja);

        $count = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where(
                'id_entidad',
                (string) $person->id_persona
            )
            ->where('accion', 'REINGRESO')
            ->count();

        self::assertSame(
            1,
            $count,
            'Una Persona activa no debe generar otro REINGRESO.'
        );
    }

    public function test_persona_activa_no_genera_auditoria_de_reingreso(): void
    {
        $userId = $this->createUser();

        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA ACTIVA SIN REINGRESO',
                ],
                'id_persona'
            );

        $person = (new ReinstatePerson)->execute(
            $userId,
            $personId
        );

        self::assertSame('ACTIVO', $person->estatus);
        self::assertNull($person->fecha_baja);

        $exists = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $personId)
            ->where('accion', 'REINGRESO')
            ->exists();

        self::assertFalse(
            $exists,
            'Una Persona que no esta de baja no debe generar REINGRESO.'
        );
    }

    public function test_revierte_reingreso_si_falla_la_auditoria(): void
    {
        $person = $this->createWithdrawnPerson();

        $personId = (string) $person->id_persona;
        $originalFechaBaja = $person->fecha_baja->toISOString();

        try {
            (new ReinstatePerson)->execute(
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

            $after = Person::query()->findOrFail($personId);

            self::assertSame('BAJA', $after->estatus);
            self::assertNotNull($after->fecha_baja);

            self::assertSame(
                $originalFechaBaja,
                $after->fecha_baja->toISOString(),
                'La fecha de baja debe recuperarse si falla la auditoria.'
            );

            return;
        }

        self::fail(
            'La operacion debio fallar por el usuario inexistente.'
        );
    }

    private function createWithdrawnPerson(): Person
    {
        $id = $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PRUEBA REINGRESO PERSONA',
                    'estatus' => 'BAJA',
                    'fecha_baja' => $this->db->raw(
                        'CURRENT_TIMESTAMP'
                    ),
                ],
                'id_persona'
            );

        return Person::query()->findOrFail($id);
    }

    private function createUser(): int
    {
        return $this->db->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO PRUEBA REINGRESO PERSONA',
                'email' => 'reingreso-persona-'
                    .bin2hex(random_bytes(8))
                    .'@example.test',
                'password' => 'NO_USAR_EN_AUTENTICACION',
            ]);
    }
}
