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
}
