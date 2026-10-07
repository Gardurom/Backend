<?php

namespace Tests\Installation;

class RoleCatalogTest extends InstallationTestCase
{
    public function test_initial_roles_catalog_exists(): void
    {
        $expected = [
            'ROL_ADMIN_SISTEMA' => 'Administrador del Sistema',
            'ROL_AUDITOR' => 'Auditor',
            'ROL_CONSULTA_PERSONAS' => 'Consulta de Personas',
            'ROL_GESTOR_PERSONAS' => 'Gestor de Personas',
        ];

        $roles = $this->db->select(
            "SELECT code, name
             FROM system.roles
             WHERE code = ANY (ARRAY[
                 'ROL_ADMIN_SISTEMA',
                 'ROL_AUDITOR',
                 'ROL_CONSULTA_PERSONAS',
                 'ROL_GESTOR_PERSONAS'
             ])
             ORDER BY code"
        );

        $actual = [];

        foreach ($roles as $role) {
            $actual[$role->code] = $role->name;
        }

        self::assertSame(
            $expected,
            $actual,
            'El catálogo inicial de roles de SIGA no coincide con lo esperado.'
        );
    }

    public function test_initial_role_codes_are_unique(): void
    {
        $duplicates = $this->db->select(
            "SELECT code, COUNT(*) AS total
             FROM system.roles
             WHERE code = ANY (ARRAY[
                 'ROL_ADMIN_SISTEMA',
                 'ROL_AUDITOR',
                 'ROL_CONSULTA_PERSONAS',
                 'ROL_GESTOR_PERSONAS'
             ])
             GROUP BY code
             HAVING COUNT(*) > 1"
        );

        self::assertSame(
            [],
            $duplicates,
            'Los códigos del catálogo inicial de roles deben ser únicos.'
        );
    }
}
