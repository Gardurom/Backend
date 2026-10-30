<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulkUserRoleRemovalController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => [
                'required',
                'integer',
                Rule::exists('pgsql.system.roles', 'id'),
            ],
            'user_ids' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'user_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('pgsql.system.users', 'id'),
            ],
        ]);

        $role = Role::query()->findOrFail(
            (int) $validated['role_id']
        );

        if ($role->code === 'ROL_ADMIN_SISTEMA') {
            throw ValidationException::withMessages([
                'role_id' => [
                    'El rol Administrador del Sistema no admite retirada masiva.',
                ],
            ]);
        }

        $userIds = array_map(
            'intval',
            $validated['user_ids']
        );

        $actorId = (int) $request->user()->getAuthIdentifier();

        $result = DB::transaction(function () use ($role, $userIds, $actorId): array {
            $users = User::query()
                ->whereIn('id', $userIds)
                ->lockForUpdate()
                ->get(['id', 'id_persona']);

            if ($users->count() !== count($userIds)) {
                throw ValidationException::withMessages([
                    'user_ids' => [
                        'Uno o mas usuarios dejaron de estar disponibles.',
                    ],
                ]);
            }

            if ($users->contains(
                fn (User $user): bool => $user->id_persona === null
            )) {
                throw ValidationException::withMessages([
                    'user_ids' => [
                        'La operacion requiere cuentas vinculadas a Personas.',
                    ],
                ]);
            }

            $removedUserIds = [];
            $alreadyAbsentUserIds = [];

            foreach ($userIds as $userId) {
                $deleted = DB::table('system.user_roles')
                    ->where('user_id', $userId)
                    ->where('role_id', $role->id)
                    ->delete();

                if ($deleted > 0) {
                    app(\App\Actions\User\RecordUserRoleActivity::class)->record(
                        $actorId,
                        (int) $userId,
                        (int) $role->id,
                        (string) $role->code,
                        false
                    );

                    $removedUserIds[] = $userId;
                } else {
                    $alreadyAbsentUserIds[] = $userId;
                }
            }

            return [
                'selected' => count($userIds),
                'removed' => count($removedUserIds),
                'already_absent' => count($alreadyAbsentUserIds),
                'removed_user_ids' => $removedUserIds,
                'already_absent_user_ids' => $alreadyAbsentUserIds,
            ];
        });

        return response()->json([
            'role_id' => $role->id,
            'result' => $result,
        ]);
    }
}
