<?php

namespace Tests\Functional\Persona;

use App\Actions\Persona\UpdatePerson;
use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class UpdatePersonActionTest extends FunctionalTestCase
{
    public function test_actualiza_persona_y_registra_solo_campos_modificados(): void
    {
        $userId = $this->createUser();
        $personId = $this->createPerson([
            'nombres' => 'NOMBRE ORIGINAL',
            'correo_personal' => 'original@example.test',
        ]);

        $person = (new UpdatePerson)->execute(
            $userId,
            $personId,
            [
                'nombres' => 'NOMBRE ACTUALIZADO',
                'correo_personal' => 'nuevo@example.test',
            ]
        );

        self::assertSame(
            'NOMBRE ACTUALIZADO',
            $person->nombres
        );

        self::assertSame(
            'nuevo@example.test',
            $person->correo_personal
        );

        $activity = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $personId)
            ->where('accion', 'ACTUALIZACION')
            ->first();

        self::assertNotNull($activity);
        self::assertSame($userId, $activity->id_usuario);

        self::assertEquals(
            [
                'nombres' => 'NOMBRE ORIGINAL',
                'correo_personal' => 'original@example.test',
            ],
            json_decode(
                $activity->datos_anteriores,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );

        self::assertEquals(
            [
                'nombres' => 'NOMBRE ACTUALIZADO',
                'correo_personal' => 'nuevo@example.test',
            ],
            json_decode(
                $activity->datos_nuevos,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );

        self::assertEquals(
            [
                'nombres',
                'correo_personal',
            ],
            json_decode(
                $activity->campos_modificados,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function test_no_registra_auditoria_si_no_hay_cambios(): void
    {
        $userId = $this->createUser();
        $personId = $this->createPerson([
            'nombres' => 'PERSONA SIN CAMBIOS',
        ]);

        (new UpdatePerson)->execute(
            $userId,
            $personId,
            [
                'nombres' => 'PERSONA SIN CAMBIOS',
            ]
        );

        $exists = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $personId)
            ->where('accion', 'ACTUALIZACION')
            ->exists();

        self::assertFalse(
            $exists,
            'No debe generarse auditoria si no hubo cambios reales.'
        );
    }

    public function test_revierte_actualizacion_si_falla_la_auditoria(): void
    {
        $personId = $this->createPerson([
            'nombres' => 'NOMBRE ANTES DEL ROLLBACK',
        ]);

        try {
            (new UpdatePerson)->execute(
                PHP_INT_MAX,
                $personId,
                [
                    'nombres' => 'NOMBRE QUE DEBE REVERTIRSE',
                ]
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

            self::assertSame(
                'NOMBRE ANTES DEL ROLLBACK',
                $person->nombres,
                'La actualizacion debe revertirse si falla su auditoria.'
            );

            return;
        }

        self::fail(
            'La operacion debio fallar por el usuario inexistente.'
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPerson(array $attributes): string
    {
        return (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                $attributes,
                'id_persona'
            );
    }

    private function createUser(): int
    {
        return $this->db->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO PRUEBA ACTUALIZACION PERSONA',
                'email' => 'actualizacion-persona-'
                    .bin2hex(random_bytes(8))
                    .'@example.test',
                'password' => 'NO_USAR_EN_AUTENTICACION',
            ]);
    }
}
