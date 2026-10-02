<?php

namespace App\Http\Controllers\Api;

use App\Actions\Persona\RegisterPerson;
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
}
