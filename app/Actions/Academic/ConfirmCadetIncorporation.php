<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Actions\Persona\RegisterPerson;
use App\Actions\Persona\UpdatePerson;
use App\Actions\User\CreateUser;
use App\Actions\User\ManageUserProfiles;
use App\Models\User;
use Illuminate\Database\Connection;

// SIGA_C1_CONFIRMACION_CADETES_MODULAR_V1
// Reutiliza acciones existentes dentro de una transaccion.
// No concede roles ni habilita cuentas.
final class ConfirmCadetIncorporation
{
    public function execute(
        Connection $db,
        User $actor,
        string $cohortId,
        array $prepared,
        array $resolved,
        RegisterPerson $registerPerson,
        RegisterAcademicIdentity $registerAcademic,
        CreateUser $createUser,
        ManageUserProfiles $manageProfiles,
        UpdatePerson $updatePerson
    ): array {
        if ($db->transactionLevel() < 1) {
            throw new \LogicException(
                'La confirmacion requiere una transaccion activa.'
            );
        }
        // SIGA_CADETE_CONFIRMACION_COMPLETA_V1
        // Comprobar todas las cuentas antes de crear
        // cualquier Persona o incorporacion.
        $accountsWithoutEmail = 0;

        foreach ($resolved as $item) {
            $existingUser = $item['user_id'] ?? null;
            $accountEmail = trim(
                (string) ($item['email'] ?? '')
            );

            if (
                $existingUser === null
                && (
                    $accountEmail === ''
                    || filter_var(
                        $accountEmail,
                        FILTER_VALIDATE_EMAIL
                    ) === false
                )
            ) {
                $accountsWithoutEmail++;
            }
        }

        if ($accountsWithoutEmail > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivo' =>
                    'Incorporacion integral bloqueada: '
                    . $accountsWithoutEmail
                    . ' registro(s) no tienen correo valido '
                    . 'ni Usuario institucional reutilizable.',
            ]);
        }

        $created = 0;
        $reused = 0;
        $confirmation = [];

        foreach ($resolved as $item) {
            $row = $item['row'];
            $personId = $item['id_persona'];

            if ($personId === null) {
                $person = $registerPerson->execute(
                    (int) $actor->getKey(),
                    [
                        'rfc' => $row['rfc'],
                        'nombres' => $row['nombres'],
                        'apellido_paterno' => $row['apellido_paterno'],
                        'apellido_materno' => $row['apellido_materno'],
                        'correo_institucional' =>
                            $row['correo_institucional'] ?: null,
                    ],
                    'MASIVO'
                );

                $personId = (string) $person->getKey();
                $created++;
            } else {
                $reused++;
            }

            $confirmation[] = [
                'id_persona' => $personId,
                'id_grupo' => $item['id_grupo'],
                'matricula_historica' => $row['matricula'] ?: null,
            ];
        }

        $incorporations = $registerAcademic
            ->confirmIncorporations(
                $actor,
                $cohortId,
                $confirmation
            );

        // El perfil identifica el contexto de trabajo.
        // No constituye un rol ni concede permisos.
        $cadetProfileId = $db->table('system.profiles as p')
            ->join(
                'system.profile_groups as g',
                'g.id',
                '=',
                'p.profile_group_id'
            )
            ->where('p.code', 'PERFIL_CADETE')
            ->where('g.code', 'GRUPO_ACADEMICO')
            ->value('p.id');

        if ($cadetProfileId === null) {
            throw new \RuntimeException(
                'No existe el perfil institucional de cadete.'
            );
        }

        $usersCreated = 0;
        $usersReused = 0;
        $profilesAssigned = 0;
        $personEmailsUpdated = 0;

        foreach ($resolved as $index => $item) {
            $userId = $item['user_id'];

            if ($item['actualizar_correo_persona']) {
                $personId = (string)
                    $confirmation[$index]['id_persona'];

                // Evitar sobrescribir cambios concurrentes.
                $currentEmail = $db->table(
                    'institutional.persons'
                )
                    ->where('id_persona', $personId)
                    ->lockForUpdate()
                    ->value('correo_institucional');

                if (
                    trim((string) $currentEmail) !== ''
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'archivo' => 'El correo institucional de una Persona cambio durante la importacion.',
                    ]);
                }

                // UpdatePerson guarda la actividad
                // dentro de la transaccion institucional.
                $updatePerson->execute(
                    (int) $actor->getKey(),
                    $personId,
                    [
                        'correo_institucional' =>
                            (string) $item['email'],
                    ]
                );

                $personEmailsUpdated++;
            }

            if ($userId === null) {
                // Contraseña aleatoria no entregada ni registrada
                // en respuesta, archivo, bitacora o consola.
                // El modelo User almacena su hash.
                $newUser = $createUser->execute(
                    (string) $confirmation[$index]['id_persona'],
                    (string) $item['email'],
                    bin2hex(random_bytes(32)),
                    (int) $actor->getKey()
                );

                if ($newUser->access_status !== 'PENDIENTE') {
                    throw new \RuntimeException(
                        'La cuenta nueva debe quedar PENDIENTE.'
                    );
                }

                $userId = (int) $newUser->getKey();
                $usersCreated++;
            } else {
                $userId = (int) $userId;
                $usersReused++;
            }

            if ($manageProfiles->assign(
                $userId,
                (int) $cadetProfileId,
                (int) $actor->getKey()
            )) {
                $profilesAssigned++;
            }
        }

        return [
            'data' => [
                'id_cohorte' => $cohortId,
                'filas_procesadas' => count($prepared),
                'personas_creadas' => $created,
                'personas_reutilizadas' => $reused,
                'incorporaciones' => $incorporations,
                'usuarios_creados' => $usersCreated,
                'usuarios_reutilizados' => $usersReused,
                'perfiles_asignados' => $profilesAssigned,
                'personas_correo_actualizado' => $personEmailsUpdated,
                'credenciales_entregadas' => false,
                'roles_asignados' => 0,
            ],
        ];
    }
}
