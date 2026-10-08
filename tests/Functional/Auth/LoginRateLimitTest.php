<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\HttpFunctionalTestCase;

class LoginRateLimitTest extends HttpFunctionalTestCase
{
    public function test_login_is_rate_limited_after_five_failed_attempts_for_same_email_and_ip(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Rate Limit SIGA',
            'email' => 'login.rate.limit@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-token-rate-limit-siga';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '203.0.113.10',
                ])
                ->withSession([
                    '_token' => $csrfToken,
                ])
                ->withHeader(
                    'X-CSRF-TOKEN',
                    $csrfToken
                )
                ->postJson('/login', [
                    'email' => ' LOGIN.RATE.LIMIT@SIGA.TEST ',
                    'password' => 'ClaveIncorrecta456!',
                ]);

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');

            $this->assertGuest();
        }

        $blockedResponse = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
            ])
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader(
                'X-CSRF-TOKEN',
                $csrfToken
            )
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'ClaveIncorrecta456!',
            ]);

        $blockedResponse->assertStatus(429);

        $this->assertGuest();
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Rate Limit Reset SIGA',
            'email' => 'login.rate.reset@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-token-rate-limit-reset-siga';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '203.0.113.20',
                ])
                ->withSession([
                    '_token' => $csrfToken,
                ])
                ->withHeader(
                    'X-CSRF-TOKEN',
                    $csrfToken
                )
                ->postJson('/login', [
                    'email' => $user->email,
                    'password' => 'ClaveIncorrecta456!',
                ]);

            $response->assertUnprocessable();
        }

        $loginResponse = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.20',
            ])
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader(
                'X-CSRF-TOKEN',
                $csrfToken
            )
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'ClaveSegura123!',
            ]);

        $loginResponse->assertNoContent();

        $this->assertAuthenticatedAs($user);

        Auth::logout();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '203.0.113.20',
                ])
                ->withSession([
                    '_token' => $csrfToken,
                ])
                ->withHeader(
                    'X-CSRF-TOKEN',
                    $csrfToken
                )
                ->postJson('/login', [
                    'email' => $user->email,
                    'password' => 'ClaveIncorrecta456!',
                ]);

            $response->assertUnprocessable();
        }

        $blockedResponse = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.20',
            ])
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader(
                'X-CSRF-TOKEN',
                $csrfToken
            )
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'ClaveIncorrecta456!',
            ]);

        $blockedResponse->assertStatus(429);
    }
}
