<?php

namespace App\Http\Controllers\Api;

use App\Access\ChangeUserAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserAccessController extends Controller
{
    public function enable(Request $request, int $user, ChangeUserAccess $change): JsonResponse
    {
        $request->validate(['roles_revisados' => ['required', 'accepted']]);
        $result = $change->execute($request->user(), $user, true, true);
        return response()->json($result->only(['id', 'id_persona', 'access_status', 'access_version']));
    }

    public function disable(Request $request, int $user, ChangeUserAccess $change): JsonResponse
    {
        $result = $change->execute($request->user(), $user, false, false);
        return response()->json($result->only(['id', 'id_persona', 'access_status', 'access_version']));
    }
}
