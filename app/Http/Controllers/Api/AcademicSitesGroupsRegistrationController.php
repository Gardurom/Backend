<?php

namespace App\Http\Controllers\Api;

use App\Actions\Academic\RegisterAcademicSitesGroups;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AcademicSitesGroupsRegistrationController extends Controller
{
    public function storeSite(
        Request $request,
        RegisterAcademicSitesGroups $register
    ): JsonResponse {
        $data = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9]+$/D',
            ],
            'nombre' => ['required', 'string', 'max:200'],
        ]);

        $name = trim($data['nombre']);

        if ($name === '') {
            return response()->json([
                'message' => 'El nombre de la sede es obligatorio.',
            ], 422);
        }

        return response()->json([
            'data' => $register->site(
                $request->user(),
                $data['codigo'],
                $name
            ),
        ], 201);
    }

    public function storeGroup(
        Request $request,
        RegisterAcademicSitesGroups $register
    ): JsonResponse {
        $data = $request->validate([
            'id_cohorte' => ['required', 'uuid'],
            'codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9]+$/D',
            ],
            'especializacion' => [
                'nullable',
                Rule::in(['CAMPO', 'GABINETE']),
            ],
        ]);

        return response()->json([
            'data' => $register->group(
                $request->user(),
                $data['id_cohorte'],
                $data['codigo'],
                $data['especializacion'] ?? null
            ),
        ], 201);
    }
}