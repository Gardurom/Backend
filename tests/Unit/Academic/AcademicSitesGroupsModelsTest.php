<?php

namespace Tests\Unit\Academic;

use App\Models\AcademicCohortSiteHistory;
use App\Models\AcademicGroup;
use App\Models\AcademicGroupSiteHistory;
use App\Models\AcademicSite;
use PHPUnit\Framework\TestCase;

final class AcademicSitesGroupsModelsTest extends TestCase
{
    public function test_models_map_to_institutional_tables(): void
    {
        $expected = [
            AcademicSite::class => [
                'institutional.academic_sites',
                'id_sede',
            ],
            AcademicGroup::class => [
                'institutional.academic_groups',
                'id_grupo',
            ],
            AcademicCohortSiteHistory::class => [
                'institutional.academic_cohort_site_history',
                'id_movimiento',
            ],
            AcademicGroupSiteHistory::class => [
                'institutional.academic_group_site_history',
                'id_movimiento',
            ],
        ];

        foreach ($expected as $class => [$table, $primaryKey]) {
            $model = new $class();

            self::assertSame($table, $model->getTable());
            self::assertSame($primaryKey, $model->getKeyName());
            self::assertFalse($model->getIncrementing());
            self::assertSame('string', $model->getKeyType());
        }
    }

    public function test_groups_allow_initially_null_specialization(): void
    {
        $group = new AcademicGroup([
            'codigo' => 'G1',
            'especializacion' => null,
        ]);

        self::assertNull(
            $group->getAttribute('especializacion')
        );
    }

    public function test_site_active_flag_is_boolean(): void
    {
        $site = new AcademicSite(['activa' => true]);

        self::assertTrue($site->activa);
    }

    public function test_history_models_contain_temporal_fields(): void
    {
        foreach ([
            AcademicCohortSiteHistory::class,
            AcademicGroupSiteHistory::class,
        ] as $class) {
            $model = new $class();

            self::assertContains(
                'vigente_desde',
                $model->getFillable()
            );

            self::assertContains(
                'vigente_hasta',
                $model->getFillable()
            );
        }
    }
}