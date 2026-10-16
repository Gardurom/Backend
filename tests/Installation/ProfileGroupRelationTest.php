<?php

namespace Tests\Installation;

class ProfileGroupRelationTest extends InstallationTestCase
{
    public function test_profiles_have_required_profile_group_relation(): void
    {
        $column = $this->db->selectOne(
            "SELECT
                data_type,
                is_nullable
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'profiles'
               AND column_name = 'profile_group_id'"
        );

        self::assertNotNull(
            $column,
            'system.profiles debe incluir profile_group_id.'
        );

        self::assertSame(
            'bigint',
            $column->data_type
        );

        self::assertSame(
            'NO',
            $column->is_nullable,
            'system.profiles.profile_group_id debe ser obligatorio.'
        );

        $foreignKey = $this->db->selectOne(
            "SELECT
                pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'profiles'
               AND c.contype = 'f'
               AND pg_get_constraintdef(c.oid)
                   LIKE '%profile_group_id%'
               AND pg_get_constraintdef(c.oid)
                   LIKE '%system.profile_groups%'"
        );

        self::assertNotNull(
            $foreignKey,
            'profile_group_id debe referenciar system.profile_groups(id).'
        );

        self::assertStringContainsString(
            'ON DELETE RESTRICT',
            strtoupper($foreignKey->definition),
            'No debe eliminarse un grupo que todavía tenga perfiles asociados.'
        );
    }

    public function test_initial_profiles_are_assigned_to_expected_groups(): void
    {
        $profiles = $this->db->select(
            "SELECT
                p.code AS profile_code,
                pg.code AS group_code
             FROM system.profiles AS p
             JOIN system.profile_groups AS pg
               ON pg.id = p.profile_group_id
             WHERE p.code = ANY (ARRAY[
                 'PERFIL_PERSONAS',
                 'PERFIL_AUDITORIA',
                 'PERFIL_ADMINISTRACION'
             ])
             ORDER BY p.code"
        );

        $actual = [];

        foreach ($profiles as $profile) {
            $actual[$profile->profile_code] = $profile->group_code;
        }

        self::assertSame(
            [
                'PERFIL_ADMINISTRACION' => 'GRUPO_ADMINISTRACION',
                'PERFIL_AUDITORIA' => 'GRUPO_CONTROL_SUPERVISION',
                'PERFIL_PERSONAS' => 'GRUPO_OPERACION',
            ],
            $actual,
            'Los perfiles iniciales deben pertenecer a sus grupos de trabajo correspondientes.'
        );
    }
}
