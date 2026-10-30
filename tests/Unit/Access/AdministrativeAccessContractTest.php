<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use PHPUnit\Framework\TestCase;

final class AdministrativeAccessContractTest extends TestCase
{
    private static function source(string $relative): string
    {
        $root = dirname(__DIR__, 3);
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        $source = file_get_contents($path);

        self::assertNotFalse($source, "No fue posible leer {$relative}");

        return $source;
    }

    public function test_administrative_operation_uses_a_transaction(): void
    {
        $source = self::source('app/Access/ChangeUserAccess.php');

        self::assertStringContainsString('DB::transaction(', $source);
        self::assertStringContainsString('pg_advisory_xact_lock(73421, 101)', $source);
        self::assertSame(2, substr_count($source, 'lockForUpdate()'));
    }

    public function test_actor_eligibility_precedes_permission_and_target_lookup(): void
    {
        $source = self::source('app/Access/ChangeUserAccess.php');

        $eligibility = strpos($source, 'app(AccessEligibility::class)->allows($actor)');
        $permission = strpos($source, 'app(PermissionResolver::class)->allows($actor, $permission)');
        $target = strpos($source, 'findOrFail($userId)');

        self::assertNotFalse($eligibility);
        self::assertNotFalse($permission);
        self::assertNotFalse($target);

        self::assertLessThan($permission, $eligibility);
        self::assertLessThan($target, $permission);
    }

    public function test_enable_and_disable_have_distinct_permissions(): void
    {
        $source = self::source('app/Access/ChangeUserAccess.php');

        self::assertStringContainsString(
            "'usuarios.habilitar' : 'usuarios.deshabilitar'",
            $source
        );
    }

    public function test_enable_requires_roles_review_and_active_person(): void
    {
        $source = self::source('app/Access/ChangeUserAccess.php');

        self::assertStringContainsString(
            '$enable && (!$reviewed || !$target->person()->where(\'estatus\', \'ACTIVO\')->exists())',
            $source
        );

        self::assertStringContainsString(
            'ValidationException::withMessages',
            $source
        );
    }

    public function test_unchanged_status_returns_before_save(): void
    {
        $source = self::source('app/Access/ChangeUserAccess.php');

        $noChange = strpos($source, 'if ($target->access_status === $next)');
        $actorContext = strpos($source, "set_config('siga.actor_id'");
        $save = strpos($source, '$target->save()');

        self::assertNotFalse($noChange);
        self::assertNotFalse($actorContext);
        self::assertNotFalse($save);

        self::assertLessThan($actorContext, $noChange);
        self::assertLessThan($save, $actorContext);
    }

    public function test_enable_controller_requires_explicit_roles_review(): void
    {
        $source = self::source('app/Http/Controllers/Api/UserAccessController.php');

        self::assertStringContainsString(
            "'roles_revisados' => ['required', 'accepted']",
            $source
        );

        self::assertStringContainsString(
            'execute($request->user(), $user, true, true)',
            $source
        );
    }

    public function test_disable_controller_uses_disable_operation(): void
    {
        $source = self::source('app/Http/Controllers/Api/UserAccessController.php');

        self::assertStringContainsString(
            'execute($request->user(), $user, false, false)',
            $source
        );
    }

    public function test_response_exposes_only_expected_access_fields(): void
    {
        $source = self::source('app/Http/Controllers/Api/UserAccessController.php');

        self::assertSame(
            2,
            substr_count(
                $source,
                "only(['id', 'id_persona', 'access_status', 'access_version'])"
            )
        );
    }
}