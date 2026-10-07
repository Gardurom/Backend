<?php

namespace Tests\Installation;

class RolePermissionRelationshipTest extends InstallationTestCase
{
    public function test_role_permissions_table_exists(): void
    {
        $table = $this->db->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'system'
                  AND table_name = 'role_permissions'
            ) AS exists"
        );

        self::assertTrue(
            (bool) $table->exists,
            'Debe existir la tabla system.role_permissions.'
        );
    }

    public function test_role_permissions_table_has_expected_columns(): void
    {
        $columns = $this->db->select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'role_permissions'
             ORDER BY ordinal_position"
        );

        $names = array_map(
            fn ($column) => $column->column_name,
            $columns
        );

        self::assertSame(
            [
                'role_id',
                'permission_id',
                'created_at',
                'updated_at',
            ],
            $names
        );
    }

    public function test_role_permission_pair_is_primary_key(): void
    {
        $constraint = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'role_permissions'
               AND c.contype = 'p'"
        );

        self::assertNotNull(
            $constraint,
            'system.role_permissions debe tener clave primaria.'
        );

        self::assertStringContainsString(
            'PRIMARY KEY (role_id, permission_id)',
            $constraint->definition
        );
    }

    public function test_role_permissions_has_expected_foreign_keys(): void
    {
        $constraints = $this->db->select(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'role_permissions'
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
            'system.role_permissions debe tener dos claves foráneas.'
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

        self::assertTrue(
            collect($definitions)->contains(
                fn ($definition) => str_contains(
                    $definition,
                    'FOREIGN KEY (permission_id) REFERENCES system.permissions(id)'
                )
            ),
            'Debe existir la FK hacia system.permissions.'
        );
    }

    public function test_application_has_expected_access_to_role_permissions(): void
    {
        $exists = $this->db->selectOne(
            "SELECT to_regclass(
                'system.role_permissions'
            ) IS NOT NULL AS exists"
        );

        if (! (bool) $exists->exists) {
            self::fail(
                'Debe existir system.role_permissions antes de verificar privilegios.'
            );
        }

        $privileges = $this->db->selectOne(
            "SELECT
                has_table_privilege(
                    current_user,
                    'system.role_permissions',
                    'SELECT'
                ) AS can_select,
                has_table_privilege(
                    current_user,
                    'system.role_permissions',
                    'INSERT'
                ) AS can_insert,
                has_table_privilege(
                    current_user,
                    'system.role_permissions',
                    'UPDATE'
                ) AS can_update,
                has_table_privilege(
                    current_user,
                    'system.role_permissions',
                    'DELETE'
                ) AS can_delete"
        );

        self::assertTrue((bool) $privileges->can_select);
        self::assertTrue((bool) $privileges->can_insert);
        self::assertFalse((bool) $privileges->can_update);
        self::assertTrue((bool) $privileges->can_delete);
    }
}
