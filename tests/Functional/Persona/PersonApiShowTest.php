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

    public function test_authenticated_user_can_show_existing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CONSULTA PERSONA',
            'email' => 'persona.show@siga.test',
        ]);

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

    public function test_authenticated_user_receives_not_found_for_missing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CONSULTA PERSONA INEXISTENTE',
            'email' => 'persona.show.missing@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/01900000-0000-7000-8000-000000000001'
        );

        $response->assertNotFound();
    }
}
