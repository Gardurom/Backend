<?php

use PHPUnit\Framework\TestCase;

final class AcademicMigrationSafetyTest extends TestCase
{
    private static function migration(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/migrations/'
            . '2026_11_09_150000_create_academic_programs_and_cohorts.php';

        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertGreaterThan(2000, strlen($content));

        return $content;
    }

    public function test_requires_active_transaction(): void
    {
        self::assertStringContainsString(
            'DB::transactionLevel() < 1',
            self::migration()
        );
    }

    public function test_requires_institutional_database_and_migrator(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            'current_database() AS database_name',
            $code
        );

        self::assertStringContainsString(
            'session_user AS session_role',
            $code
        );

        self::assertStringContainsString(
            "\$identity->database_name !== 'siga'",
            $code
        );

        self::assertStringContainsString(
            "\$identity->session_role !== 'siga_migrator'",
            $code
        );
    }

    public function test_preserves_role_reset_and_rollback_protection(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            "SET ROLE siga_owner",
            $code
        );

        self::assertStringContainsString(
            'RESET ROLE',
            $code
        );

        self::assertStringContainsString(
            'rollback automatico bloqueado',
            $code
        );
    }
}