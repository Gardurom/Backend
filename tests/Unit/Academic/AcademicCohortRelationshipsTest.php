<?php

namespace Tests\Unit\Academic;

use App\Models\AcademicCohort;
use App\Models\AcademicCohortSiteHistory;
use App\Models\AcademicGroup;
use PHPUnit\Framework\TestCase;

final class AcademicCohortRelationshipsTest extends TestCase
{
    public function test_cohort_declares_group_relationship(): void
    {
        $method = new \ReflectionMethod(
            AcademicCohort::class,
            'groups'
        );

        self::assertSame(
            'Illuminate\Database\Eloquent\Relations\HasMany',
            (string) $method->getReturnType()
        );
    }

    public function test_cohort_declares_site_history_relationship(): void
    {
        $method = new \ReflectionMethod(
            AcademicCohort::class,
            'siteAssignments'
        );

        self::assertSame(
            'Illuminate\Database\Eloquent\Relations\HasMany',
            (string) $method->getReturnType()
        );
    }

    public function test_related_models_have_correct_keys(): void
    {
        self::assertSame(
            'id_cohorte',
            (new AcademicCohort())->getKeyName()
        );

        self::assertSame(
            'id_grupo',
            (new AcademicGroup())->getKeyName()
        );

        self::assertSame(
            'id_movimiento',
            (new AcademicCohortSiteHistory())->getKeyName()
        );
    }

    public function test_relationships_reference_expected_models(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Models/AcademicCohort.php'
        );

        self::assertIsString($source);

        self::assertStringContainsString(
            'AcademicGroup::class',
            $source
        );

        self::assertStringContainsString(
            'AcademicCohortSiteHistory::class',
            $source
        );
    }
}