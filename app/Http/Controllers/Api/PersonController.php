<?php

namespace App\Http\Controllers\Api;

use App\Actions\Persona\FindPerson;
use App\Actions\Persona\RegisterPerson;
use App\Actions\Persona\ReinstatePerson;
use App\Actions\Persona\UpdatePerson;
use App\Actions\Persona\WithdrawPerson;
use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Persona\PersonInputRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $validated = $request->validate(
            PersonInputRules::forCreate()
        );

        $person = $registerPerson->execute(
            (int) $request->user()->getAuthIdentifier(),
            $validated
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
        $currentPerson = Person::query()
            ->findOrFail($idPersona);

        $validated = $request->validate(
            PersonInputRules::forUpdate()
        );

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

        $sinApellidoPaterno =
            $apellidoPaterno === null
            || trim($apellidoPaterno) === '';

        $sinApellidoMaterno =
            $apellidoMaterno === null
            || trim($apellidoMaterno) === '';

        if (
            $sinApellidoPaterno
            && $sinApellidoMaterno
        ) {
            $errors = [];

            if (
                array_key_exists(
                    'apellido_paterno',
                    $validated
                )
            ) {
                $errors['apellido_paterno'] = [
                    'La Persona debe conservar al menos un apellido.',
                ];
            }

            if (
                array_key_exists(
                    'apellido_materno',
                    $validated
                )
            ) {
                $errors['apellido_materno'] = [
                    'La Persona debe conservar al menos un apellido.',
                ];
            }

            throw ValidationException::withMessages(
                $errors
            );
        }

        $person = $updatePerson->execute(
            (int) $request->user()->getAuthIdentifier(),
            $idPersona,
            $validated
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
