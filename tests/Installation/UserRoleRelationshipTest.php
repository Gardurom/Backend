<?php

namespace Tests\Installation;

class UserRoleRelationshipTest extends InstallationTestCase
{
    public function test_user_roles_table_exists(): void
    {
        $table = $this->db->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'system'
                  AND table_name = 'user_roles'
            ) AS exists"
        );

        self::assertTrue(
            (bool) $table->exists,
            'Debe existir la tabla system.user_roles.'
        );
    }

    public function test_user_roles_table_has_expected_columns(): void
    {
        $columns = $this->db->select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'user_roles'
             ORDER BY ordinal_position"
        );

        $names = array_map(
            fn ($column) => $column->column_name,
            $columns
        );

        self::assertSame(
            [
                'user_id',
                'role_id',
                'created_at',
                'updated_at',
            ],
            $names
        );
    }

    public function test_user_role_pair_is_primary_key(): void
    {
        $constraint = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'user_roles'
               AND c.contype = 'p'"
        );

        self::assertNotNull(
            $constraint,
            'system.user_roles debe tener clave primaria.'
        );

        self::assertStringContainsString(
            'PRIMARY KEY (user_id, role_id)',
            $constraint->definition
        );
    }

    public function test_user_roles_has_expected_foreign_keys(): void
    {
        $constraints = $this->db->select(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'user_roles'
               AND c.contype = 'f'
             ORDER BY c.conname"
        );

        $definitions = array_map(
            fn ($constraint) => $constraint->definition,
            $constraints
        );

        self::assertCount(
            2,
            $definitions,
            'system.user_roles debe tener dos claves foráneas.'
        );

        self::assertTrue(
            collect($definitions)->contains(
                fn ($definition) => str_contains(
                    $definition,
                    'FOREIGN KEY (user_id) REFERENCES system.users(id)'
                )
            ),
            'Debe existir la FK hacia system.users.'
        );

        self::assertTrue(
            collect($definitions)->contains(
                fn ($definition) => str_contains(
                    $definition,
                    'FOREIGN KEY (role_id) REFERENCES system.roles(id)'
                )
            ),
            'Debe existir la FK hacia system.roles.'
        );
    }

    public function test_application_has_expected_access_to_user_roles(): void
    {
        $privileges = $this->db->selectOne(
            "SELECT
                has_table_privilege(
                    current_user,
                    'system.user_roles',
                    'SELECT'
                ) AS can_select,
                has_table_privilege(
                    current_user,
                    'system.user_roles',
                    'INSERT'
                ) AS can_insert,
                has_table_privilege(
                    current_user,
                    'system.user_roles',
                    'UPDATE'
                ) AS can_update,
                has_table_privilege(
                    current_user,
                    'system.user_roles',
                    'DELETE'
                ) AS can_delete"
        );

        self::assertTrue((bool) $privileges->can_select);
        self::assertTrue((bool) $privileges->can_insert);
        self::assertFalse((bool) $privileges->can_update);
        self::assertTrue((bool) $privileges->can_delete);
    }
}
