<?php

namespace Tests\Installation;

class UserProfileRelationshipTest extends InstallationTestCase
{
    public function test_user_profiles_has_expected_structure_and_permissions(): void
    {
        $table = $this->db->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'system'
                  AND table_name = 'user_profiles'
            ) AS exists"
        );

        self::assertTrue(
            (bool) $table->exists,
            'Debe existir la tabla system.user_profiles.'
        );

        $columns = $this->db->select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'user_profiles'
             ORDER BY ordinal_position"
        );

        $names = array_map(
            fn ($column) => $column->column_name,
            $columns
        );

        self::assertSame(
            [
                'user_id',
                'profile_id',
                'is_default',
                'created_at',
                'updated_at',
            ],
            $names
        );

        $defaultColumn = $this->db->selectOne(
            "SELECT
                is_nullable = 'NO' AS is_required,
                column_default = 'false' AS defaults_false
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'user_profiles'
               AND column_name = 'is_default'"
        );

        self::assertNotNull($defaultColumn);
        self::assertTrue((bool) $defaultColumn->is_required);
        self::assertTrue((bool) $defaultColumn->defaults_false);

        $primaryKey = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'user_profiles'
               AND c.contype = 'p'"
        );

        self::assertNotNull($primaryKey);

        self::assertStringContainsString(
            'PRIMARY KEY (user_id, profile_id)',
            $primaryKey->definition
        );

        $foreignKeys = $this->db->select(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = 'user_profiles'
               AND c.contype = 'f'
             ORDER BY c.conname"
        );

        $definitions = array_map(
            fn ($constraint) => $constraint->definition,
            $foreignKeys
        );

        self::assertCount(2, $definitions);

        self::assertTrue(
            collect($definitions)->contains(
                fn ($definition) => str_contains(
                    $definition,
                    'FOREIGN KEY (user_id) REFERENCES system.users(id)'
                )
            )
        );

        self::assertTrue(
            collect($definitions)->contains(
                fn ($definition) => str_contains(
                    $definition,
                    'FOREIGN KEY (profile_id) REFERENCES system.profiles(id)'
                )
            )
        );

        $defaultIndex = $this->db->selectOne(
            "SELECT indexdef
             FROM pg_catalog.pg_indexes
             WHERE schemaname = 'system'
               AND tablename = 'user_profiles'
               AND indexname = 'user_profiles_one_default_per_user'"
        );

        self::assertNotNull(
            $defaultIndex,
            'Debe existir el índice que limita un perfil predeterminado por usuario.'
        );

        self::assertStringContainsString(
            'UNIQUE INDEX',
            $defaultIndex->indexdef
        );

        self::assertStringContainsString(
            '(user_id)',
            $defaultIndex->indexdef
        );

        self::assertStringContainsString(
            'WHERE is_default',
            $defaultIndex->indexdef
        );

        $privileges = $this->db->selectOne(
            "SELECT
                has_table_privilege(
                    current_user,
                    'system.user_profiles',
                    'SELECT'
                ) AS can_select,
                has_table_privilege(
                    current_user,
                    'system.user_profiles',
                    'INSERT'
                ) AS can_insert,
                has_table_privilege(
                    current_user,
                    'system.user_profiles',
                    'UPDATE'
                ) AS can_update,
                has_table_privilege(
                    current_user,
                    'system.user_profiles',
                    'DELETE'
                ) AS can_delete"
        );

        self::assertTrue((bool) $privileges->can_select);
        self::assertTrue((bool) $privileges->can_insert);
        self::assertTrue((bool) $privileges->can_update);
        self::assertTrue((bool) $privileges->can_delete);
    }
}
