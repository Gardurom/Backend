<?php

namespace Tests\Installation;

class PermissionStructureTest extends InstallationTestCase
{
    public function test_permissions_table_exists(): void
    {
        $table = $this->db->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'system'
                  AND table_name = 'permissions'
            ) AS exists"
        );

        self::assertTrue(
            (bool) $table->exists,
            'Debe existir la tabla system.permissions.'
        );
    }

    public function test_permissions_table_has_expected_columns(): void
    {
        $columns = $this->db->select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'permissions'
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
                'created_at',
                'updated_at',
            ],
            $names
        );
    }

    public function test_permissions_code_is_unique(): void
    {
        $constraint = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'permissions'
               AND c.contype = 'u'
               AND pg_get_constraintdef(c.oid)
                   LIKE '%(code)%'"
        );

        self::assertNotNull(
            $constraint,
            'system.permissions.code debe ser único.'
        );
    }

    public function test_application_has_read_only_access_to_permissions(): void
    {
        $privileges = $this->db->selectOne(
            "SELECT
                has_table_privilege(
                    current_user,
                    'system.permissions',
                    'SELECT'
                ) AS can_select,
                has_table_privilege(
                    current_user,
                    'system.permissions',
                    'INSERT'
                ) AS can_insert,
                has_table_privilege(
                    current_user,
                    'system.permissions',
                    'UPDATE'
                ) AS can_update,
                has_table_privilege(
                    current_user,
                    'system.permissions',
                    'DELETE'
                ) AS can_delete"
        );

        self::assertTrue((bool) $privileges->can_select);
        self::assertFalse((bool) $privileges->can_insert);
        self::assertFalse((bool) $privileges->can_update);
        self::assertFalse((bool) $privileges->can_delete);
    }
}
