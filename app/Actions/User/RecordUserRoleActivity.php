<?php

declare(strict_types=1);

namespace App\Actions\User;

use Illuminate\Support\Facades\DB;

final class RecordUserRoleActivity
{
    public function record(
        ?int $actorId,
        int $userId,
        int $roleId,
        string $roleCode,
        bool $assigned
    ): void {
        $role = [
            'role_id' => $roleId,
            'role_code' => $roleCode,
        ];

        $empty = [
            'role_id' => null,
            'role_code' => null,
        ];

        $previous = $assigned ? $empty : $role;
        $current = $assigned ? $role : $empty;

        // Sin actor explicito, la cuenta afectada es referencia tecnica.
        if ($actorId === null) {
            $current['origen'] = 'REFERENCIA_TECNICA';
        }

        DB::table('system.activities')->insert([
            'id_usuario' => $actorId ?? $userId,
            'entidad' => 'USUARIO_ROL',
            'id_entidad' => (string) $userId,
            'accion' => 'ACTUALIZACION',
            'datos_anteriores' => json_encode(
                $previous,
                JSON_THROW_ON_ERROR
            ),
            'datos_nuevos' => json_encode(
                $current,
                JSON_THROW_ON_ERROR
            ),
            'campos_modificados' => json_encode(
                ['role_id', 'role_code'],
                JSON_THROW_ON_ERROR
            ),
        ]);
    }
}