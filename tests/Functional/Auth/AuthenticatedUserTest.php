<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class AuthenticatedUserTest extends HttpFunctionalTestCase
{
    public function test_authenticated_user_can_access_user_endpoint(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Funcional SIGA',
            'email' => 'auth.functional@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', $user->name)
            ->assertJsonPath('email', $user->email);
    }

    public function test_authenticated_user_endpoint_includes_linked_person(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA AUTENTICADA SIGA',
                ],
                'id_persona'
            );

        $user = User::factory()->create([
            'name' => 'USUARIO CON PERSONA SIGA',
            'email' => 'auth.person@siga.test',
            'id_persona' => $personId,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('id_persona', $personId)
            ->assertJsonPath('person.id_persona', $personId)
            ->assertJsonPath(
                'person.nombres',
                'PERSONA AUTENTICADA SIGA'
            );

        $response
            ->assertJsonMissingPath('person.curp')
            ->assertJsonMissingPath('person.rfc')
            ->assertJsonMissingPath('person.correo_institucional')
            ->assertJsonMissingPath('person.correo_personal');
    }
}
