<?php

namespace Tests\Functional\Persona;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class PersonApiShowTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_show_person(): void
    {
        $response = $this->getJson(
            '/api/personas/01900000-0000-7000-8000-000000000001'
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_show_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO PERSONA',
            'email' => 'persona.show.forbidden@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
             VALUES (?)
             RETURNING id_persona',
            ['PERSONA PROTEGIDA API']
        );

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/'.$person->id_persona
        );

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_can_show_existing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CONSULTA PERSONA',
            'email' => 'persona.show@siga.test',
        ]);

        $this->assignRole(
            $user,
            'ROL_CONSULTA_PERSONAS'
        );

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
             VALUES (?)
             RETURNING id_persona',
            ['PERSONA CONSULTADA API']
        );

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/'.$person->id_persona
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id_persona',
                $person->id_persona
            )
            ->assertJsonPath(
                'nombres',
                'PERSONA CONSULTADA API'
            );
    }

    public function test_authenticated_user_with_permission_receives_not_found_for_missing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CONSULTA PERSONA INEXISTENTE',
            'email' => 'persona.show.missing@siga.test',
        ]);

        $this->assignRole(
            $user,
            'ROL_CONSULTA_PERSONAS'
        );

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/01900000-0000-7000-8000-000000000001'
        );

        $response->assertNotFound();
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
