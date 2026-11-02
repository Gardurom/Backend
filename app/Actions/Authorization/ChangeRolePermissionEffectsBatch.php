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
        $enabled = config('database.siga_permissions_enabled', false);

        if ($enabled) {
            $settings = config('database.connections.siga_permissions', []);

            if (
                !is_array($settings)
                || ($settings['driver'] ?? null) !== 'pgsql'
                || ($settings['database'] ?? null) !== 'siga'
                || ($settings['username'] ?? null) !== 'siga_permission_executor'
                || !is_string($settings['password'] ?? null)
                || $settings['password'] === ''
            ) {
                throw new \RuntimeException(
                    'La conexion administrativa de permisos no esta configurada de forma segura.'
                );
            }
        }

        $connection = $enabled
            ? 'siga_permissions'
            : config('database.default');

        $result = DB::connection($connection)->selectOne(
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
