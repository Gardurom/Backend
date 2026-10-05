<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\HttpFunctionalTestCase;

class LoginTest extends HttpFunctionalTestCase
{
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Login SIGA',
            'email' => 'login.functional@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-token-functional-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'ClaveSegura123!',
            ]);

        $response->assertNoContent();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Login Invalido SIGA',
            'email' => 'login.invalid@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-token-invalid-functional-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'ClaveIncorrecta456!',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_login_with_normalized_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Login Normalizado SIGA',
            'email' => 'login.normalizado@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-token-normalized-login-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/login', [
                'email' => ' LOGIN.NORMALIZADO@SIGA.TEST ',
                'password' => 'ClaveSegura123!',
            ]);

        $response->assertNoContent();

        $this->assertAuthenticatedAs($user);
    }
}
