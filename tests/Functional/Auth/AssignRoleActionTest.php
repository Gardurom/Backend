<?php

namespace Tests\Functional\Auth;

use App\Actions\User\AssignRole;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Functional\FunctionalTestCase;

class AssignRoleActionTest extends FunctionalTestCase
{
    public function test_asigna_rol_a_usuario_existente(): void
    {
        $user = User::factory()->create();

        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $result = (new AssignRole)->execute(
            $user->id,
            $roleId
        );

        self::assertTrue($result);

        $total = $this->db
            ->table('system.user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $roleId)
            ->count();

        self::assertSame(1, $total);
    }

    public function test_no_duplica_asignacion_existente(): void
    {
        $user = User::factory()->create();

        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $action = new AssignRole;

        self::assertTrue(
            $action->execute($user->id, $roleId)
        );

        self::assertFalse(
            $action->execute($user->id, $roleId)
        );

        $total = $this->db
            ->table('system.user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $roleId)
            ->count();

        self::assertSame(1, $total);
    }

    public function test_rechaza_usuario_inexistente(): void
    {
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $this->expectException(
            ModelNotFoundException::class
        );

        (new AssignRole)->execute(
            2147483647,
            $roleId
        );
    }

    public function test_rechaza_rol_inexistente(): void
    {
        $user = User::factory()->create();

        $this->expectException(
            ModelNotFoundException::class
        );

        (new AssignRole)->execute(
            $user->id,
            2147483647
        );
    }

    public function test_rechaza_asignacion_directa_de_administrador(): void
    {
        $user = User::factory()->create();

        $roleId = $this->roleId('ROL_ADMIN_SISTEMA');

        $this->expectException(\DomainException::class);

        (new AssignRole)->execute($user->id, $roleId);
    }
    private function roleId(string $code): int
    {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $code)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$code}."
        );

        return (int) $roleId;
    }
}