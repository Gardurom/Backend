<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AccessCatalogController extends Controller
{
    public function permissions(): JsonResponse
    {
        $data = DB::table('system.permissions')
            ->select('id', 'code')
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function rolePermissions(int $role): JsonResponse
    {
        $exists = DB::table('system.roles')
            ->where('id', $role)
            ->exists();

        abort_unless($exists, 404);

        $data = DB::table('system.role_permissions as rp')
            ->join('system.permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $role)
            ->orderBy('p.code')
            ->get([
                'p.id',
                'p.code',
                'rp.effect',
            ]);

        return response()->json(['data' => $data]);
    }

    public function profileGroups(): JsonResponse
    {
        $data = DB::table('system.profile_groups')
            ->select('id', 'code', 'name', 'sort_order')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $data]);
    }
}
