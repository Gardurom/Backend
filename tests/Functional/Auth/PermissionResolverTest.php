<?php

namespace Tests\Functional\Auth;

use App\Authorization\PermissionDecision;
use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\FunctionalTestCase;

class PermissionResolverTest extends FunctionalTestCase
{
    public function test_resolves_allow_when_assigned_role_allows_permission(): void
    {
        $user = $this->createUser();

        $this->assignRole(
            $user,
            'ROL_GESTOR_PERSONAS'
        );

        $decision = (new PermissionResolver)->resolve(
            $user,
            'personas.crear'
        );

        self::assertSame(
            PermissionDecision::ALLOW,
            $decision
        );

        self::assertTrue(
            $decision->allows()
        );
    }

    public function test_explicit_deny_overrides_allow_from_another_role(): void
    {
        $user = $this->createUser();

        $this->assignRole(
            $user,
            'ROL_GESTOR_PERSONAS'
        );

        $this->assignRole(
            $user,
            'ROL_CONSULTA_PERSONAS'
        );

        $this->replaceEffect(
            'ROL_CONSULTA_PERSONAS',
            'personas.ver',
            'DENY'
        );

        $decision = (new PermissionResolver)->resolve(
            $user,
            'personas.ver'
        );

        self::assertSame(
            PermissionDecision::DENY,
            $decision
        );

        self::assertFalse(
            $decision->allows()
        );
    }

    public function test_resolves_no_rule_when_no_role_grants_or_denies_permission(): void
    {
        $user = $this->createUser();

        $this->assignRole(
            $user,
            'ROL_ADMIN_SISTEMA'
        );

        $decision = (new PermissionResolver)->resolve(
            $user,
            'personas.ver'
        );

        self::assertSame(
            PermissionDecision::NO_RULE,
            $decision
        );

        self::assertFalse(
            $decision->allows()
        );
    }

    private function createUser(): User
    {
        $id = $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO RESOLVEDOR',
                'email' => 'permission.resolver@siga.test',
                'password' => Hash::make('ClaveSegura123!'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::query()->findOrFail($id);
    }

    private function assignRole(
        User $user,
        string $roleCode
    ): void {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $roleCode)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$roleCode}."
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function replaceEffect(
        string $roleCode,
        string $permissionCode,
        string $effect
    ): void {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $roleCode)
            ->value('id');

        $permissionId = $this->db
            ->table('system.permissions')
            ->where('code', $permissionCode)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$roleCode}."
        );

        self::assertNotNull(
            $permissionId,
            "Debe existir el permiso {$permissionCode}."
        );

        $this->db
            ->table('system.role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();

        $this->db
            ->table('system.role_permissions')
            ->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'effect' => $effect,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
