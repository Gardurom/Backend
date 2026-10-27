<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = DB::table('system.roles')
            ->select([
                'id',
                'code',
                'name',
            ])
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }
}