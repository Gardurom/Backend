<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\CreateUser;
use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function store(
        Request $request,
        CreateUser $createUser
    ): JsonResponse {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => mb_strtolower(
                    trim($request->input('email'))
                ),
            ]);
        }

        $validated = $request->validate([
            'id_persona' => [
                'required',
                'uuid',
                Rule::exists('pgsql.institutional.persons', 'id_persona'),
                Rule::unique('pgsql.system.users', 'id_persona'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('pgsql.system.users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:15',
                'confirmed',
            ],
        ]);

        $user = $createUser->execute(
            $validated['id_persona'],
            $validated['email'],
            $validated['password']
        );

        return response()->json([
            'id' => $user->id,
            'id_persona' => $user->id_persona,
            'name' => $user->name,
            'email' => $user->email,
        ], 201);
    }

    public function availablePersons(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $buscar = trim($validated['buscar'] ?? '');

        $persons = Person::query()
            ->select([
                'id_persona',
                'num_expediente',
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'estatus',
            ])
            ->whereDoesntHave('user')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($q) use ($buscar) {
                    $texto = '%'.$buscar.'%';

                    $q->where('nombres', 'ilike', $texto)
                        ->orWhere('apellido_paterno', 'ilike', $texto)
                        ->orWhere('apellido_materno', 'ilike', $texto)
                        ->orWhereRaw(
                            'CAST(num_expediente AS TEXT) ILIKE ?',
                            [$texto]
                        );
                });
            })
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

    public function index(): JsonResponse
    {
        $users = User::query()
            ->select([
                'id',
                'id_persona',
                'name',
                'email',
                'created_at',
            ])
            ->with([
                'person:id_persona,nombres,apellido_paterno,apellido_materno',
            ])
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
