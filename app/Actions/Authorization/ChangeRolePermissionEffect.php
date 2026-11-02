<?php

namespace App\Actions\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeRolePermissionEffect
{
    public function execute(
        int $roleId,
        int $permissionId,
        int $actorId,
        string $previousEffect,
        string $newEffect
    ): array {
        return DB::transaction(function () use (
            $roleId,
            $permissionId,
            $actorId,
            $previousEffect,
            $newEffect
        ): array {
            $role = DB::table('system.roles')
                ->where('id', $roleId)
                ->lockForUpdate()
                ->first(['id', 'code']);

            abort_if($role === null, 404);

            if ($role->code === 'ROL_ADMIN_SISTEMA') {
                throw ValidationException::withMessages([
                    'role' => ['El rol administrador está protegido.'],
                ]);
            }

            // El operador no puede cambiar las reglas de un rol propio.
            if (DB::table('system.user_roles')
                ->where('user_id', $actorId)
                ->where('role_id', $roleId)
                ->exists()) {
                throw ValidationException::withMessages([
                    'role' => [
                        'No puedes modificar permisos de un rol asignado a tu propia cuenta.',
                    ],
                ]);
            }

            $permission = DB::table('system.permissions')
                ->where('id', $permissionId)
                ->first(['id', 'code']);

            abort_if($permission === null, 404);

            $rule = DB::table('system.role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->lockForUpdate()
                ->first(['effect']);

            abort_if($rule === null, 404);

            $current = strtoupper((string) $rule->effect);

            // Impide sobrescribir una modificación concurrente.
            if ($current !== $previousEffect) {
                abort(409, 'La regla cambió. Actualiza la consulta antes de intentar nuevamente.');
            }

            if ($newEffect === $current) {
                throw ValidationException::withMessages([
                    'effect' => ['Selecciona el estado contrario al actual.'],
                ]);
            }

            if (
                !in_array($current, ['ALLOW', 'DENY'], true)
                || !in_array($newEffect, ['ALLOW', 'DENY'], true)
            ) {
                throw ValidationException::withMessages([
                    'effect' => ['Estado de autorización no reconocido.'],
                ]);
            }

            DB::table('system.role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->update([
                    'effect' => $newEffect,
                    'updated_at' => now(),
                ]);

            $metadata = [
                'role_id' => $roleId,
                'role_code' => $role->code,
                'permission_id' => $permissionId,
                'permission_code' => $permission->code,
            ];

            DB::table('system.activities')->insert([
                'id_usuario' => $actorId,
                'entidad' => 'ROL_PERMISO',
                'id_entidad' => (string) $roleId,
                'accion' => 'ACTUALIZACION',
                'datos_anteriores' => json_encode(
                    array_merge($metadata, ['effect' => $current]),
                    JSON_THROW_ON_ERROR
                ),
                'datos_nuevos' => json_encode(
                    array_merge($metadata, ['effect' => $newEffect]),
                    JSON_THROW_ON_ERROR
                ),
                'campos_modificados' => json_encode(
                    ['effect'],
                    JSON_THROW_ON_ERROR
                ),
            ]);

            return [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'code' => $permission->code,
                'previous_effect' => $current,
                'effect' => $newEffect,
            ];
        });
    }
}
