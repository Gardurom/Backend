<?php

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicSiteOverlapConstraintTest extends TestCase
{
    private function source(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/migrations/2026_11_09_171000_create_academic_site_assignment_history.php';

        $source = file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    public function test_requires_gist_uuid_operator(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            "am.amname = 'gist'",
            $source
        );

        self::assertStringContainsString(
            "opc.opcintype = 'uuid'::regtype",
            $source
        );

        self::assertStringContainsString(
            'requiere btree_gist',
            $source
        );
    }

    public function test_cohort_periods_cannot_overlap(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            'academic_cohort_site_no_overlap',
            $source
        );

        self::assertStringContainsString(
            'id_cohorte WITH =',
            $source
        );
    }

    public function test_group_periods_cannot_overlap(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            'academic_group_site_no_overlap',
            $source
        );

        self::assertStringContainsString(
            'id_grupo WITH =',
            $source
        );
    }

    public function test_uses_half_open_timestamp_ranges(): void
    {
        $source = $this->source();

        self::assertSame(
            2,
            substr_count(
                $source,
                "tstzrange(vigente_desde, vigente_hasta, '[)') WITH &&"
            )
        );
    }

    public function test_keeps_one_current_site_indexes(): void
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
    }

    public function test_does_not_install_extension_automatically(): void
    {
        self::assertStringNotContainsString(
            'CREATE EXTENSION',
            $this->source()
        );
    }
}