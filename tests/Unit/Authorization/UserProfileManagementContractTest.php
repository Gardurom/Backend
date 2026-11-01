<?php

namespace Tests\Unit\Authorization;

use PHPUnit\Framework\TestCase;

final class UserProfileManagementContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$path
        );

        self::assertIsString($source);

        return $source;
    }

    public function test_profile_management_uses_a_transaction_and_audit(): void
    {
        $source = $this->source('app/Actions/User/ManageUserProfiles.php');

        self::assertStringContainsString('DB::transaction(', $source);
        self::assertStringContainsString('lockForUpdate()', $source);
        self::assertStringContainsString("'system.activities'", $source);
        self::assertStringContainsString("'USUARIO_PERFIL'", $source);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $source);
    }

    public function test_default_profile_cannot_be_removed_directly(): void
    {
        $source = $this->source('app/Actions/User/ManageUserProfiles.php');

        self::assertStringContainsString(
            'if ((bool) $assignment->is_default)',
            $source
        );
        self::assertStringContainsString(
            'throw ValidationException::withMessages(',
            $source
        );
    }

    public function test_write_routes_require_independent_permission_and_reauthentication(): void
    {
        $source = $this->source('routes/api.php');

        preg_match_all(
            '~Route::(post|delete)\(\s*'
            ."'/(usuarios/\\{user\\}/perfiles(?:/\\{profile\\})?)'"
            .'[\s\S]*?\);~',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        self::assertCount(2, $matches);

        foreach ($matches as $match) {
            self::assertStringContainsString(
                'siga.permission:usuarios.asignar_perfiles',
                $match[0]
            );
            self::assertStringContainsString(
                'siga.reauthenticated',
                $match[0]
            );
            self::assertStringContainsString(
                'siga.access',
                $match[0]
            );
        }
    }

    public function test_migration_does_not_grant_permission_to_any_role(): void
    {
        $source = $this->source(
            'database/migrations/2026_11_01_230000_register_user_profile_permission.php'
        );

        self::assertStringContainsString(
            "'usuarios.asignar_perfiles'",
            $source
        );
        self::assertStringNotContainsString(
            'system.role_permissions',
            $source
        );
        self::assertStringNotContainsString(
            'system.user_roles',
            $source
        );
    }
}
