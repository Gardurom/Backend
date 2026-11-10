<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'USR-02: se requiere transaccion institucional.'
            );
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS db, session_user AS actor'
        );

        if (
            $identity?->db !== 'siga'
            || $identity?->actor !== 'siga_migrator'
        ) {
            throw new RuntimeException(
                'USR-02: migrador institucional requerido.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                'LOCK TABLE system.permissions, system.role_permissions
                 IN SHARE ROW EXCLUSIVE MODE'
            );

            $roles = DB::table('system.roles')
                ->where('code', 'ROL_ADMIN_SISTEMA')
                ->pluck('id');

            if ($roles->count() !== 1) {
                throw new RuntimeException(
                    'USR-02: administrador institucional ambiguo.'
                );
            }

            $roleId = (int) $roles[0];

            $existingCodes = [
                'usuarios.asignar_roles',
                'usuarios.retirar_roles_masivamente',
            ];

            if (
                DB::table('system.permissions')
                    ->whereIn('code', $existingCodes)
                    ->count() !== 2
            ) {
                throw new RuntimeException(
                    'USR-02: catalogo anterior divergente.'
                );
            }

            $rules = DB::table('system.role_permissions as rp')
                ->join(
                    'system.permissions as p',
                    'p.id',
                    '=',
                    'rp.permission_id'
                )
                ->whereIn('p.code', $existingCodes)
                ->get(['p.code', 'rp.role_id', 'rp.effect']);

            if ($rules->count() !== 2) {
                throw new RuntimeException(
                    'USR-02: matriz anterior divergente.'
                );
            }

            foreach ($existingCodes as $code) {
                $matches = $rules->filter(
                    fn ($row): bool =>
                        $row->code === $code
                        && (int) $row->role_id === $roleId
                        && $row->effect === 'ALLOW'
                );

                if ($matches->count() !== 1) {
                    throw new RuntimeException(
                        'USR-02: regla administrativa anterior invalida.'
                    );
                }
            }

            if (
                DB::table('system.permissions')
                    ->where('code', 'usuarios.retirar_roles')
                    ->exists()
            ) {
                throw new RuntimeException(
                    'USR-02: permiso ya registrado.'
                );
            }

            $admins = DB::table('system.users as u')
                ->join(
                    'system.user_roles as ur',
                    'ur.user_id',
                    '=',
                    'u.id'
                )
                ->where('ur.role_id', $roleId)
                ->where('u.access_status', 'HABILITADO')
                ->distinct()
                ->count('u.id');

            if ($admins < 1) {
                throw new RuntimeException(
                    'USR-02: falta administrador habilitado.'
                );
            }

            $permissionId = DB::table('system.permissions')
                ->insertGetId([
                    'name' => 'Retirar roles individuales de usuarios',
                    'code' => 'usuarios.retirar_roles',
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id');

            DB::table('system.role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'effect' => 'ALLOW',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $verified = DB::table('system.role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->where('effect', 'ALLOW')
                ->count();

            if ($verified !== 1) {
                throw new RuntimeException(
                    'USR-02: regla insertada no conciliada.'
                );
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'USR-02: la reversion requiere autorizacion expresa.'
        );
    }
};