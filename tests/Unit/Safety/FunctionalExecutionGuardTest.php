<?php

declare(strict_types=1);

namespace Tests\Unit\Safety;

use PHPUnit\Framework\TestCase;
use Tests\Functional\FunctionalExecutionGuard;

final class FunctionalExecutionGuardTest extends TestCase
{
    public function test_execution_is_blocked_explicitly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'SIGA_FUNCTIONAL_EXECUTION_BLOCKED'
        );

        FunctionalExecutionGuard::assertSafeToRun();
    }

    public function test_action_tests_are_blocked_before_parent_setup(): void
    {
        $this->assertGuardRunsBeforeParent(
            'FunctionalTestCase.php'
        );
    }

    public function test_http_tests_are_blocked_before_parent_setup(): void
    {
        $this->assertGuardRunsBeforeParent(
            'HttpFunctionalTestCase.php'
        );
    }

    private function assertGuardRunsBeforeParent(string $file): void
    {
        $path = dirname(__DIR__, 2).'/Functional/'.$file;

        $source = file_get_contents($path);

        self::assertNotFalse($source);

        $guardPosition = strpos(
            $source,
            'FunctionalExecutionGuard::assertSafeToRun();'
        );

        $parentPosition = strpos(
            $source,
            'parent::setUp();'
        );

        self::assertNotFalse($guardPosition);
        self::assertNotFalse($parentPosition);

        self::assertLessThan(
            $parentPosition,
            $guardPosition,
            'El bloqueo debe ejecutarse antes del setUp padre.'
        );
    }
}