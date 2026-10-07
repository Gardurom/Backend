<?php

namespace Tests\Installation;

class RolePermissionMatrixTest extends InstallationTestCase
{
    public function test_initial_role_permission_matrix_matches_approved_design(): void
    {
        $expected = [
            'ROL_ADMIN_SISTEMA|usuarios.asignar_roles',
            'ROL_ADMIN_SISTEMA|usuarios.crear',
            'ROL_AUDITOR|auditoria.ver',
            'ROL_CONSULTA_PERSONAS|personas.ver',
            'ROL_GESTOR_PERSONAS|personas.actualizar',
            'ROL_GESTOR_PERSONAS|personas.baja',
            'ROL_GESTOR_PERSONAS|personas.crear',
            'ROL_GESTOR_PERSONAS|personas.reingreso',
            'ROL_GESTOR_PERSONAS|personas.ver',
        ];

        $relationships = $this->db->select(
            "SELECT r.code AS role_code,
                    p.code AS permission_code
             FROM system.role_permissions AS rp
             JOIN system.roles AS r
               ON r.id = rp.role_id
             JOIN system.permissions AS p
               ON p.id = rp.permission_id
             WHERE r.code = ANY (ARRAY[
                 'ROL_ADMIN_SISTEMA',
                 'ROL_AUDITOR',
                 'ROL_CONSULTA_PERSONAS',
                 'ROL_GESTOR_PERSONAS'
             ])
             ORDER BY r.code, p.code"
        );

        $actual = array_map(
            fn ($relationship) => sprintf(
                '%s|%s',
                $relationship->role_code,
                $relationship->permission_code
            ),
            $relationships
        );

        self::assertSame(
            $expected,
            $actual,
            'La matriz inicial de roles y permisos no coincide con el checkpoint aprobado.'
        );
    }
}
