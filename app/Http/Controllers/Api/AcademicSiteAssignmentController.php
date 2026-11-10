<?php

namespace App\Http\Controllers\Api;

use App\Actions\Academic\AssignAcademicSites;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcademicSiteAssignmentController extends Controller
{
    public function storeCohort(
        Request $request,
        AssignAcademicSites $assign
    ): JsonResponse {
        $data = $request->validate([
            'id_cohorte' => ['required', 'uuid'],
            'id_sede' => ['required', 'uuid'],
        ]);

        return response()->json([
            'data' => $assign->cohort(
                $request->user(),
                $data['id_cohorte'],
                $data['id_sede']
            ),
        ], 201);
    }

    public function storeGroup(
        Request $request,
        AssignAcademicSites $assign
    ): JsonResponse {
        $data = $request->validate([
            'id_grupo' => ['required', 'uuid'],
            'id_sede' => ['required', 'uuid'],
        ]);

        return response()->json([
            'data' => $assign->group(
                $request->user(),
                $data['id_grupo'],
                $data['id_sede']
            ),
        ], 201);
    }
}