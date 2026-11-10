<?php

namespace App\Http\Controllers\Api;

use App\Actions\Academic\RegisterAcademicOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AcademicOrganizationRegistrationController extends Controller
{
    public function storeProgram(
        Request $request,
        RegisterAcademicOrganization $register
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
                'message' => 'El nombre no puede estar vacio.',
            ], 422);
        }

        return response()->json([
            'data' => $register->program(
                $request->user(),
                $data['codigo'],
                $name
            ),
        ], 201);
    }

    public function storeCohort(
        Request $request,
        RegisterAcademicOrganization $register
    ): JsonResponse {
        $data = $request->validate([
            'id_programa' => ['required', 'uuid'],
            'tipo' => ['required', Rule::in(['G', 'E'])],
            'numero' => ['required', 'integer', 'min:1'],
            'anio' => [
                'required',
                'integer',
                'between:1900,9999',
            ],
        ]);

        return response()->json([
            'data' => $register->cohort(
                $request->user(),
                $data['id_programa'],
                $data['tipo'],
                (int) $data['numero'],
                (int) $data['anio']
            ),
        ], 201);
    }
}