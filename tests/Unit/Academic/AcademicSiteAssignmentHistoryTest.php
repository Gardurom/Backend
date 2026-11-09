<?php

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicSiteAssignmentHistoryTest extends TestCase
{
    private function source(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/migrations/2026_11_09_171000_create_academic_site_assignment_history.php';

        $source = file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    public function test_creates_two_historical_tables(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            'institutional.academic_cohort_site_history',
            $source
        );

        self::assertStringContainsString(
            'institutional.academic_group_site_history',
            $source
        );
    }

    public function test_references_existing_cohorts_groups_and_sites(): void
    {
        $source = $this->source();

        foreach ([
            'institutional.academic_cohorts',
            'institutional.academic_groups',
            'institutional.academic_sites',
        ] as $table) {
            self::assertStringContainsString($table, $source);
        }
    }

    public function test_preserves_start_and_end_dates(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            "timestampTz('vigente_desde')",
            $source
        );

        self::assertStringContainsString(
            "timestampTz('vigente_hasta')->nullable()",
            $source
        );

        self::assertStringContainsString(
            'vigente_hasta > vigente_desde',
            $source
        );
    }

    public function test_only_one_open_assignment_per_cohort_or_group(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            'academic_cohort_one_current_site',
            $source
        );

        self::assertStringContainsString(
            'academic_group_one_current_site',
            $source
        );

        self::assertSame(
            2,
            substr_count($source, 'WHERE vigente_hasta IS NULL')
        );
    }

    public function test_requires_controlled_institutional_migration(): void
    {
        $source = $this->source();

        foreach ([
            "DB::transactionLevel()",
            "current_database()",
            "siga_migrator",
            "SET ROLE siga_owner",
            "RESET ROLE",
        ] as $fragment) {
            self::assertStringContainsString($fragment, $source);
        }
    }

    public function test_grants_read_only_application_access(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            'REVOKE ALL ON TABLE',
            $source
        );

        self::assertStringContainsString(
            'GRANT SELECT ON TABLE',
            $source
        );
    }

    public function test_blocks_automatic_rollback(): void
    {
        self::assertStringContainsString(
            'rollback automatico bloqueado',
            $this->source()
        );
    }
}