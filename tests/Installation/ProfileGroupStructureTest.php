<?php

namespace Tests\Installation;

class ProfileGroupStructureTest extends InstallationTestCase
{
    public function test_profile_groups_table_has_expected_structure_and_permissions(): void
    {
        $table = $this->db->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'system'
                  AND table_name = 'profile_groups'
            ) AS exists"
        );

        self::assertTrue(
            (bool) $table->exists,
            'Debe existir la tabla system.profile_groups.'
        );

        $columns = $this->db->select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'profile_groups'
             ORDER BY ordinal_position"
        );

        $names = array_map(
            fn ($column) => $column->column_name,
            $columns
        );

        self::assertSame(
            [
                'id',
                'name',
                'code',
                'description',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ],
            $names
        );

        $constraint = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'profile_groups'
               AND c.contype = 'u'
               AND pg_get_constraintdef(c.oid)
                   LIKE '%(code)%'"
        );

        self::assertNotNull(
            $constraint,
            'system.profile_groups.code debe ser unico.'
        );

        $privileges = $this->db->selectOne(
            "SELECT
                has_table_privilege(
                    current_user,
                    'system.profile_groups',
                    'SELECT'
                ) AS can_select,
                has_table_privilege(
                    current_user,
                    'system.profile_groups',
                    'INSERT'
                ) AS can_insert,
                has_table_privilege(
                    current_user,
                    'system.profile_groups',
                    'UPDATE'
                ) AS can_update,
                has_table_privilege(
                    current_user,
                    'system.profile_groups',
                    'DELETE'
                ) AS can_delete"
        );

        self::assertTrue((bool) $privileges->can_select);
        self::assertFalse((bool) $privileges->can_insert);
        self::assertFalse((bool) $privileges->can_update);
        self::assertFalse((bool) $privileges->can_delete);
    }
}
