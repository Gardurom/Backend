<?php

namespace App\Http\Controllers\Api;

use App\Actions\Persona\FindPersonRegistrationOrigin;
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

    public function availablePersons(
        Request $request,
        FindPersonRegistrationOrigin $findOrigin
    ): JsonResponse {
        $validated = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'persona' => ['nullable', 'string', 'max:100'],
            'expediente' => ['nullable', 'string', 'max:13'],
            'fecha_registro' => ['nullable', 'date_format:Y-m-d'],
            'forma_registro' => ['nullable', Rule::in(['INDIVIDUAL', 'MASIVO', 'NO_DETERMINADO'])],
        ]);

        $buscar = trim($validated['buscar'] ?? '');
        $persona = trim($validated['persona'] ?? '');
        $expediente = trim($validated['expediente'] ?? '');
        $fechaRegistro = $validated['fecha_registro'] ?? null;
        $formaRegistro = $validated['forma_registro'] ?? null;

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
            ->when($persona !== '', function ($query) use ($persona) {
                $query->whereRaw(
                    "concat_ws(' ', nombres, apellido_paterno, apellido_materno) ILIKE ?",
                    ['%'.$persona.'%']
                );
            })
            ->when($expediente !== '', function ($query) use ($expediente) {
                $query->where('num_expediente', 'ilike', '%'.$expediente.'%');
            })
            ->when($fechaRegistro !== null, function ($query) use ($fechaRegistro) {
                $query->whereRaw(
                    "(created_at AT TIME ZONE 'America/Mexico_City')::date = ?::date",
                    [$fechaRegistro]
                );
            })
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
            ->when($formaRegistro !== null, function ($query) use ($formaRegistro) {
                $query->whereRaw(<<<'SQL'
                    (
                        CASE
                            WHEN (
                                SELECT COUNT(*)
                                FROM system.import_rows AS r
                                JOIN system.import_batches AS b
                                    ON b.id = r.import_batch_id
                                WHERE b.import_type = 'PERSONAS'
                                    AND r.status = 'PROCESSED'
                                    AND r.result_data ->> 'id_persona'
                                        = institutional.persons.id_persona::text
                            ) = 1
                            AND COALESCE((
                                SELECT a.datos_nuevos::jsonb ->> 'origen_registro'
                                FROM system.activities AS a
                                WHERE a.entidad = 'PERSONA'
                                    AND a.id_entidad::text
                                        = institutional.persons.id_persona::text
                                    AND a.accion = 'CREACION'
                                ORDER BY a.id_actividad
                                LIMIT 1
                            ), '') <> 'INDIVIDUAL'
                                THEN 'MASIVO'
                            WHEN (
                                SELECT COUNT(*)
                                FROM system.import_rows AS r
                                JOIN system.import_batches AS b
                                    ON b.id = r.import_batch_id
                                WHERE b.import_type = 'PERSONAS'
                                    AND r.status = 'PROCESSED'
                                    AND r.result_data ->> 'id_persona'
                                        = institutional.persons.id_persona::text
                            ) = 0
                            AND (
                                SELECT a.datos_nuevos::jsonb ->> 'origen_registro'
                                FROM system.activities AS a
                                WHERE a.entidad = 'PERSONA'
                                    AND a.id_entidad::text
                                        = institutional.persons.id_persona::text
                                    AND a.accion = 'CREACION'
                                ORDER BY a.id_actividad
                                LIMIT 1
                            ) = 'INDIVIDUAL'
                                THEN 'INDIVIDUAL'
                            ELSE 'NO_DETERMINADO'
                        END
                    ) = ?
                SQL, [$formaRegistro]);
            })
            ->orderBy('num_expediente')
            ->paginate(20);

        $data = array_map(
            function (Person $person) use ($findOrigin): array {
                $origin = $findOrigin->execute($person->id_persona);

                return array_merge($person->toArray(), [
                    'fecha_registro' => $origin['fecha_registro'],
                    'forma_registro' => $origin['forma_registro'],
                ]);
            },
            $persons->items()
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $persons->currentPage(),
                'per_page' => $persons->perPage(),
                'last_page' => $persons->lastPage(),
                'total' => $persons->total(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::exists('pgsql.system.roles', 'id'),
            ],
        ]);

        $roleId = isset($validated['role_id'])
            ? (int) $validated['role_id']
            : null;

        $query = User::query()
            ->select([
                'id',
                'id_persona',
                'name',
                'email',
                'created_at',
            ])
            ->with([
                'person:id_persona,nombres,apellido_paterno,apellido_materno',
                'roles:id,code,name',
            ])
            ->orderBy('id');

        if ($roleId !== null) {
            $query->whereHas('roles', function ($roles) use ($roleId): void {
                $roles->where('system.roles.id', $roleId);
            });
        }

        $users = $query->paginate(20);

        $users->getCollection()->each(function (User $user): void {
            $user->roles->each(
                fn ($role) => $role->makeHidden(['pivot'])
            );
        });

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
