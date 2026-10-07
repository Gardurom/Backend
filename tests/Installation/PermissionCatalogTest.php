<?php

namespace Tests\Installation;

class PermissionCatalogTest extends InstallationTestCase
{
    public function test_initial_permissions_catalog_exists(): void
    {
        $expected = [
            'auditoria.ver' => 'Consultar auditoría',
            'personas.actualizar' => 'Actualizar personas',
            'personas.baja' => 'Dar de baja personas',
            'personas.crear' => 'Registrar personas',
            'personas.reingreso' => 'Reingresar personas',
            'personas.ver' => 'Consultar personas',
            'usuarios.asignar_roles' => 'Asignar roles a usuarios',
            'usuarios.crear' => 'Crear usuarios',
        ];

        $permissions = $this->db->select(
            "SELECT code, name
             FROM system.permissions
             WHERE code = ANY (ARRAY[
                 'auditoria.ver',
                 'personas.actualizar',
                 'personas.baja',
                 'personas.crear',
                 'personas.reingreso',
                 'personas.ver',
                 'usuarios.asignar_roles',
                 'usuarios.crear'
             ])
             ORDER BY code"
        );

        $actual = [];

        foreach ($permissions as $permission) {
            $actual[$permission->code] = $permission->name;
        }

        self::assertSame(
            $expected,
            $actual,
            'El catálogo inicial de permisos de SIGA no coincide con lo esperado.'
        );
    }

    public function test_initial_permission_codes_are_unique(): void
    {
        $duplicates = $this->db->select(
            "SELECT code, COUNT(*) AS total
             FROM system.permissions
             WHERE code = ANY (ARRAY[
                 'auditoria.ver',
                 'personas.actualizar',
                 'personas.baja',
                 'personas.crear',
                 'personas.reingreso',
                 'personas.ver',
                 'usuarios.asignar_roles',
                 'usuarios.crear'
             ])
             GROUP BY code
             HAVING COUNT(*) > 1"
        );

        self::assertSame(
            [],
            $duplicates,
            'Los códigos del catálogo inicial de permisos deben ser únicos.'
        );
    }
}
