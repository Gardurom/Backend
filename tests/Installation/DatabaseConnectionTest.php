<?php

namespace Tests\Installation;

class DatabaseConnectionTest extends InstallationTestCase
{
    public function test_connection_uses_application_role(): void
    {
        $result = $this->db->selectOne(
            'SELECT session_user AS login_role,
                    current_user AS effective_role'
        );

        self::assertSame(
            'siga_app',
            $result->login_role,
            'Las pruebas de instalacion deben conectarse como siga_app.'
        );

        self::assertSame(
            'siga_app',
            $result->effective_role,
            'La conexion debe conservar el rol de aplicacion.'
        );
    }

    public function test_transaction_is_read_only(): void
    {
        $result = $this->db->selectOne(
            "SELECT current_setting('transaction_read_only') AS read_only"
        );

        self::assertSame(
            'on',
            $result->read_only,
            'Las comprobaciones deben ejecutarse en una transaccion de solo lectura.'
        );
    }

    public function test_application_can_use_schemas_without_creating_objects(): void
    {
        foreach (['system', 'institutional'] as $schema) {
            $result = $this->db->selectOne(
                "SELECT
                    has_schema_privilege(current_user, ?, 'USAGE') AS can_use,
                    has_schema_privilege(current_user, ?, 'CREATE') AS can_create",
                [$schema, $schema]
            );

            self::assertTrue(
                $result->can_use,
                "siga_app necesita USAGE sobre el esquema {$schema}."
            );

            self::assertFalse(
                $result->can_create,
                "siga_app no debe tener CREATE sobre el esquema {$schema}."
            );
        }
    }
}