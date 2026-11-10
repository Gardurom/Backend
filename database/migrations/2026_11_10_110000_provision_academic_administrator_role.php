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
            $identity?->db !== 'siga'
            || $identity?->actor !== 'siga_migrator'
        ) {
            throw new RuntimeException(
                'SIGA: migrador institucional no autorizado.'
            );
        }

        DB::statement('SET ROLE siga_owner');

        try {
            $codes = [
                'academico.organizacion.ver',
                'academico.organizacion.registrar',
                'academico.organizacion.trasladar',
            ];

            // El rol administrador general permanece protegido.
            $systemRole = DB::table('system.roles')
                ->where('code', 'ROL_ADMIN_SISTEMA')
                ->first();

            if ($systemRole === null) {
                throw new RuntimeException(
                    'SIGA: no existe ROL_ADMIN_SISTEMA.'
                );
            }

            if (
                DB::table('system.roles')
                    ->where('code', 'ROL_ADMIN_ACADEMICO')
                    ->exists()
            ) {
                throw new RuntimeException(
                    'SIGA: el rol academico ya existe; no se duplicara.'
                );
            }

            $permissions = DB::table('system.permissions')
                ->whereIn('code', $codes)
                ->get(['id', 'code']);

            if ($permissions->count() !== 3) {
                throw new RuntimeException(
                    'SIGA: deben existir exactamente tres permisos academicos.'
                );
            }

            // Identificar al administrador por su rol y elegibilidad,
            // no por correo, nombre o identificador supuesto.
            $admins = DB::table('system.users as u')
                ->join(
                    'institutional.persons as person',
                    'person.id_persona',
                    '=',
                    'u.id_persona'
                )
                ->join(
                    'system.user_roles as ur',
                    'ur.user_id',
                    '=',
                    'u.id'
                )
                ->where('ur.role_id', $systemRole->id)
                ->where('u.access_status', 'HABILITADO')
                ->where('person.estatus', 'ACTIVO')
                ->where('u.access_security_blocked', false)
                ->distinct()
                ->pluck('u.id');

            if ($admins->count() !== 1) {
                throw new RuntimeException(
                    'SIGA: se requiere exactamente un administrador elegible.'
                );
            }

            $adminId = (int) $admins->first();

            // DENY tiene precedencia. No se debe eludir.
            $denied = DB::table('system.user_roles as ur')
                ->join(
                    'system.role_permissions as rp',
                    'rp.role_id',
                    '=',
                    'ur.role_id'
                )
                ->join(
                    'system.permissions as p',
                    'p.id',
                    '=',
                    'rp.permission_id'
                )
                ->where('ur.user_id', $adminId)
                ->whereIn('p.code', $codes)
                ->where('rp.effect', 'DENY')
                ->exists();

            if ($denied) {
                throw new RuntimeException(
                    'SIGA: DENY existente; no se concedera acceso.'
                );
            }

            // Auditoria de provisionamiento tecnico separada de
            // actividades ejecutadas por usuarios autenticados.
            // Nunca atribuir al admin una operacion del migrador.
            DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS system.academic_role_provisioning_audit (
    id_auditoria bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    role_id bigint NOT NULL REFERENCES system.roles(id),
    target_user_id bigint NOT NULL REFERENCES system.users(id),
    executor_db text NOT NULL,
    operation text NOT NULL,
    permission_codes jsonb NOT NULL,
    authorization_reference text NOT NULL,
    executed_at timestamptz NOT NULL DEFAULT clock_timestamp()
)
SQL);

            if (
                DB::table('system.academic_role_provisioning_audit')
                    ->exists()
            ) {
                throw new RuntimeException(
                    'SIGA: ya existe provisionamiento academico; revisar manualmente.'
                );
            }

            $roleId = DB::table('system.roles')->insertGetId([
                'code' => 'ROL_ADMIN_ACADEMICO',
                'name' => 'Administrador Academico',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id');

            foreach ($permissions as $permission) {
                DB::table('system.role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permission->id,
                    'effect' => 'ALLOW',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('system.user_roles')->insert([
                'user_id' => $adminId,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('system.academic_role_provisioning_audit')->insert([
                'role_id' => $roleId,
                'target_user_id' => $adminId,
                'executor_db' => $identity->actor,
                'operation' => 'PROVISION_ACADEMIC_ROLE',
                'permission_codes' => json_encode(
                    $codes,
                    JSON_THROW_ON_ERROR
                ),
                'authorization_reference' =>
                    'ACAD-O01.9: autorizacion institucional expresa',
            ]);

            // Verificar las tres reglas efectivas antes del commit.
            $count = DB::table('system.user_roles as ur')
                ->join(
                    'system.role_permissions as rp',
                    'rp.role_id',
                    '=',
                    'ur.role_id'
                )
                ->join(
                    'system.permissions as p',
                    'p.id',
                    '=',
                    'rp.permission_id'
                )
                ->where('ur.user_id', $adminId)
                ->where('ur.role_id', $roleId)
                ->where('rp.effect', 'ALLOW')
                ->whereIn('p.code', $codes)
                ->distinct()
                ->count('p.id');

            if ($count !== 3) {
                throw new RuntimeException(
                    'SIGA: asignacion academica incompleta.'
                );
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'SIGA: retiro automatico institucional bloqueado.'
        );
    }
};