<?php

namespace Tests\Installation;

class ProfileGroupCatalogTest extends InstallationTestCase
{
    public function test_initial_profile_groups_catalog_exists(): void
    {
        $expected = [
            'GRUPO_OPERACION' => [
                'name' => 'Operación',
                'sort_order' => 10,
                'is_active' => true,
            ],
            'GRUPO_CONTROL_SUPERVISION' => [
                'name' => 'Control y supervisión',
                'sort_order' => 20,
                'is_active' => true,
            ],
            'GRUPO_ADMINISTRACION' => [
                'name' => 'Administración',
                'sort_order' => 30,
                'is_active' => true,
            ],
        ];

        $groups = $this->db->select(
            "SELECT
                code,
                name,
                sort_order,
                is_active
             FROM system.profile_groups
             WHERE code = ANY (ARRAY[
                 'GRUPO_OPERACION',
                 'GRUPO_CONTROL_SUPERVISION',
                 'GRUPO_ADMINISTRACION'
             ])
             ORDER BY sort_order, code"
        );

        $actual = [];

        foreach ($groups as $group) {
            $actual[$group->code] = [
                'name' => $group->name,
                'sort_order' => (int) $group->sort_order,
                'is_active' => (bool) $group->is_active,
            ];
        }

        self::assertSame(
            $expected,
            $actual,
            'El catálogo inicial de grupos de perfiles de SIGA no coincide con lo esperado.'
        );
    }

    public function test_initial_profile_group_codes_are_unique(): void
    {
        $duplicates = $this->db->select(
            "SELECT code, COUNT(*) AS total
             FROM system.profile_groups
             WHERE code = ANY (ARRAY[
                 'GRUPO_OPERACION',
                 'GRUPO_CONTROL_SUPERVISION',
                 'GRUPO_ADMINISTRACION'
             ])
             GROUP BY code
             HAVING COUNT(*) > 1"
        );

        self::assertSame(
            [],
            $duplicates,
            'Los códigos del catálogo inicial de grupos de perfiles deben ser únicos.'
        );
    }
}
