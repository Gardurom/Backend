<?php

namespace App\Http\Controllers\Api;

use App\Actions\Authorization\ChangeRolePermissionEffectsBatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RolePermissionBatchController extends Controller
{
    public function update(
        Request $request,
        int $role,
        ChangeRolePermissionEffectsBatch $action
    ): JsonResponse {
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:100'],
            'changes.*.permission_id' => [
                'required', 'integer', 'min:1',
            ],
            'changes.*.expected_effect' => [
                'required', Rule::in(['ALLOW', 'DENY']),
            ],
            'changes.*.effect' => [
                'required', Rule::in(['ALLOW', 'DENY']),
            ],
        ]);

        $result = $action->execute(
            $role,
            (int) $request->user()->getAuthIdentifier(),
            $data['changes']
        );

        return response()->json($result);
    }
}
