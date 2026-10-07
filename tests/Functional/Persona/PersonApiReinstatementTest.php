<?php

namespace Tests\Functional\Persona;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class PersonApiReinstatementTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_reinstate_person(): void
    {
        $response = $this->postJson(
            '/api/personas/01900000-0000-7000-8000-000000000001/reingreso'
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_reinstate_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO REINGRESO PERSONA',
            'email' => 'persona.reinstate.forbidden@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (
                nombres,
                estatus,
                fecha_baja
            )
            VALUES (?, ?, CURRENT_TIMESTAMP)
            RETURNING id_persona',
            [
                'PERSONA PROTEGIDA REINGRESO',
                'BAJA',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-reinstate-forbidden-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/reingreso'
            );

        $response->assertForbidden();
    }

    public function test_authenticated_user_can_reinstate_withdrawn_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO REINGRESO PERSONA',
            'email' => 'persona.reinstate@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (
            nombres,
            estatus,
            fecha_baja
        )
        VALUES (?, ?, CURRENT_TIMESTAMP)
        RETURNING id_persona',
            [
                'PERSONA PARA REINGRESO API',
                'BAJA',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-reinstate-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/reingreso'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id_persona',
                $person->id_persona
            )
            ->assertJsonPath(
                'estatus',
                'ACTIVO'
            )
            ->assertJsonPath(
                'fecha_baja',
                null
            );
    }

    public function test_authenticated_user_receives_not_found_when_reinstating_missing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO REINGRESO PERSONA INEXISTENTE',
            'email' => 'persona.reinstate.missing@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-reinstate-missing-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/01900000-0000-7000-8000-000000000001/reingreso'
            );

        $response->assertNotFound();
    }

    public function test_authenticated_user_does_not_duplicate_reinstatement(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO REINGRESO REPETIDO PERSONA',
            'email' => 'persona.reinstate.repeated@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (
            nombres,
            estatus,
            fecha_baja
        )
        VALUES (?, ?, CURRENT_TIMESTAMP)
        RETURNING id_persona',
            [
                'PERSONA REINGRESO REPETIDO API',
                'BAJA',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-reinstate-repeated-siga';

        $firstResponse = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/reingreso'
            );

        $firstResponse
            ->assertOk()
            ->assertJsonPath('estatus', 'ACTIVO')
            ->assertJsonPath('fecha_baja', null);

        $secondResponse = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/reingreso'
            );

        $secondResponse
            ->assertOk()
            ->assertJsonPath('estatus', 'ACTIVO')
            ->assertJsonPath('fecha_baja', null);

        $count = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $person->id_persona)
            ->where('accion', 'REINGRESO')
            ->count();

        self::assertSame(1, $count);
    }

    public function test_authenticated_user_cannot_spoof_audit_user_on_reinstatement(): void
    {
        $authenticatedUser = User::factory()->create([
            'name' => 'USUARIO AUTENTICADO REINGRESO PERSONA',
            'email' => 'persona.reinstate.audit.authenticated@siga.test',
        ]);

        $this->assignRole($authenticatedUser, 'ROL_GESTOR_PERSONAS');

        $otherUser = User::factory()->create([
            'name' => 'USUARIO SUPLANTADO REINGRESO PERSONA',
            'email' => 'persona.reinstate.audit.other@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (
            nombres,
            estatus,
            fecha_baja
        )
        VALUES (?, ?, CURRENT_TIMESTAMP)
        RETURNING id_persona',
            [
                'PERSONA AUDITORIA REINGRESO API',
                'BAJA',
            ]
        );

        $this->actingAs($authenticatedUser);

        $csrfToken = 'csrf-token-persona-reinstate-audit-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/reingreso',
                [
                    'id_usuario' => $otherUser->id,
                ]
            );

        $response->assertOk();

        $activity = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $person->id_persona)
            ->where('accion', 'REINGRESO')
            ->first();

        self::assertNotNull($activity);
        self::assertSame(
            $authenticatedUser->id,
            $activity->id_usuario
        );
        self::assertNotSame(
            $otherUser->id,
            $activity->id_usuario
        );
    }

    private function assignRole(
        User $user,
        string $roleCode
    ): void {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $roleCode)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$roleCode}."
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
