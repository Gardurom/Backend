<?php

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicSitesGroupsMigrationTest extends TestCase
{
    private function source(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/migrations/2026_11_09_170000_create_academic_sites_and_groups.php';

        $source = file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    public function test_creates_sites_and_groups(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            "Schema::create('institutional.academic_sites'",
            $source
        );

        self::assertStringContainsString(
            "Schema::create('institutional.academic_groups'",
            $source
        );
    }

    public function test_groups_reference_existing_cohorts(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            "->on('institutional.academic_cohorts')",
            $source
        );

        self::assertStringContainsString(
            "->restrictOnDelete()",
            $source
        );
    }

    public function test_specialization_is_optional_and_restricted(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            "->nullable()",
            $source
        );

        self::assertStringContainsString(
            "especializacion IN ('CAMPO', 'GABINETE')",
            $source
        );
    }

    public function test_migration_requires_institutional_identity(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            "current_database()",
            $source
        );

        self::assertStringContainsString(
            "siga_migrator",
            $source
        );

        self::assertStringContainsString(
            "SET ROLE siga_owner",
            $source
        );
    }

    public function test_application_only_receives_select(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            'GRANT SELECT ON TABLE',
            $source
        );

        self::assertStringContainsString(
            'REVOKE ALL ON TABLE',
            $source
        );
    }

    public function test_automatic_rollback_is_blocked(): void
    {
        self::assertStringContainsString(
            'rollback automatico bloqueado',
            $this->source()
        );
    }
}