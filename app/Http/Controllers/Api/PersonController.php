<?php

namespace App\Http\Controllers\Api;

use App\Actions\Persona\FindPerson;
use App\Actions\Persona\RegisterPerson;
use App\Actions\Persona\UpdatePerson;
use App\Actions\Persona\WithdrawPerson;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function store(
        Request $request,
        RegisterPerson $registerPerson
    ): JsonResponse {

        // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $request->validate([
            'nombres' => ['required', 'string', 'max:150'],
            'sexo' => [
                'nullable',
                'string',
                'in:MASCULINO,FEMENINO',
            ],
            'estado_civil' => [
                'nullable',
                'string',
                'in:SOLTERO,CASADO',
            ],
            'fecha_nacimiento' => [
                'nullable',
                'date',
                'before_or_equal:'.now('America/Mexico_City')->toDateString(),
            ],
        ]);

        // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $person = $registerPerson->execute(
            (int) $request->user()->getAuthIdentifier(),
            $request->all()
        );

        return response()->json(
            $person,
            201
        );
    }

    public function show(
        string $idPersona,
        FindPerson $findPerson
    ): JsonResponse {
        $person = $findPerson->execute($idPersona);

        return response()->json($person);
    }

    public function update(
        Request $request,
        string $idPersona,
        UpdatePerson $updatePerson
    ): JsonResponse {

        $request->validate([
            'nombres' => ['sometimes', 'required', 'string', 'max:150'],
            'sexo' => [
                'sometimes',
                'nullable',
                'string',
                'in:MASCULINO,FEMENINO',
            ],
            'estado_civil' => [
                'sometimes',
                'nullable',
                'string',
                'in:SOLTERO,CASADO',
            ],
            'fecha_nacimiento' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:'.now('America/Mexico_City')->toDateString(),
            ],
        ]);

        $person = $updatePerson->execute(
            (int) $request->user()->getAuthIdentifier(),
            $idPersona,
            $request->all()
        );

        return response()->json($person);
    }

    public function withdraw(
        Request $request,
        string $idPersona,
        WithdrawPerson $withdrawPerson
    ): JsonResponse {
        $person = $withdrawPerson->execute(
            (int) $request->user()->getAuthIdentifier(),
            $idPersona
        );

        return response()->json($person);
    }
}
