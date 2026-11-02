<?php

namespace App\Actions\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeRolePermissionEffectsBatch
{
    public function execute(
        int $roleId,
        int $actorId,
        array $changes
    ): array {
        $result = DB::selectOne(
            'SELECT system.apply_role_permission_changes(
                CAST(? AS bigint),
                CAST(? AS bigint),
                CAST(? AS jsonb)
            ) AS result',
            [
                $roleId,
                $actorId,
                json_encode($changes, JSON_THROW_ON_ERROR),
            ]
        );

        if (!$result || !isset($result->result)) {
            throw ValidationException::withMessages([
                'changes' => ['No se recibió una confirmación válida.'],
            ]);
        }

        $decoded = json_decode(
            $result->result,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($decoded) ||
            ($decoded['role_id'] ?? null) !== $roleId ||
            ($decoded['updated'] ?? null) !== count($changes) ||
            !isset($decoded['data']) ||
            !is_array($decoded['data'])) {
            throw ValidationException::withMessages([
                'changes' => ['Respuesta administrativa inválida.'],
            ]);
        }

        return $decoded;
    }
}
