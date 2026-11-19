<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

// SIGA_C1_CONCILIACION_ACADEMICA_MODULAR_V1
// Resuelve identidades dentro de la transaccion del coordinador.
// No crea ni actualiza registros institucionales.
final class ResolveCadetIncorporationIdentities
{
    public function execute(
        Connection $db,
        array $prepared,
        Collection $groups,
        callable $normalize,
        bool $previewOnly,
        User $actor
    ): array {
        $resolved = [];
        $resolvedEmails = [];

        // Resolver TODAS las identidades antes de crear alguna.
        foreach ($prepared as $row) {
            $group = $groups->get($row['grupo']);

            if (
                $group === null
                || $group->especializacion !== null
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "Grupo no disponible en fila {$row['fila_excel']}.",
                ]);
            }

            $person = $db->table(
                'institutional.persons'
            )
                ->where('rfc', $row['rfc'])
                ->first([
                    'id_persona',
                    'estatus',
                    'nombres',
                    'apellido_paterno',
                    'apellido_materno',
                    'correo_institucional',
                ]);

            if ($person !== null) {
                if (
                    $person->estatus !== 'ACTIVO'
                    || $normalize($person->nombres)
                        !== $row['nombres']
                    || $normalize($person->apellido_paterno)
                        !== ($row['apellido_paterno'] ?? '')
                    || $normalize($person->apellido_materno)
                        !== ($row['apellido_materno'] ?? '')
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'archivo' => "Identidad institucional contradictoria en fila {$row['fila_excel']}.",
                    ]);
                }
            } else {
                $sameName = $db->table(
                    'institutional.persons'
                )
                    ->whereRaw(
                        'UPPER(BTRIM(nombres)) = ?',
                        [$row['nombres']]
                    )
                    ->where(function ($q) use ($row) {
                        if ($row['apellido_paterno'] === null) {
                            $q->whereNull('apellido_paterno');
                        } else {
                            $q->whereRaw(
                                'UPPER(BTRIM(apellido_paterno)) = ?',
                                [$row['apellido_paterno']]
                            );
                        }
                    })
                    ->where(function ($q) use ($row) {
                        if ($row['apellido_materno'] === null) {
                            $q->whereNull('apellido_materno');
                        } else {
                            $q->whereRaw(
                                'UPPER(BTRIM(apellido_materno)) = ?',
                                [$row['apellido_materno']]
                            );
                        }
                    })
                    ->exists();

                if ($sameName) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'archivo' => "Posible Persona preexistente en fila {$row['fila_excel']}.",
                    ]);
                }
            }

            // La cuenta se identifica por Persona, nunca por
            // semejanza de nombres o por coincidencia de correo.
            $existingUser = $person === null
                ? null
                : $db->table('system.users')
                    ->where('id_persona', $person->id_persona)
                    ->first(['id', 'email']);

            $excelEmail = $row['correo_institucional'];

            $personEmail = mb_strtolower(trim(
                (string) ($person?->correo_institucional ?? '')
            ));

            $userEmail = mb_strtolower(trim(
                (string) ($existingUser?->email ?? '')
            ));

            if (
                ($excelEmail !== ''
                    && $personEmail !== ''
                    && $excelEmail !== $personEmail)
                || ($excelEmail !== ''
                    && $userEmail !== ''
                    && $excelEmail !== $userEmail)
                || ($personEmail !== ''
                    && $userEmail !== ''
                    && $personEmail !== $userEmail)
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "Correos institucionales contradictorios en fila {$row['fila_excel']}.",
                ]);
            }

            $accountEmail = $userEmail !== ''
                ? $userEmail
                : ($personEmail !== '' ? $personEmail : $excelEmail);

            if (
                $accountEmail !== ''
                && (
                    strlen($accountEmail) > 255
                    || filter_var(
                        $accountEmail,
                        FILTER_VALIDATE_EMAIL
                    ) === false
                )
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "Correo de cuenta invalido en fila {$row['fila_excel']}.",
                ]);
            }

            if (! $previewOnly && $accountEmail === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'archivo' => "Falta correo institucional para crear Usuario en fila {$row['fila_excel']}.",
                ]);
            }

            if ($accountEmail !== '') {
                if (isset($resolvedEmails[$accountEmail])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'archivo' => 'El mismo correo pertenece a varias filas.',
                    ]);
                }

                $resolvedEmails[$accountEmail] = true;

                $personConflict = $db->table(
                    'institutional.persons'
                )->whereRaw(
                    'LOWER(BTRIM(correo_institucional)) = ?',
                    [$accountEmail]
                );

                if ($person !== null) {
                    $personConflict->where(
                        'id_persona',
                        '<>',
                        $person->id_persona
                    );
                }

                if ($personConflict->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'archivo' => 'Correo asociado a otra Persona institucional.',
                    ]);
                }

                $userConflict = $db->table(
                    'system.users'
                )->whereRaw(
                    'LOWER(BTRIM(email)) = ?',
                    [$accountEmail]
                );

                if ($existingUser !== null) {
                    $userConflict->where(
                        'id',
                        '<>',
                        $existingUser->id
                    );
                }

                if ($userConflict->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'archivo' => 'Correo asociado a otra cuenta de Usuario.',
                    ]);
                }
            }

            // SIGA_CADETE_CORREO_PERSONA_AUDITADO_V1
            // Solo completar un correo institucional ausente.
            // Nunca sobrescribir un correo ya registrado.
            $updatePersonEmail =
                $person !== null
                && $personEmail === ''
                && $excelEmail !== '';

            if ($updatePersonEmail && ! $previewOnly) {
                abort_unless(
                    app(\App\Authorization\PermissionResolver::class)
                        ->allows(
                            $actor,
                            'personas.actualizar'
                        ),
                    403
                );
            }

            $resolved[] = [
                'row' => $row,
                'id_persona' => $person?->id_persona,
                'id_grupo' => (string) $group->id_grupo,
                'email' => $accountEmail,
                'user_id' => $existingUser?->id,
                'actualizar_correo_persona' => $updatePersonEmail,
            ];
        }

        return $resolved;
    }
}
