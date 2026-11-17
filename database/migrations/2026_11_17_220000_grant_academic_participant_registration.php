<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SIGA_ACAD_PARTICIPANTES_REGISTRAR_V1
        // Solo concede un permiso al rol academico existente.

        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'SIGA: transaccion institucional requerida.'
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
                'SIGA: migrador institucional no autorizado.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            $role = DB::table('system.roles')
                ->where('code', 'ROL_ADMIN_ACADEMICO')
                ->first(['id']);

            $permission = DB::table('system.permissions')
                ->where('code', 'academico.participantes.registrar')
                ->first(['id']);

            if ($role === null || $permission === null) {
                throw new RuntimeException(
                    'SIGA: faltan catalogos institucionales.'
                );
            }

            $holders = DB::table('system.user_roles')
                ->where('role_id', $role->id)
                ->orderBy('user_id')
                ->pluck('user_id')
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($holders !== [1]) {
                throw new RuntimeException(
                    'SIGA: titulares academicos inesperados.'
                );
            }

            $already = DB::table('system.role_permissions')
                ->where('role_id', $role->id)
                ->where('permission_id', $permission->id)
                ->exists();

            if ($already) {
                throw new RuntimeException(
                    'SIGA: permiso ya configurado.'
                );
            }

            $denied = DB::table('system.user_roles as ur')
                ->join(
                    'system.role_permissions as rp',
                    'rp.role_id', '=', 'ur.role_id'
                )
                ->where('ur.user_id', 1)
                ->where('rp.permission_id', $permission->id)
                ->where('rp.effect', 'DENY')
                ->exists();

            if ($denied) {
                throw new RuntimeException(
                    'SIGA: DENY existente, operacion bloqueada.'
                );
            }

            $auditOperation =
                'GRANT_CADET_PARTICIPANT_REGISTRATION';

            if (
                DB::table('system.academic_role_provisioning_audit')
                    ->where('operation', $auditOperation)
                    ->exists()
            ) {
                throw new RuntimeException(
                    'SIGA: provisionamiento ya auditado.'
                );
            }

            DB::table('system.role_permissions')->insert([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'effect' => 'ALLOW',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table(
                'system.academic_role_provisioning_audit'
            )->insert([
                'role_id' => $role->id,
                'target_user_id' => 1,
                'executor_db' => $identity->actor,
                'operation' => $auditOperation,
                'permission_codes' => json_encode(
                    ['academico.participantes.registrar'],
                    JSON_THROW_ON_ERROR
                ),
                'authorization_reference' =>
                    'SIGA_ETAPA_5_5: AUTORIZO_PERMISO_ACADEMICO',
            ]);

            $confirmed = DB::table('system.role_permissions')
                ->where('role_id', $role->id)
                ->where('permission_id', $permission->id)
                ->where('effect', 'ALLOW')
                ->count();

            if ($confirmed !== 1) {
                throw new RuntimeException(
                    'SIGA: permiso no conciliado.'
                );
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'SIGA: retiro requiere autorizacion institucional.'
        );
    }
};