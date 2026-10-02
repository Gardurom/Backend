<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class LogoutTest extends HttpFunctionalTestCase
{
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Logout SIGA',
            'email' => 'logout.functional@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-logout-functional-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/logout');

        $response->assertNoContent();

        $this->assertGuest();
    }
}
