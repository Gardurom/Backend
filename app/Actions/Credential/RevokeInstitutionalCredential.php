<?php

namespace App\Actions\Credential;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class RevokeInstitutionalCredential
{
    public function __construct(
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function execute(
        User $actor,
        string $credentialId,
        string $reason
    ): void {
        if (! $this->permissions->allows(
            $actor,
            'credenciales.revocar'
        )) {
            throw new AuthorizationException(
                'No autorizado para revocar credenciales.'
            );
        }

        $reason = trim($reason);

        if (! Str::isUuid($credentialId)) {
            throw new InvalidArgumentException(
                'Identificador de credencial invalido.'
            );
        }

        if ($reason === '' || strlen($reason) > 1000) {
            throw new InvalidArgumentException(
                'El motivo es obligatorio y no puede exceder 1000 bytes.'
            );
        }

        $actorId = $actor->getKey();

        if (
            ! is_numeric($actorId)
            || (int) $actorId < 1
        ) {
            throw new InvalidArgumentException(
                'Usuario responsable invalido.'
            );
        }

        DB::transaction(function () use (
            $credentialId,
            $actorId,
            $reason
        ): void {
            $credential = DB::table('institutional.credentials')
                ->where('id_credencial', $credentialId)
                ->lockForUpdate()
                ->first();

            if ($credential === null) {
                throw new RuntimeException(
                    'La credencial no existe.'
                );
            }

            if ($credential->estado !== 'VIGENTE') {
                throw new RuntimeException(
                    'La credencial no esta vigente.'
                );
            }

            $now = now();

            $updated = DB::table('institutional.credentials')
                ->where('id_credencial', $credentialId)
                ->where('estado', 'VIGENTE')
                ->update([
                    'estado' => 'REVOCADA',
                    'revocada_en' => $now,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new RuntimeException(
                    'No se pudo revocar la credencial.'
                );
            }

            DB::table('institutional.credential_events')
                ->insert([
                    'id_evento' => (string) Str::uuid(),
                    'id_credencial' => $credentialId,
                    'id_usuario_actor' => (int) $actorId,
                    'tipo_evento' => 'REVOCACION',
                    'motivo' => $reason,
                    'ocurrido_en' => $now,
                ]);
        }, 3);
    }
}