<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'SIGA: se requiere transaccion institucional.'
            );
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS db, session_user AS actor'
        );

        if (
            $identity?->db !== 'siga' ||
            $identity?->actor !== 'siga_migrator'
        ) {
            throw new RuntimeException(
                'SIGA: migrador institucional requerido.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                'LOCK TABLE system.role_permissions
                 IN SHARE ROW EXCLUSIVE MODE'
            );

            $roleIds = DB::table('system.roles')
                ->where('code', 'ROL_ADMIN_SISTEMA')
                ->pluck('id');

            $permissionIds = DB::table('system.permissions')
                ->where('code', 'usuarios.asignar_perfiles')
                ->pluck('id');

            if (
                $roleIds->count() !== 1 ||
                $permissionIds->count() !== 1
            ) {
                throw new RuntimeException(
                    'SIGA: catalogos institucionales invalidos.'
                );
            }

            $roleId = (int) $roleIds[0];
            $permissionId = (int) $permissionIds[0];

            if (
                DB::table('system.role_permissions')
                    ->where('permission_id', $permissionId)
                    ->exists()
            ) {
                throw new RuntimeException(
                    'SIGA: permiso ya asignado; no duplicar.'
                );
            }

            $enabled = DB::table('system.users')
                ->where('access_status', 'HABILITADO')
                ->count();

            $eligible = DB::table('system.users as u')
                ->join(
                    'institutional.persons as p',
                    'p.id_persona',
                    '=',
                    'u.id_persona'
                )
                ->where('u.access_status', 'HABILITADO')
                ->where('p.estatus', 'ACTIVO')
                ->where('u.access_security_blocked', false)
                ->count();

            $admins = DB::table('system.user_roles as ur')
                ->join(
                    'system.users as u',
                    'u.id',
                    '=',
                    'ur.user_id'
                )
                ->where('ur.role_id', $roleId)
                ->where('u.access_status', 'HABILITADO')
                ->distinct()
                ->count('u.id');

            if (
                $enabled !== 3 ||
                $eligible !== 3 ||
                $admins !== 1
            ) {
                throw new RuntimeException(
                    'SIGA: distribucion institucional inesperada.'
                );
            }

            DB::table('system.role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'effect' => 'ALLOW',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $actual = DB::table('system.role_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->where('effect', 'ALLOW')
                ->count();

            if ($actual !== 1) {
                throw new RuntimeException(
                    'SIGA: regla no conciliada.'
                );
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'SIGA: retiro sujeto a autorizacion expresa.'
        );
    }
};