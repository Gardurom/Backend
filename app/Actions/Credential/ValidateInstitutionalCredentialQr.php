<?php

namespace App\Actions\Credential;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ValidateInstitutionalCredentialQr
{
    public function __construct(
        private readonly PermissionResolver $permissions
    ) {
    }

    /**
     * Devuelve solo el resultado de validez.
     * No expone datos de la Persona ni de la credencial.
     *
     * @return array{valida: bool}
     */
    public function execute(User $actor, string $qrUid): array
    {
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

        if (! $this->permissions->allows($actor, 'credenciales.validar')) {
            throw new RuntimeException(
                'El usuario no tiene permiso para validar credenciales.'
            );
        }

        if (! Str::isUuid($qrUid)) {
            return ['valida' => false];
        }

        $now = now();

        $valid = DB::table('institutional.credentials as c')
            ->join(
                'institutional.persons as p',
                'p.id_persona',
                '=',
                'c.id_persona'
            )
            ->where('c.uid_qr', $qrUid)
            ->where('c.estado', 'VIGENTE')
            ->whereNull('c.revocada_en')
            ->where('p.estatus', 'ACTIVO')
            ->where(function ($query) use ($now): void {
                $query
                    ->whereNull('c.vigente_desde')
                    ->orWhere('c.vigente_desde', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query
                    ->whereNull('c.vigente_hasta')
                    ->orWhere('c.vigente_hasta', '>', $now);
            })
            ->exists();

        return ['valida' => $valid];
    }
}