<?php

declare(strict_types=1);

namespace Tests\Functional\Auth\Concerns;

trait AssertsRoleActivity
{
    private function assertUserRoleActivity(
        int $actorId,
        int $userId,
        int $roleId,
        string $roleCode,
        bool $assigned
    ): void {
        $activities = $this->db->table('system.activities')
            ->where('entidad', 'USUARIO_ROL')
            ->where('id_entidad', (string) $userId)
            ->get();

        self::assertCount(1, $activities);

        $activity = $activities->first();
        self::assertSame($actorId, (int) $activity->id_usuario);
        self::assertSame('ACTUALIZACION', $activity->accion);

        $role = ['role_id' => $roleId, 'role_code' => $roleCode];
        $empty = ['role_id' => null, 'role_code' => null];
        $expectedPrevious = $assigned ? $empty : $role;
        $expectedCurrent = $assigned ? $role : $empty;

        self::assertSame(
            $expectedPrevious,
            json_decode($activity->datos_anteriores, true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertSame(
            $expectedCurrent,
            json_decode($activity->datos_nuevos, true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertSame(
            ['role_id', 'role_code'],
            json_decode($activity->campos_modificados, true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertNotNull($activity->fecha_actividad);
    }

    private function assertNoUserRoleActivity(int $userId): void
    {
        self::assertSame(
            0,
            $this->db->table('system.activities')
                ->where('entidad', 'USUARIO_ROL')
                ->where('id_entidad', (string) $userId)
                ->count()
        );
    }
}
