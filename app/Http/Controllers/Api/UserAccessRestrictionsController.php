<?php

namespace App\Http\Controllers\Api;

use App\Access\AccessEligibility;
use App\Authorization\PermissionResolver;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserAccessRestrictionsController extends Controller
{
    private const FIELDS = [
        'access_security_blocked',
        'access_suspended_until',
        'access_valid_from',
        'access_valid_until',
    ];

    public function show(int $user): JsonResponse
    {
        $target = User::query()->findOrFail($user);

        return response()->json($this->responseData($target));
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $data = $request->validate([
            'access_security_blocked' => ['required', 'boolean'],
            'access_suspended_until' => ['present', 'nullable', 'date'],
            'access_valid_from' => ['present', 'nullable', 'date'],
            'access_valid_until' => ['present', 'nullable', 'date'],
            'access_version' => ['required', 'integer', 'min:1'],
        ]);

        $from = $data['access_valid_from'];
        $until = $data['access_valid_until'];

        if ($from !== null && $until !== null &&
            \Carbon\CarbonImmutable::parse($until)->lessThanOrEqualTo(
                \Carbon\CarbonImmutable::parse($from)
            )) {
            throw ValidationException::withMessages([
                'access_valid_until' => 'La fecha final debe ser posterior a la inicial.',
            ]);
        }

        try {
            $result = DB::transaction(function () use ($request, $user, $data) {
                DB::select('SELECT pg_advisory_xact_lock(73421, 101)');

                $actor = User::query()
                    ->lockForUpdate()
                    ->findOrFail($request->user()->getAuthIdentifier());

                abort_unless(
                    app(AccessEligibility::class)->allows($actor),
                    403
                );

                abort_unless(
                    $request->hasSession()
                    && (string) $request->session()->get('siga_access_version', '')
                        === (string) $actor->access_version,
                    401
                );

                $resolver = app(PermissionResolver::class);

                abort_unless(
                    $resolver->allows($actor, 'usuarios.habilitar')
                    && $resolver->allows($actor, 'usuarios.deshabilitar'),
                    403
                );

                $target = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user);

                if ((int) $target->access_version !== (int) $data['access_version']) {
                    return [
                        'conflict' => true,
                        'user' => $target,
                    ];
                }

                $changed = false;

                foreach (self::FIELDS as $field) {
                    $value = $data[$field];
                    $current = $target->{$field};

                    if ($field === 'access_security_blocked') {
                        $value = (bool) $value;
                        $different = $value !== (bool) $current;
                    } else {
                        $incoming = $value === null
                            ? null
                            : \Carbon\CarbonImmutable::parse($value)->utc();

                        $existing = $current === null
                            ? null
                            : \Carbon\CarbonImmutable::parse($current)->utc();

                        $different = ($incoming === null) !== ($existing === null)
                            || ($incoming !== null
                                && $existing !== null
                                && !$incoming->equalTo($existing));

                        $value = $incoming?->toDateTimeString();
                    }

                    if ($different) {
                        $target->{$field} = $value;
                        $changed = true;
                    }
                }

                if (!$changed) {
                    return [
                        'conflict' => false,
                        'user' => $target,
                    ];
                }

                DB::select(
                    "SELECT set_config('siga.actor_id', ?, true)",
                    [(string) $actor->getKey()]
                );

                $target->save();

                // Se ejecuta el trigger de integridad al confirmar.
                // La revocación y la auditoría forman parte
                // de la misma transacción PostgreSQL.

                return [
                    'conflict' => false,
                    'user' => $target->refresh(),
                ];
            });
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? '') === '23514'
                && str_contains($error->getMessage(), 'SIGA_LAST_ADMIN')) {
                return response()->json([
                    'message' => 'La operación dejaría SIGA sin administrador habilitado.',
                    'code' => 'LAST_ENABLED_ADMIN',
                ], 409);
            }

            throw $error;
        }

        if ($result['conflict']) {
            return response()->json([
                'message' => 'La cuenta cambió desde su última consulta. Actualice antes de confirmar.',
                'code' => 'ACCESS_VERSION_CONFLICT',
            ], 409);
        }

        return response()->json($this->responseData($result['user']));
    }

    private function responseData(User $user): array
    {
        return $user->only(array_merge([
            'id',
            'access_status',
            'access_version',
        ], self::FIELDS));
    }
}