<?php

namespace Tests\Functional\Audit;

use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class ActivityTest extends FunctionalTestCase
{
    public function test_permite_registrar_una_actividad_valida(): void
    {
        $userId = $this->createUser();

        $activityId = $this->db->table('system.activities')
            ->insertGetId([
                'id_usuario' => $userId,
                'entidad' => 'PERSONA',
                'id_entidad' => '00000000-0000-0000-0000-000000000001',
                'accion' => 'CREACION',
                'datos_anteriores' => null,
                'datos_nuevos' => json_encode(
                    ['estatus' => 'ACTIVO'],
                    JSON_THROW_ON_ERROR
                ),
                'campos_modificados' => json_encode(
                    ['estatus'],
                    JSON_THROW_ON_ERROR
                ),
            ], 'id_actividad');

        $activity = $this->db->table('system.activities')
            ->where('id_actividad', $activityId)
            ->first();

        self::assertNotNull($activity);
        self::assertSame($userId, $activity->id_usuario);
        self::assertSame('PERSONA', $activity->entidad);
        self::assertSame('CREACION', $activity->accion);
        self::assertNotNull($activity->fecha_actividad);

        self::assertSame(
            ['estatus' => 'ACTIVO'],
            json_decode(
                $activity->datos_nuevos,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );

        self::assertSame(
            ['estatus'],
            json_decode(
                $activity->campos_modificados,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function test_rechaza_accion_fuera_del_dominio(): void
    {
        $userId = $this->createUser();

        $this->assertDatabaseRejected(
            function () use ($userId): void {
                $this->db->table('system.activities')->insert([
                    'id_usuario' => $userId,
                    'entidad' => 'PERSONA',
                    'id_entidad' => '00000000-0000-0000-0000-000000000002',
                    'accion' => 'ELIMINACION',
                ]);
            },
            '23514',
            'activities_accion_check'
        );
    }

    public function test_rechaza_usuario_inexistente(): void
    {
        $this->assertDatabaseRejected(
            function (): void {
                $this->db->table('system.activities')->insert([
                    'id_usuario' => PHP_INT_MAX,
                    'entidad' => 'PERSONA',
                    'id_entidad' => '00000000-0000-0000-0000-000000000003',
                    'accion' => 'CREACION',
                ]);
            },
            '23503',
            'activities_usuario_fk'
        );
    }

    private function createUser(): int
    {
        return $this->db->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO PRUEBA AUDITORIA',
                'email' => 'auditoria-'
                    .bin2hex(random_bytes(8))
                    .'@example.test',
                'password' => 'NO_USAR_EN_AUTENTICACION',
            ]);
    }

    private function assertDatabaseRejected(
        callable $operation,
        string $sqlState,
        string $constraint
    ): void {
        try {
            $operation();
        } catch (QueryException $error) {
            self::assertSame(
                $sqlState,
                $error->getCode(),
                'El rechazo debe proceder de PostgreSQL.'
            );

            self::assertStringContainsString(
                $constraint,
                $error->getMessage(),
                'Debe intervenir la restriccion esperada.'
            );

            return;
        }

        self::fail(
            "La operacion debio ser rechazada por {$constraint}."
        );
    }

    public function test_no_permite_modificar_una_actividad(): void
    {
        $userId = $this->createUser();

        $activityId = $this->db->table('system.activities')
            ->insertGetId([
                'id_usuario' => $userId,
                'entidad' => 'PERSONA',
                'id_entidad' => '00000000-0000-0000-0000-000000000004',
                'accion' => 'CREACION',
            ], 'id_actividad');

        try {
            $this->db->table('system.activities')
                ->where('id_actividad', $activityId)
                ->update([
                    'accion' => 'ACTUALIZACION',
                ]);
        } catch (QueryException $error) {
            self::assertSame(
                '42501',
                $error->getCode(),
                'siga_app no debe poder modificar actividades.'
            );

            return;
        }

        self::fail(
            'La actividad no debe poder modificarse mediante siga_app.'
        );
    }

    public function test_no_permite_eliminar_una_actividad(): void
    {
        $userId = $this->createUser();

        $activityId = $this->db->table('system.activities')
            ->insertGetId([
                'id_usuario' => $userId,
                'entidad' => 'PERSONA',
                'id_entidad' => '00000000-0000-0000-0000-000000000005',
                'accion' => 'CREACION',
            ], 'id_actividad');

        try {
            $this->db->table('system.activities')
                ->where('id_actividad', $activityId)
                ->delete();
        } catch (QueryException $error) {
            self::assertSame(
                '42501',
                $error->getCode(),
                'siga_app no debe poder eliminar actividades.'
            );

            return;
        }

        self::fail(
            'La actividad no debe poder eliminarse mediante siga_app.'
        );
    }
}
