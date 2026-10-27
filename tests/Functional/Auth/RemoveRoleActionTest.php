<?php

namespace Tests\Functional\Auth;

use App\Actions\User\RemoveRole;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Functional\FunctionalTestCase;

class RemoveRoleActionTest extends FunctionalTestCase
{
    public function test_retira_rol_ordinario(): void
    {
        $user = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $user->roles()->attach($roleId);

        self::assertTrue(
            (new RemoveRole)->execute($user->id, $roleId)
        );

        self::assertSame(0, $this->assignmentCount(
            $user->id,
            $roleId
        ));
    }

    public function test_retiro_repetido_es_idempotente(): void
    {
        $user = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $user->roles()->attach($roleId);

        $action = new RemoveRole;

        self::assertTrue($action->execute($user->id, $roleId));
        self::assertFalse($action->execute($user->id, $roleId));

        self::assertSame(0, $this->assignmentCount(
            $user->id,
            $roleId
        ));
    }

    public function test_rechaza_retiro_de_rol_administrador(): void
    {
        $user = User::factory()->create();
        $roleId = $this->roleId('ROL_ADMIN_SISTEMA');

        $user->roles()->attach($roleId);

        try {
            (new RemoveRole)->execute($user->id, $roleId);
        } catch (\DomainException $exception) {
            self::assertSame(1, $this->assignmentCount(
                $user->id,
                $roleId
            ));

            return;
        }

        self::fail('El rol administrador no debe retirarse.');
    }

    public function test_rechaza_usuario_inexistente(): void
    {
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $this->expectException(
            ModelNotFoundException::class
        );

        (new RemoveRole)->execute(2147483647, $roleId);
    }

    public function test_rechaza_rol_inexistente(): void
    {
        $user = User::factory()->create();

        $this->expectException(
            ModelNotFoundException::class
        );

        (new RemoveRole)->execute($user->id, 2147483647);
    }

    private function roleId(string $code): int
    {
        $id = $this->db
            ->table('system.roles')
            ->where('code', $code)
            ->value('id');

        self::assertNotNull($id, "No existe {$code}.");

        return (int) $id;
    }

    private function assignmentCount(int $userId, int $roleId): int
    {
        return $this->db
            ->table('system.user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->count();
    }
}