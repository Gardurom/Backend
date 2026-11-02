<?php

namespace App\Http\Controllers\Api;

use App\Actions\Authorization\ChangeRolePermissionEffect;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RolePermissionEffectController extends Controller
{
    public function update(
        Request $request,
        int $role,
        int $permission,
        ChangeRolePermissionEffect $action
    ): JsonResponse {
        $data = $request->validate([
            'expected_effect' => [
                'required',
                Rule::in(['ALLOW', 'DENY']),
            ],
            'effect' => [
                'required',
                Rule::in(['ALLOW', 'DENY']),
            ],
        ]);

        $result = $action->execute(
            $role,
            $permission,
            (int) $request->user()->getAuthIdentifier(),
            $data['expected_effect'],
            $data['effect']
        );

        return response()->json(['data' => $result]);
    }
}
