<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\HttpFunctionalTestCase;

class SensitiveOperationReauthenticationTest extends HttpFunctionalTestCase
{
    public function test_authenticated_user_can_reauthenticate_with_current_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Reautenticacion SIGA',
            'email' => 'reauth@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-reauthentication-siga';

        $this->actingAs($user);

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/reauthenticate', [
                'password' => 'ClaveSegura123!',
            ]);

        $response->assertNoContent();

        $response->assertSessionHas(
            'siga_reauthenticated_at'
        );

        self::assertIsInt(
            session('siga_reauthenticated_at')
        );
    }

    public function test_authenticated_user_cannot_reauthenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Reautenticacion Invalida SIGA',
            'email' => 'reauth.invalid@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-invalid-reauthentication-siga';

        $this->actingAs($user);

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/reauthenticate', [
                'password' => 'ClaveIncorrecta456!',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $response->assertSessionMissing(
            'siga_reauthenticated_at'
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_reauthentication_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Rate Limit Reautenticacion SIGA',
            'email' => 'reauth.rate.limit@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-rate-limit-reauthentication-siga';

        $this->actingAs($user);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '203.0.113.30',
                ])
                ->withSession([
                    '_token' => $csrfToken,
                    'siga_authenticated_at' => now()->timestamp,
                ])
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->postJson('/reauthenticate', [
                    'password' => 'ClaveIncorrecta456!',
                ]);

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');

            $response->assertSessionMissing(
                'siga_reauthenticated_at'
            );

            $this->assertAuthenticatedAs($user);
        }

        $blockedResponse = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.30',
            ])
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/reauthenticate', [
                'password' => 'ClaveIncorrecta456!',
            ]);

        $blockedResponse
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $blockedResponse->assertSessionMissing(
            'siga_reauthenticated_at'
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_reauthentication_clears_previous_failed_attempts(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Reset Rate Limit Reautenticacion SIGA',
            'email' => 'reauth.rate.reset@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-rate-limit-reset-reauthentication-siga';

        $this->actingAs($user);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '203.0.113.40',
                ])
                ->withSession([
                    '_token' => $csrfToken,
                    'siga_authenticated_at' => now()->timestamp,
                ])
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->postJson('/reauthenticate', [
                    'password' => 'ClaveIncorrecta456!',
                ]);

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $successfulResponse = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.40',
            ])
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/reauthenticate', [
                'password' => 'ClaveSegura123!',
            ]);

        $successfulResponse->assertNoContent();

        $successfulResponse->assertSessionHas(
            'siga_reauthenticated_at'
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '203.0.113.40',
                ])
                ->withSession([
                    '_token' => $csrfToken,
                    'siga_authenticated_at' => now()->timestamp,
                ])
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->postJson('/reauthenticate', [
                    'password' => 'ClaveIncorrecta456!',
                ]);

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $blockedResponse = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.40',
            ])
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/reauthenticate', [
                'password' => 'ClaveIncorrecta456!',
            ]);

        $blockedResponse
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertAuthenticatedAs($user);
    }

    public function test_sensitive_session_revocation_requires_recent_reauthentication(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Operacion Sensible SIGA',
            'email' => 'sensitive.operation@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $otherSessionId = str_repeat('9', 40);
        $now = now()->timestamp;
        $csrfToken = 'csrf-sensitive-operation-siga';

        $this->db->table('system.sessions')->insert([
            'id' => $otherSessionId,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.90',
            'user_agent' => 'SIGA Other Sensitive Browser',
            'payload' => 'test-payload-sensitive-operation',
            'last_activity' => $now,
        ]);

        $this->actingAs($user);

        $response = $this
            ->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => $now,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->deleteJson('/api/sessions/others');

        $response
            ->assertStatus(423)
            ->assertJson([
                'message' => 'Reauthentication required.',
            ]);

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $otherSessionId)
                ->count(),
            'La operación sensible no debe ejecutarse sin reautenticación reciente.'
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_reauthentication_timeout_allows_one_second_before_limit_and_blocks_exact_limit(): void
    {
        $fixedNow = Carbon::parse('2026-10-09 12:00:00 UTC');
        $originalTimeout = config('auth.reauthentication_timeout');

        Carbon::setTestNow($fixedNow);
        config()->set('auth.reauthentication_timeout', 900);

        try {
            $user = User::factory()->create([
                'name' => 'Usuario Limite Reautenticacion SIGA',
                'email' => 'reauth.boundary@siga.test',
                'password' => Hash::make('ClaveSegura123!'),
            ]);

            $this->actingAs($user);

            $now = $fixedNow->timestamp;
            $csrfToken = 'csrf-reauthentication-boundary-siga';

            $beforeBoundarySessionId = str_repeat('7', 40);

            $this->db->table('system.sessions')->insert([
                'id' => $beforeBoundarySessionId,
                'user_id' => $user->id,
                'ip_address' => '203.0.113.120',
                'user_agent' => 'SIGA Reauthentication Before Boundary',
                'payload' => 'test-payload-reauth-before-boundary',
                'last_activity' => $now,
            ]);

            $allowedResponse = $this
                ->withCredentials()
                ->withHeader('Origin', 'http://localhost')
                ->withSession([
                    '_token' => $csrfToken,
                    'siga_authenticated_at' => $now,
                    'siga_reauthenticated_at' => $now - 899,
                ])
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->deleteJson('/api/sessions/others');

            $allowedResponse->assertNoContent();

            self::assertSame(
                0,
                $this->db
                    ->table('system.sessions')
                    ->where('id', $beforeBoundarySessionId)
                    ->count(),
                'Una reautenticación de 14:59 debe permitir la operación sensible.'
            );

            $exactBoundarySessionId = str_repeat('8', 40);

            $this->db->table('system.sessions')->insert([
                'id' => $exactBoundarySessionId,
                'user_id' => $user->id,
                'ip_address' => '203.0.113.121',
                'user_agent' => 'SIGA Reauthentication Exact Boundary',
                'payload' => 'test-payload-reauth-exact-boundary',
                'last_activity' => $now,
            ]);

            $blockedResponse = $this
                ->withCredentials()
                ->withHeader('Origin', 'http://localhost')
                ->withSession([
                    '_token' => $csrfToken,
                    'siga_authenticated_at' => $now,
                    'siga_reauthenticated_at' => $now - 900,
                ])
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->deleteJson('/api/sessions/others');

            $blockedResponse
                ->assertStatus(423)
                ->assertJson([
                    'message' => 'Reauthentication required.',
                ]);

            self::assertSame(
                1,
                $this->db
                    ->table('system.sessions')
                    ->where('id', $exactBoundarySessionId)
                    ->count(),
                'Una reautenticación de exactamente 15:00 debe considerarse vencida.'
            );

            $this->assertAuthenticatedAs($user);
        } finally {
            Carbon::setTestNow();

            config()->set(
                'auth.reauthentication_timeout',
                $originalTimeout
            );
        }
    }

    public function test_individual_session_revocation_requires_recent_reauthentication(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Revocacion Individual Sensible SIGA',
            'email' => 'sensitive.individual@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $targetSessionId = str_repeat('a', 40);
        $now = now()->timestamp;
        $csrfToken = 'csrf-sensitive-individual-session-siga';

        $this->db->table('system.sessions')->insert([
            'id' => $targetSessionId,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.130',
            'user_agent' => 'SIGA Sensitive Individual Browser',
            'payload' => 'test-payload-sensitive-individual',
            'last_activity' => $now,
        ]);

        $publicSessionId = hash_hmac(
            'sha256',
            $targetSessionId,
            (string) config('app.key')
        );

        $this->actingAs($user);

        $response = $this
            ->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => $now,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->deleteJson('/api/sessions/'.$publicSessionId);

        $response
            ->assertStatus(423)
            ->assertJson([
                'message' => 'Reauthentication required.',
            ]);

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $targetSessionId)
                ->count(),
            'La sesión objetivo no debe revocarse sin reautenticación reciente.'
        );

        $this->assertAuthenticatedAs($user);
    }
}
