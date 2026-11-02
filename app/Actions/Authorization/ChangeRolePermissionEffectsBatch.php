<?php

namespace App\Actions\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeRolePermissionEffectsBatch
{
    public function execute(
        int $roleId,
        int $actorId,
        array $changes
    ): array {
        return DB::transaction(function () use (
            $roleId, $actorId, $changes
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

            if (DB::table('system.user_roles')
                ->where('user_id', $actorId)
                ->where('role_id', $roleId)
                ->exists()) {
                throw ValidationException::withMessages([
                    'role' => [
                        'No puedes modificar permisos de un rol asignado a tu cuenta.',
                    ],
                ]);
            }

            $ids = array_column($changes, 'permission_id');

            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'changes' => ['No se permiten permisos duplicados.'],
                ]);
            }

            $permissions = DB::table('system.permissions')
                ->whereIn('id', $ids)
                ->get(['id', 'code'])
                ->keyBy('id');

            $existing = DB::table('system.role_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $ids)
                ->orderBy('permission_id')
                ->lockForUpdate()
                ->get(['permission_id', 'effect'])
                ->keyBy('permission_id');

            // Validar todo antes de realizar cualquier cambio.
            foreach ($changes as $change) {
                $id = $change['permission_id'];
                $rule = $existing->get($id);

                if (!$permissions->has($id) || $rule === null) {
                    throw ValidationException::withMessages([
                        'changes' => ['Un permiso ya no pertenece a este rol.'],
                    ]);
                }

                if ((string) $rule->effect !== $change['expected_effect']) {
                    abort(409, 'Los permisos cambiaron. Actualiza antes de confirmar.');
                }

                if ($change['effect'] === $change['expected_effect']) {
                    throw ValidationException::withMessages([
                        'changes' => ['Cada cambio debe alternar su estado.'],
                    ]);
                }
            }

            $results = [];

            foreach ($changes as $change) {
                $id = $change['permission_id'];
                $code = $permissions->get($id)->code;
                $previous = $change['expected_effect'];
                $next = $change['effect'];

                DB::table('system.role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $id)
                    ->update([
                        'effect' => $next,
                        'updated_at' => now(),
                    ]);

                $metadata = [
                    'role_id' => $roleId,
                    'role_code' => $role->code,
                    'permission_id' => $id,
                    'permission_code' => $code,
                ];

                DB::table('system.activities')->insert([
                    'id_usuario' => $actorId,
                    'entidad' => 'ROL_PERMISO',
                    'id_entidad' => (string) $roleId,
                    'accion' => 'ACTUALIZACION',
                    'datos_anteriores' => json_encode(
                        array_merge($metadata, ['effect' => $previous]),
                        JSON_THROW_ON_ERROR
                    ),
                    'datos_nuevos' => json_encode(
                        array_merge($metadata, ['effect' => $next]),
                        JSON_THROW_ON_ERROR
                    ),
                    'campos_modificados' => json_encode(
                        ['effect'],
                        JSON_THROW_ON_ERROR
                    ),
                ]);

                $results[] = [
                    'permission_id' => $id,
                    'code' => $code,
                    'previous_effect' => $previous,
                    'effect' => $next,
                ];
            }

            return [
                'role_id' => $roleId,
                'updated' => count($results),
                'data' => $results,
            ];
        });
    }
}
