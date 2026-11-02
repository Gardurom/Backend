<?php

namespace App\Actions\Authorization;

use Illuminate\Validation\ValidationException;

final class ChangeRolePermissionEffect
{
    public function __construct(
        private readonly ChangeRolePermissionEffectsBatch $batch
    ) {}

    public function execute(
        int $roleId,
        int $permissionId,
        int $actorId,
        string $previousEffect,
        string $newEffect
    ): array {
        // La operación individual utiliza exactamente la misma
        // autorización, concurrencia y auditoría que la operación por lote.
        $result = $this->batch->execute(
            $roleId,
            $actorId,
            [[
                'permission_id' => $permissionId,
                'expected_effect' => $previousEffect,
                'effect' => $newEffect,
            ]]
        );

        $item = $result['data'][0] ?? null;

        if (!is_array($item)) {
            throw ValidationException::withMessages([
                'effect' => [
                    'La operación no devolvió una regla válida.',
                ],
            ]);
        }

        $returnedId = $item['permission_id'] ?? null;
        $returnedEffect = $item['effect'] ?? null;

        if (
            (string) $returnedId !== (string) $permissionId
            || $returnedEffect !== $newEffect
        ) {
            throw ValidationException::withMessages([
                'effect' => [
                    'La respuesta administrativa no coincide con el cambio solicitado.',
                ],
            ]);
        }

        return [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'code' => $item['code']
                ?? $item['permission_code']
                ?? null,
            'previous_effect' => $previousEffect,
            'effect' => $newEffect,
        ];
    }
}