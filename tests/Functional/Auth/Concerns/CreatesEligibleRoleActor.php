<?php

declare(strict_types=1);

namespace Tests\Functional\Auth\Concerns;

use App\Models\User;

trait CreatesEligibleRoleActor
{
    private function createEligibleRoleActor(): User
    {
        $personId = $this->db
            ->table('institutional.persons')
            ->insertGetId([
                'nombres' => 'ACTOR DE PRUEBA SEGURIDAD',
                'apellido_paterno' => 'SIGA',
                'estatus' => 'ACTIVO',
            ], 'id_persona');

        $actor = User::factory()->create([
            'id_persona' => $personId,
            'access_status' => 'HABILITADO',
        ]);

        // Recuperar los valores generados por PostgreSQL.
        $actor->refresh();

        if (
            $actor->access_status !== 'HABILITADO'
            || (string) $actor->id_persona !== (string) $personId
            || (int) $actor->access_version < 1
        ) {
            throw new \LogicException(
                'El actor funcional no cumple los requisitos de acceso.'
            );
        }

        return $actor;
    }

    private function currentRoleActorAccessVersion(): string
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new \LogicException(
                'La prueba requiere un actor autenticado.'
            );
        }

        return (string) $actor->access_version;
    }
}