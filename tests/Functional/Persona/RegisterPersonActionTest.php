<?php

namespace Tests\Functional\Persona;

use App\Actions\Persona\RegisterPerson;
use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class RegisterPersonActionTest extends FunctionalTestCase
{
    public function test_registra_persona_y_auditoria_en_la_misma_operacion(): void
    {
        $userId = $this->createUser();

        $person = (new RegisterPerson)->execute(
            $userId,
            [
                'nombres' => 'PRUEBA ACCION REGISTRO',
                'apellido_paterno' => 'PERSONA',
                'correo_personal' => 'registro@example.test',
            ]
        );

        self::assertNotNull($person->id_persona);
        self::assertNotNull($person->num_expediente);
        self::assertSame('ACTIVO', $person->estatus);
        self::assertSame(
            'PRUEBA ACCION REGISTRO',
            $person->nombres
        );

        $activity = $this->db->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where(
                'id_entidad',
                (string) $person->id_persona
            )
            ->first();

        self::assertNotNull($activity);
        self::assertSame($userId, $activity->id_usuario);
        self::assertSame('CREACION', $activity->accion);
        self::assertNull($activity->datos_anteriores);

        // self::assertSame(
        //     [
        //         'num_expediente' => $person->num_expediente,
        //         'estatus' => 'ACTIVO',
        //     ],
        //     json_decode(
        //         $activity->datos_nuevos,
        //         true,
        //         512,
        //         JSON_THROW_ON_ERROR
        //     )
        // );

        self::assertEquals(
            [
                'num_expediente' => $person->num_expediente,
                'estatus' => 'ACTIVO',
            ],
            json_decode(
                $activity->datos_nuevos,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );

        self::assertSame(
            [
                'nombres',
                'apellido_paterno',
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

    public function test_revierte_persona_si_falla_la_auditoria(): void
    {
        try {
            (new RegisterPerson)->execute(
                PHP_INT_MAX,
                [
                    'nombres' => 'PRUEBA ROLLBACK AUDITORIA',
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

            $exists = $this->db->table('institutional.persons')
                ->where(
                    'nombres',
                    'PRUEBA ROLLBACK AUDITORIA'
                )
                ->exists();

            self::assertFalse(
                $exists,
                'La Persona debe revertirse si falla su auditoria.'
            );

            return;
        }

        self::fail(
            'La operacion debio fallar por el usuario inexistente.'
        );
    }

    private function createUser(): int
    {
        return $this->db->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO PRUEBA REGISTRO PERSONA',
                'email' => 'registro-persona-'
                    .bin2hex(random_bytes(8))
                    .'@example.test',
                'password' => 'NO_USAR_EN_AUTENTICACION',
            ]);
    }
}
