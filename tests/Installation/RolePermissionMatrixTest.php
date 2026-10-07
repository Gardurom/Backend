<?php

namespace Tests\Installation;

class RolePermissionMatrixTest extends InstallationTestCase
{
    public function test_initial_role_permission_matrix_matches_approved_design(): void
    {
        $expected = [
            'ROL_ADMIN_SISTEMA|usuarios.asignar_roles|ALLOW',
            'ROL_ADMIN_SISTEMA|usuarios.crear|ALLOW',
            'ROL_AUDITOR|auditoria.ver|ALLOW',
            'ROL_CONSULTA_PERSONAS|personas.ver|ALLOW',
            'ROL_GESTOR_PERSONAS|personas.actualizar|ALLOW',
            'ROL_GESTOR_PERSONAS|personas.baja|ALLOW',
            'ROL_GESTOR_PERSONAS|personas.crear|ALLOW',
            'ROL_GESTOR_PERSONAS|personas.reingreso|ALLOW',
            'ROL_GESTOR_PERSONAS|personas.ver|ALLOW',
        ];

        $relationships = $this->db->select(
            "SELECT
                r.code AS role_code,
                p.code AS permission_code,
                rp.effect
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
                '%s|%s|%s',
                $relationship->role_code,
                $relationship->permission_code,
                $relationship->effect
            ),
            $relationships
        );

        self::assertSame(
            $expected,
            $actual,
            'La matriz inicial de roles, permisos y efectos no coincide con el checkpoint aprobado.'
        );
    }
}
