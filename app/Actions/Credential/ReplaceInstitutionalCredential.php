<?php

namespace App\Actions\Credential;

use App\Authorization\PermissionResolver;
use App\Models\InstitutionalCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ReplaceInstitutionalCredential
{
    public function __construct(
        private readonly PermissionResolver $permissions
    ) {
    }

    public function execute(
        User $actor,
        string $credentialId,
        string $reason
    ): InstitutionalCredential {
        $reason = trim($reason);

        if (! Str::isUuid($credentialId)) {
            throw new InvalidArgumentException(
                'El identificador de credencial es invalido.'
            );
        }

        if (
            $reason === ''
            || strlen($reason) > 1000
        ) {
            throw new InvalidArgumentException(
                'El motivo de reposicion debe tener entre 1 y 1000 bytes.'
            );
        }

        $actorId = $actor->getKey();

        if (
            $actorId === null
            || ! ctype_digit((string) $actorId)
            || (int) $actorId <= 0
        ) {
            throw new RuntimeException(
                'Se requiere un usuario institucional persistido.'
            );
        }

        if ($actor->access_status !== 'HABILITADO') {
            throw new RuntimeException(
                'El usuario actor no esta habilitado.'
            );
        }

        if (
            ! $this->permissions->allows($actor, 'credenciales.emitir')
            || ! $this->permissions->allows($actor, 'credenciales.revocar')
        ) {
            throw new RuntimeException(
                'Se requieren permisos de emision y revocacion.'
            );
        }

        return DB::transaction(function () use (
            $credentialId,
            $actorId,
            $reason
        ): InstitutionalCredential {
            /*
             * Consultar inicialmente la Persona asociada para adquirir
             * los bloqueos en el mismo orden que el servicio de emision.
             */
            $source = DB::table('institutional.credentials')
                ->where('id_credencial', $credentialId)
                ->first(['id_persona']);

            if ($source === null) {
                throw new RuntimeException(
                    'La credencial anterior no existe.'
                );
            }

            $person = DB::table('institutional.persons')
                ->where('id_persona', $source->id_persona)
                ->lockForUpdate()
                ->first(['id_persona', 'estatus']);

            if ($person === null || $person->estatus !== 'ACTIVO') {
                throw new RuntimeException(
                    'La Persona no esta activa o no existe.'
                );
            }

            $previous = DB::table('institutional.credentials')
                ->where('id_credencial', $credentialId)
                ->lockForUpdate()
                ->first([
                    'id_credencial',
                    'id_persona',
                    'categoria',
                    'estado',
                ]);

            if (
                $previous === null
                || $previous->id_persona !== $person->id_persona
                || $previous->estado !== 'VIGENTE'
            ) {
                throw new RuntimeException(
                    'La credencial anterior ya no es vigente.'
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
                    'No se pudo revocar la credencial anterior.'
                );
            }

            DB::table('institutional.credential_events')->insert([
                'id_evento' => (string) Str::uuid(),
                'id_credencial' => $credentialId,
                'id_usuario_actor' => (int) $actorId,
                'tipo_evento' => 'REVOCACION',
                'motivo' => $reason,
                'ocurrido_en' => $now,
            ]);

            $newId = (string) Str::uuid();
            $newQrUid = (string) Str::uuid();
            $newFolio = 'CRD-' . strtoupper(bin2hex(random_bytes(16)));

            DB::table('institutional.credentials')->insert([
                'id_credencial' => $newId,
                'id_persona' => $person->id_persona,
                'folio' => $newFolio,
                'uid_qr' => $newQrUid,
                'categoria' => $previous->categoria,
                'estado' => 'VIGENTE',
                'vigente_desde' => $now,
                'vigente_hasta' => null,
                'revocada_en' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('institutional.credential_events')->insert([
                'id_evento' => (string) Str::uuid(),
                'id_credencial' => $newId,
                'id_usuario_actor' => (int) $actorId,
                'tipo_evento' => 'REPOSICION',
                'motivo' => $reason,
                'ocurrido_en' => $now,
            ]);

            $replacement = InstitutionalCredential::query()
                ->whereKey($newId)
                ->first();

            if ($replacement === null) {
                throw new RuntimeException(
                    'No se pudo recuperar la credencial de reposicion.'
                );
            }

            return $replacement;
        }, 3);
    }
}