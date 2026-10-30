<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\AssignRole;
use App\Actions\User\RemoveRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function index(int $user): JsonResponse
    {
        $target = User::query()->findOrFail($user);

        $roles = $target->roles()
            ->select([
                'system.roles.id',
                'system.roles.code',
                'system.roles.name',
            ])
            ->orderBy('system.roles.code')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
            ])
            ->values();

        return response()->json([
            'data' => $roles,
        ]);
    }
    public function destroy(
        Request $request,
        int $user,
        int $role,
        RemoveRole $removeRole
    ): JsonResponse {
        $target = User::query()->findOrFail($user);

        $targetRole = Role::query()->findOrFail($role);

        if ($targetRole->code === 'ROL_ADMIN_SISTEMA') {
            throw ValidationException::withMessages([
                'role_id' => [
                    'El rol Administrador del Sistema no puede retirarse mediante esta operacion.',
                ],
            ]);
        }

        $removed = $removeRole->execute(
            (int) $target->id,
            (int) $targetRole->id,
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'user_id' => $target->id,
            'role_id' => $targetRole->id,
            'removed' => $removed,
        ]);
    }
    public function store(
        Request $request,
        int $user,
        AssignRole $assignRole
    ): JsonResponse {
        $validated = $request->validate([
            'role_id' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $target = User::query()->findOrFail($user);

        $role = Role::query()->findOrFail(
            (int) $validated['role_id']
        );

        if ($role->code === 'ROL_ADMIN_SISTEMA') {
            throw ValidationException::withMessages([
                'role_id' => [
                    'La asignacion del rol Administrador del Sistema requiere un procedimiento especial.',
                ],
            ]);
        }

        $created = $assignRole->execute(
            (int) $target->id,
            (int) $role->id,
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'user_id' => $target->id,
            'role_id' => $role->id,
            'assigned' => $created,
        ], $created ? 201 : 200);
    }
}