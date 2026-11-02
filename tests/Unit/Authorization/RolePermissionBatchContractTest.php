<?php

namespace Tests\Unit\Authorization;

use PHPUnit\Framework\TestCase;

final class RolePermissionBatchContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/' . $path
        );
        self::assertIsString($source);
        return $source;
    }

    public function test_batch_route_requires_independent_permission_and_reauthentication(): void
    {
        $routes = $this->source('routes/api.php');

        preg_match(
            '~Route::patch\(\s*'
            . "'/roles/\\{role\\}/permisos/lote'"
            . '[\s\S]*?\);~',
            $routes,
            $matches
        );

        self::assertNotEmpty($matches);
        self::assertStringContainsString(
            'siga.permission:roles.administrar_permisos',
            $matches[0]
        );
        self::assertStringContainsString(
            'siga.reauthenticated',
            $matches[0]
        );
        self::assertStringContainsString(
            'siga.access',
            $matches[0]
        );
    }

    public function test_all_updates_are_transactional_and_audited(): void
    {
        $source = $this->source(
            'app/Actions/Authorization/ChangeRolePermissionEffectsBatch.php'
        );

        self::assertStringContainsString('DB::transaction(', $source);
        self::assertStringContainsString('lockForUpdate()', $source);
        self::assertStringContainsString('abort(409', $source);
        self::assertStringContainsString("'system.activities'", $source);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $source);
    }

    public function test_administrator_and_own_roles_remain_protected(): void
    {
        $source = $this->source(
            'app/Actions/Authorization/ChangeRolePermissionEffectsBatch.php'
        );

        self::assertStringContainsString(
            "'ROL_ADMIN_SISTEMA'", $source
        );
        self::assertStringContainsString(
            "'system.user_roles'", $source
        );
        self::assertStringContainsString(
            "'user_id', \$actorId", $source
        );
    }

    public function test_batch_validates_duplicates_and_original_effects(): void
    {
        $source = $this->source(
            'app/Actions/Authorization/ChangeRolePermissionEffectsBatch.php'
        );

        self::assertStringContainsString('array_unique($ids)', $source);
        self::assertStringContainsString(
            "\$change['expected_effect']", $source
        );
    }
}
