<?php

namespace Tests\Installation;

class UserPersonRelationshipTest extends InstallationTestCase
{
    public function test_users_table_has_person_uuid_reference(): void
    {
        $column = $this->db->selectOne(
            "SELECT data_type
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = 'users'
               AND column_name = 'id_persona'"
        );

        self::assertNotNull(
            $column,
            'system.users debe incluir la columna id_persona.'
        );

        self::assertSame(
            'uuid',
            $column->data_type,
            'system.users.id_persona debe utilizar UUID.'
        );
    }

    public function test_users_person_reference_is_nullable(): void
    {
        $column = $this->db->selectOne(
            "SELECT is_nullable
         FROM information_schema.columns
         WHERE table_schema = 'system'
           AND table_name = 'users'
           AND column_name = 'id_persona'"
        );

        self::assertNotNull($column);

        self::assertSame(
            'YES',
            $column->is_nullable,
            'system.users.id_persona debe permitir NULL mientras existan cuentas no vinculadas.'
        );
    }

    public function test_users_person_reference_is_unique(): void
    {
        $constraint = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
         FROM pg_catalog.pg_constraint AS c
         JOIN pg_catalog.pg_class AS t
           ON t.oid = c.conrelid
         JOIN pg_catalog.pg_namespace AS n
           ON n.oid = t.relnamespace
         WHERE n.nspname = 'system'
           AND t.relname = 'users'
           AND c.contype = 'u'
           AND pg_get_constraintdef(c.oid) LIKE '%(id_persona)%'"
        );

        self::assertNotNull(
            $constraint,
            'Una Persona no debe estar vinculada a más de un usuario.'
        );
    }

    public function test_users_person_reference_has_foreign_key(): void
    {
        $constraint = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
         FROM pg_catalog.pg_constraint AS c
         JOIN pg_catalog.pg_class AS t
           ON t.oid = c.conrelid
         JOIN pg_catalog.pg_namespace AS n
           ON n.oid = t.relnamespace
         WHERE n.nspname = 'system'
           AND t.relname = 'users'
           AND c.contype = 'f'
           AND pg_get_constraintdef(c.oid)
               LIKE 'FOREIGN KEY (id_persona)%'"
        );

        self::assertNotNull(
            $constraint,
            'system.users.id_persona debe tener clave foránea.'
        );

        self::assertStringContainsString(
            'REFERENCES institutional.persons(id_persona)',
            $constraint->definition
        );
    }
}
