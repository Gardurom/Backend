<?php

namespace Tests\Unit\Authorization;

use PHPUnit\Framework\TestCase;

final class RolePermissionEffectContractTest extends TestCase
{
    private function source(string $path): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/'.$path);
        self::assertIsString($content);
        return $content;
    }

    public function test_route_requires_authorization_and_reauthentication(): void
    {
        $routes = $this->source('routes/api.php');

        self::assertStringContainsString(
            "'/roles/{role}/permisos/{permission}'",
            $routes
        );
        self::assertStringContainsString(
            'siga.permission:roles.administrar_permisos',
            $routes
        );
        self::assertStringContainsString(
            'siga.reauthenticated',
            $routes
        );
    }

    public function test_changes_are_transactional_and_audited(): void
    {
        $code = $this->source(
            'app/Actions/Authorization/ChangeRolePermissionEffect.php'
        );

        self::assertStringContainsString('DB::transaction(', $code);
        self::assertStringContainsString('lockForUpdate()', $code);
        self::assertStringContainsString("'system.activities'", $code);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $code);
        self::assertStringContainsString('abort(409', $code);
    }

    public function test_administrator_role_and_self_modification_are_blocked(): void
    {
        $code = $this->source(
            'app/Actions/Authorization/ChangeRolePermissionEffect.php'
        );

        self::assertStringContainsString("'ROL_ADMIN_SISTEMA'", $code);
        self::assertStringContainsString("'system.user_roles'", $code);
        self::assertStringContainsString("'user_id', \$actorId", $code);
    }

    public function test_permission_migration_does_not_grant_roles(): void
    {
        $migration = $this->source(
            'database/migrations/2026_11_02_000000_register_role_permission_management.php'
        );

        self::assertStringContainsString(
            "'roles.administrar_permisos'",
            $migration
        );
        self::assertStringNotContainsString(
            'system.role_permissions',
            $migration
        );
    }
}
