<?php

namespace Tests\Functional\Persona;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class PersonApiWithdrawalTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_withdraw_person(): void
    {
        $response = $this->postJson(
            '/api/personas/01900000-0000-7000-8000-000000000001/baja'
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_withdraw_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO BAJA PERSONA',
            'email' => 'persona.withdraw.forbidden@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
             VALUES (?)
             RETURNING id_persona',
            ['PERSONA PROTEGIDA BAJA']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-withdraw-forbidden-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/baja'
            );

        $response->assertForbidden();
    }

    public function test_authenticated_user_can_withdraw_existing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO BAJA PERSONA',
            'email' => 'persona.withdraw@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA PARA BAJA API']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-withdraw-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/baja'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id_persona',
                $person->id_persona
            )
            ->assertJsonPath(
                'estatus',
                'BAJA'
            );

        self::assertNotNull(
            $response->json('fecha_baja')
        );
    }

    public function test_authenticated_user_receives_not_found_when_withdrawing_missing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO BAJA PERSONA INEXISTENTE',
            'email' => 'persona.withdraw.missing@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-withdraw-missing-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/01900000-0000-7000-8000-000000000001/baja'
            );

        $response->assertNotFound();
    }

    public function test_authenticated_user_does_not_duplicate_withdrawal(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO BAJA REPETIDA PERSONA',
            'email' => 'persona.withdraw.repeated@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA BAJA REPETIDA API']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-withdraw-repeated-siga';

        $firstResponse = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/baja'
            );

        $firstResponse->assertOk();

        $firstDate = $firstResponse->json('fecha_baja');

        $secondResponse = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/baja'
            );

        $secondResponse
            ->assertOk()
            ->assertJsonPath('estatus', 'BAJA')
            ->assertJsonPath('fecha_baja', $firstDate);

        $count = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $person->id_persona)
            ->where('accion', 'BAJA')
            ->count();

        self::assertSame(1, $count);
    }

    public function test_authenticated_user_cannot_spoof_audit_user_on_withdrawal(): void
    {
        $authenticatedUser = User::factory()->create([
            'name' => 'USUARIO AUTENTICADO BAJA PERSONA',
            'email' => 'persona.withdraw.audit.authenticated@siga.test',
        ]);

        $this->assignRole($authenticatedUser, 'ROL_GESTOR_PERSONAS');

        $otherUser = User::factory()->create([
            'name' => 'USUARIO SUPLANTADO BAJA PERSONA',
            'email' => 'persona.withdraw.audit.other@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA AUDITORIA BAJA API']
        );

        $this->actingAs($authenticatedUser);

        $csrfToken = 'csrf-token-persona-withdraw-audit-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(
                '/api/personas/'.$person->id_persona.'/baja',
                [
                    'id_usuario' => $otherUser->id,
                ]
            );

        $response->assertOk();

        $activity = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $person->id_persona)
            ->where('accion', 'BAJA')
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
