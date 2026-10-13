<?php

namespace App\Http\Controllers\Api;

use App\Actions\Persona\FindPerson;
use App\Actions\Persona\RegisterPerson;
use App\Actions\Persona\ReinstatePerson;
use App\Actions\Persona\UpdatePerson;
use App\Actions\Persona\WithdrawPerson;
use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(): JsonResponse
    {
        $persons = Person::query()
            ->select([
                'id_persona',
                'num_expediente',
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'estatus',
            ])
            ->orderBy('num_expediente')
            ->paginate(20);

        return response()->json([
            'data' => $persons->items(),
            'meta' => [
                'current_page' => $persons->currentPage(),
                'per_page' => $persons->perPage(),
                'last_page' => $persons->lastPage(),
                'total' => $persons->total(),
            ],
        ]);
    }

    public function store(
        Request $request,
        RegisterPerson $registerPerson
    ): JsonResponse {

        // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $request->validate([
            'nombres' => ['required', 'string', 'max:150'],
            'apellido_paterno' => [
                'nullable',
                'string',
                'max:100',
                'required_without:apellido_materno',
            ],
            'apellido_materno' => [
                'nullable',
                'string',
                'max:100',
                'required_without:apellido_paterno',
            ],
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

        $currentPerson = Person::query()->findOrFail($idPersona);

        $validated = $request->validate([
            'nombres' => ['sometimes', 'required', 'string', 'max:150'],
            'apellido_paterno' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'apellido_materno' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
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

        $apellidoPaterno = array_key_exists(
            'apellido_paterno',
            $validated
        )
            ? $validated['apellido_paterno']
            : $currentPerson->apellido_paterno;

        $apellidoMaterno = array_key_exists(
            'apellido_materno',
            $validated
        )
            ? $validated['apellido_materno']
            : $currentPerson->apellido_materno;

        $sinApellidoPaterno = $apellidoPaterno === null
            || trim($apellidoPaterno) === '';

        $sinApellidoMaterno = $apellidoMaterno === null
            || trim($apellidoMaterno) === '';

        if ($sinApellidoPaterno && $sinApellidoMaterno) {
            $errors = [];

            if (array_key_exists('apellido_paterno', $validated)) {
                $errors['apellido_paterno'] = [
                    'La Persona debe conservar al menos un apellido.',
                ];
            }

            if (array_key_exists('apellido_materno', $validated)) {
                $errors['apellido_materno'] = [
                    'La Persona debe conservar al menos un apellido.',
                ];
            }

            throw \Illuminate\Validation\ValidationException::withMessages(
                $errors
            );
        }

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

    public function reinstate(
        Request $request,
        string $idPersona,
        ReinstatePerson $reinstatePerson
    ): JsonResponse {
        $person = $reinstatePerson->execute(
            (int) $request->user()->getAuthIdentifier(),
            $idPersona
        );

        return response()->json($person);
    }
}
