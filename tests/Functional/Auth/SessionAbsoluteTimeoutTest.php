<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\HttpFunctionalTestCase;

class SessionAbsoluteTimeoutTest extends HttpFunctionalTestCase
{
    public function test_authenticated_session_expires_after_eight_hours(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Timeout Absoluto SIGA',
            'email' => 'absolute.timeout@siga.test',
        ]);

        $this->actingAs($user);

        $this->withSession([
            'siga_authenticated_at' => now()
                ->subHours(8)
                ->subSecond()
                ->timestamp,
        ]);

        $response = $this
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_login_records_absolute_session_start_time(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Inicio Sesion Absoluta SIGA',
            'email' => 'absolute.login@siga.test',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $csrfToken = 'csrf-token-absolute-timeout-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'ClaveSegura123!',
            ]);

        $response
            ->assertNoContent()
            ->assertSessionHas(
                'siga_authenticated_at',
                fn ($value): bool => is_int($value)
            );
    }

    public function test_authenticated_session_remains_valid_before_eight_hours(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Sesion Vigente SIGA',
            'email' => 'absolute.valid@siga.test',
        ]);

        $this->actingAs($user);

        $this->withSession([
            'siga_authenticated_at' => now()
                ->subHours(8)
                ->addSecond()
                ->timestamp,
        ]);

        $response = $this
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_legacy_authenticated_session_initializes_absolute_start_time(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Sesion Legada SIGA',
            'email' => 'absolute.legacy@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this
            ->withSession([
                'legacy_session' => true,
            ])
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/user');

        $response
            ->assertOk()
            ->assertSessionHas(
                'siga_authenticated_at',
                fn ($value): bool => is_int($value)
            );
    }
}
