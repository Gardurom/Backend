<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\CreateUser;
use App\Http\Controllers\Controller;
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