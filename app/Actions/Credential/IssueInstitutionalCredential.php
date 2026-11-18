<?php

namespace App\Actions\Credential;

use App\Authorization\PermissionResolver;
use App\Models\InstitutionalCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class IssueInstitutionalCredential
{
    public function __construct(
        private readonly PermissionResolver $permissions
    ) {
    }

    public function execute(
        User $actor,
        string $personId,
        string $category
    ): InstitutionalCredential {
        if (
            ! Str::isUuid($personId)
            || ! preg_match('/^[A-Z][A-Z0-9_]{1,39}$/D', $category)
        ) {
            throw new InvalidArgumentException(
                'Identificador de Persona o categoria invalida.'
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

        if (! $this->permissions->allows($actor, 'credenciales.emitir')) {
            throw new RuntimeException(
                'El usuario no tiene permiso para emitir credenciales.'
            );
        }

        return DB::transaction(function () use (
            $actorId,
            $personId,
            $category
        ): InstitutionalCredential {
            $person = DB::table('institutional.persons')
                ->where('id_persona', $personId)
                ->lockForUpdate()
                ->first(['id_persona', 'estatus']);

            if ($person === null || $person->estatus !== 'ACTIVO') {
                throw new RuntimeException(
                    'La Persona no existe o no es elegible.'
                );
            }

            $alreadyActive = DB::table('institutional.credentials')
                ->where('id_persona', $personId)
                ->where('estado', 'VIGENTE')
                ->exists();

            if ($alreadyActive) {
                throw new RuntimeException(
                    'La Persona ya tiene una credencial vigente.'
                );
            }

            $credentialId = (string) Str::uuid();
            $qrUid = (string) Str::uuid();
            $folio = 'CRD-' . strtoupper(bin2hex(random_bytes(16)));
            $now = now();

            DB::table('institutional.credentials')->insert([
                'id_credencial' => $credentialId,
                'id_persona' => $personId,
                'folio' => $folio,
                'uid_qr' => $qrUid,
                'categoria' => $category,
                'estado' => 'VIGENTE',
                'vigente_desde' => $now,
                'vigente_hasta' => null,
                'revocada_en' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('institutional.credential_events')->insert([
                'id_evento' => (string) Str::uuid(),
                'id_credencial' => $credentialId,
                'id_usuario_actor' => (int) $actorId,
                'tipo_evento' => 'EMISION',
                'motivo' => null,
                'ocurrido_en' => $now,
            ]);

            $credential = InstitutionalCredential::query()
                ->whereKey($credentialId)
                ->first();

            if ($credential === null) {
                throw new RuntimeException(
                    'No se pudo recuperar la credencial emitida.'
                );
            }

            return $credential;
        }, 3);
    }
}