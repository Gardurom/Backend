<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Functional\HttpFunctionalTestCase;

class SessionManagementTest extends HttpFunctionalTestCase
{
    public function test_authenticated_user_can_list_only_their_sessions_without_exposing_raw_session_ids(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Sesiones SIGA',
            'email' => 'sesiones@siga.test',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Otro Usuario Sesiones SIGA',
            'email' => 'otras.sesiones@siga.test',
        ]);

        $this->actingAs($user);

        $currentSessionId = str_repeat('c', 40);
        $otherOwnSessionId = str_repeat('a', 40);
        $foreignSessionId = str_repeat('b', 40);

        $now = now()->timestamp;

        $session = $this->app['session']->driver();

        $session->setId($currentSessionId);
        $session->start();

        $session->put([
            'session_management_test' => true,
            'siga_authenticated_at' => $now,
        ]);

        $session->save();

        $updated = $this->db
            ->table('system.sessions')
            ->where('id', $currentSessionId)
            ->update([
                'user_id' => $user->id,
                'ip_address' => '203.0.113.10',
                'user_agent' => 'SIGA Current Browser',
                'last_activity' => $now,
            ]);

        self::assertSame(
            1,
            $updated,
            'La sesión actual de prueba debe existir en system.sessions.'
        );

        $this->db->table('system.sessions')->insert([
            [
                'id' => $otherOwnSessionId,
                'user_id' => $user->id,
                'ip_address' => '203.0.113.20',
                'user_agent' => 'SIGA Other Browser',
                'payload' => 'test-payload-other',
                'last_activity' => $now - 60,
            ],
            [
                'id' => $foreignSessionId,
                'user_id' => $otherUser->id,
                'ip_address' => '198.51.100.30',
                'user_agent' => 'Foreign Browser',
                'payload' => 'test-payload-foreign',
                'last_activity' => $now - 120,
            ],
        ]);

        $response = $this
            ->withCredentials()
            ->withCookie(
                (string) config('session.cookie'),
                $currentSessionId
            )
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/sessions');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'ip_address' => '203.0.113.10',
                'user_agent' => 'SIGA Current Browser',
                'last_activity' => $now,
                'current' => true,
            ])
            ->assertJsonFragment([
                'ip_address' => '203.0.113.20',
                'user_agent' => 'SIGA Other Browser',
                'last_activity' => $now - 60,
                'current' => false,
            ])
            ->assertJsonMissing([
                'ip_address' => '198.51.100.30',
            ])
            ->assertJsonMissing([
                'id' => $currentSessionId,
            ])
            ->assertJsonMissing([
                'id' => $otherOwnSessionId,
            ])
            ->assertJsonMissing([
                'id' => $foreignSessionId,
            ]);

        $sessions = $response->json('data');

        self::assertIsArray($sessions);

        foreach ($sessions as $sessionData) {
            self::assertIsArray($sessionData);
            self::assertArrayHasKey('id', $sessionData);
            self::assertIsString($sessionData['id']);

            self::assertMatchesRegularExpression(
                '/^[a-f0-9]{64}$/',
                $sessionData['id'],
                'El identificador público de sesión debe ser opaco y no revelar el ID real.'
            );
        }
    }

    public function test_authenticated_user_can_revoke_one_of_their_other_sessions_using_public_id(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Revocacion SIGA',
            'email' => 'revocacion@siga.test',
        ]);

        $this->actingAs($user);

        $currentSessionId = str_repeat('c', 40);
        $otherSessionId = str_repeat('d', 40);

        $now = now()->timestamp;
        $csrfToken = 'csrf-session-revocation-siga';

        $session = $this->app['session']->driver();

        $session->setId($currentSessionId);
        $session->start();

        $session->put([
            '_token' => $csrfToken,
            'siga_authenticated_at' => $now,
        ]);

        $session->save();

        $updated = $this->db
            ->table('system.sessions')
            ->where('id', $currentSessionId)
            ->update([
                'user_id' => $user->id,
                'ip_address' => '203.0.113.40',
                'user_agent' => 'SIGA Current Browser',
                'last_activity' => $now,
            ]);

        self::assertSame(
            1,
            $updated,
            'La sesión actual debe existir antes de revocar otra sesión.'
        );

        $this->db->table('system.sessions')->insert([
            'id' => $otherSessionId,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.41',
            'user_agent' => 'SIGA Revocable Browser',
            'payload' => 'test-payload-revocable',
            'last_activity' => $now - 60,
        ]);

        $publicSessionId = hash_hmac(
            'sha256',
            $otherSessionId,
            (string) config('app.key')
        );

        $response = $this
            ->withCredentials()
            ->withCookie(
                (string) config('session.cookie'),
                $currentSessionId
            )
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->deleteJson(
                '/api/sessions/'.$publicSessionId
            );

        $response->assertNoContent();

        self::assertSame(
            0,
            $this->db
                ->table('system.sessions')
                ->where('id', $otherSessionId)
                ->count(),
            'La sesión seleccionada debe quedar revocada.'
        );

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $currentSessionId)
                ->count(),
            'La sesión actual debe permanecer activa.'
        );
    }

    public function test_authenticated_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Propietario SIGA',
            'email' => 'propietario.sesion@siga.test',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Usuario Ajeno SIGA',
            'email' => 'ajeno.sesion@siga.test',
        ]);

        $this->actingAs($user);

        $currentSessionId = str_repeat('c', 40);
        $foreignSessionId = str_repeat('e', 40);

        $now = now()->timestamp;
        $csrfToken = 'csrf-foreign-session-siga';

        $session = $this->app['session']->driver();

        $session->setId($currentSessionId);
        $session->start();

        $session->put([
            '_token' => $csrfToken,
            'siga_authenticated_at' => $now,
        ]);

        $session->save();

        $updated = $this->db
            ->table('system.sessions')
            ->where('id', $currentSessionId)
            ->update([
                'user_id' => $user->id,
                'ip_address' => '203.0.113.50',
                'user_agent' => 'SIGA Current Browser',
                'last_activity' => $now,
            ]);

        self::assertSame(
            1,
            $updated,
            'La sesión actual debe existir antes de intentar revocar una sesión ajena.'
        );

        $this->db->table('system.sessions')->insert([
            'id' => $foreignSessionId,
            'user_id' => $otherUser->id,
            'ip_address' => '198.51.100.50',
            'user_agent' => 'Foreign SIGA Browser',
            'payload' => 'test-payload-foreign-session',
            'last_activity' => $now - 60,
        ]);

        $publicForeignSessionId = hash_hmac(
            'sha256',
            $foreignSessionId,
            (string) config('app.key')
        );

        $response = $this
            ->withCredentials()
            ->withCookie(
                (string) config('session.cookie'),
                $currentSessionId
            )
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->deleteJson(
                '/api/sessions/'.$publicForeignSessionId
            );

        $response->assertNotFound();

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $foreignSessionId)
                ->count(),
            'Una sesión perteneciente a otro usuario nunca debe ser revocada.'
        );

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $currentSessionId)
                ->count(),
            'La sesión actual debe permanecer activa.'
        );
    }

    public function test_authenticated_user_cannot_revoke_current_session_through_session_management_endpoint(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Sesion Actual SIGA',
            'email' => 'sesion.actual@siga.test',
        ]);

        $this->actingAs($user);

        $currentSessionId = str_repeat('f', 40);

        $now = now()->timestamp;
        $csrfToken = 'csrf-current-session-siga';

        $session = $this->app['session']->driver();

        $session->setId($currentSessionId);
        $session->start();

        $session->put([
            '_token' => $csrfToken,
            'siga_authenticated_at' => $now,
        ]);

        $session->save();

        $updated = $this->db
            ->table('system.sessions')
            ->where('id', $currentSessionId)
            ->update([
                'user_id' => $user->id,
                'ip_address' => '203.0.113.60',
                'user_agent' => 'SIGA Protected Current Browser',
                'last_activity' => $now,
            ]);

        self::assertSame(
            1,
            $updated,
            'La sesión actual debe existir antes de probar su protección.'
        );

        $publicCurrentSessionId = hash_hmac(
            'sha256',
            $currentSessionId,
            (string) config('app.key')
        );

        $response = $this
            ->withCredentials()
            ->withCookie(
                (string) config('session.cookie'),
                $currentSessionId
            )
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->deleteJson(
                '/api/sessions/'.$publicCurrentSessionId
            );

        $response->assertNotFound();

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $currentSessionId)
                ->count(),
            'La sesión actual no debe poder revocarse mediante DELETE /api/sessions/{session}.'
        );
    }

    public function test_authenticated_user_can_revoke_all_other_sessions_without_affecting_current_or_foreign_sessions(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Revocacion Masiva SIGA',
            'email' => 'revocacion.masiva@siga.test',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Usuario Externo Revocacion Masiva SIGA',
            'email' => 'externo.revocacion.masiva@siga.test',
        ]);

        $this->actingAs($user);

        $currentSessionId = str_repeat('c', 40);
        $otherSessionOneId = str_repeat('1', 40);
        $otherSessionTwoId = str_repeat('2', 40);
        $foreignSessionId = str_repeat('3', 40);

        $now = now()->timestamp;
        $csrfToken = 'csrf-revoke-other-sessions-siga';

        $session = $this->app['session']->driver();

        $session->setId($currentSessionId);
        $session->start();

        $session->put([
            '_token' => $csrfToken,
            'siga_authenticated_at' => $now,
        ]);

        $session->save();

        $updated = $this->db
            ->table('system.sessions')
            ->where('id', $currentSessionId)
            ->update([
                'user_id' => $user->id,
                'ip_address' => '203.0.113.70',
                'user_agent' => 'SIGA Current Mass Revoke Browser',
                'last_activity' => $now,
            ]);

        self::assertSame(
            1,
            $updated,
            'La sesión actual debe existir antes de revocar las demás.'
        );

        $this->db->table('system.sessions')->insert([
            [
                'id' => $otherSessionOneId,
                'user_id' => $user->id,
                'ip_address' => '203.0.113.71',
                'user_agent' => 'SIGA Other Browser One',
                'payload' => 'test-payload-other-one',
                'last_activity' => $now - 60,
            ],
            [
                'id' => $otherSessionTwoId,
                'user_id' => $user->id,
                'ip_address' => '203.0.113.72',
                'user_agent' => 'SIGA Other Browser Two',
                'payload' => 'test-payload-other-two',
                'last_activity' => $now - 120,
            ],
            [
                'id' => $foreignSessionId,
                'user_id' => $otherUser->id,
                'ip_address' => '198.51.100.70',
                'user_agent' => 'Foreign Mass Revoke Browser',
                'payload' => 'test-payload-foreign-mass-revoke',
                'last_activity' => $now - 180,
            ],
        ]);

        $response = $this
            ->withCredentials()
            ->withCookie(
                (string) config('session.cookie'),
                $currentSessionId
            )
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->deleteJson('/api/sessions/others');

        $response->assertNoContent();

        self::assertSame(
            0,
            $this->db
                ->table('system.sessions')
                ->where('id', $otherSessionOneId)
                ->count(),
            'La primera sesión adicional del usuario debe quedar revocada.'
        );

        self::assertSame(
            0,
            $this->db
                ->table('system.sessions')
                ->where('id', $otherSessionTwoId)
                ->count(),
            'La segunda sesión adicional del usuario debe quedar revocada.'
        );

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $currentSessionId)
                ->count(),
            'La sesión actual debe permanecer activa.'
        );

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $foreignSessionId)
                ->count(),
            'Las sesiones pertenecientes a otros usuarios no deben verse afectadas.'
        );
    }

    public function test_unauthenticated_user_cannot_list_sessions(): void
    {
        $response = $this
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/sessions');

        $response->assertUnauthorized();
    }

    public function test_invalid_public_session_id_is_rejected(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario ID Sesion Invalido SIGA',
            'email' => 'sesion.invalida@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this
            ->withHeader('Origin', 'http://localhost')
            ->deleteJson('/api/sessions/id-invalido');

        $response->assertNotFound();
    }

    public function test_revoke_other_sessions_succeeds_when_no_other_sessions_exist(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Sin Otras Sesiones SIGA',
            'email' => 'sin.otras.sesiones@siga.test',
        ]);

        $this->actingAs($user);

        $currentSessionId = str_repeat('9', 40);

        $now = now()->timestamp;
        $csrfToken = 'csrf-no-other-sessions-siga';

        $session = $this->app['session']->driver();

        $session->setId($currentSessionId);
        $session->start();

        $session->put([
            '_token' => $csrfToken,
            'siga_authenticated_at' => $now,
        ]);

        $session->save();

        $updated = $this->db
            ->table('system.sessions')
            ->where('id', $currentSessionId)
            ->update([
                'user_id' => $user->id,
                'ip_address' => '203.0.113.90',
                'user_agent' => 'SIGA Only Current Browser',
                'last_activity' => $now,
            ]);

        self::assertSame(
            1,
            $updated,
            'La sesión actual debe existir antes de probar la operación idempotente.'
        );

        $response = $this
            ->withCredentials()
            ->withCookie(
                (string) config('session.cookie'),
                $currentSessionId
            )
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->deleteJson('/api/sessions/others');

        $response->assertNoContent();

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $currentSessionId)
                ->count(),
            'La sesión actual debe conservarse aunque no existan otras sesiones.'
        );

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('user_id', $user->id)
                ->count(),
            'No deben crearse ni eliminarse sesiones adicionales.'
        );
    }

    public function test_expired_idle_session_is_not_listed(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Sesion Expirada SIGA',
            'email' => 'sesion.expirada@siga.test',
        ]);

        $this->actingAs($user);

        $currentSessionId = str_repeat('7', 40);
        $expiredSessionId = str_repeat('8', 40);

        $now = now()->timestamp;

        $session = $this->app['session']->driver();

        $session->setId($currentSessionId);
        $session->start();

        $session->put([
            'siga_authenticated_at' => $now,
        ]);

        $session->save();

        $updated = $this->db
            ->table('system.sessions')
            ->where('id', $currentSessionId)
            ->update([
                'user_id' => $user->id,
                'ip_address' => '203.0.113.100',
                'user_agent' => 'SIGA Current Active Browser',
                'last_activity' => $now,
            ]);

        self::assertSame(
            1,
            $updated,
            'La sesión actual debe existir antes de comprobar sesiones expiradas.'
        );

        $this->db->table('system.sessions')->insert([
            'id' => $expiredSessionId,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.101',
            'user_agent' => 'SIGA Expired Browser',
            'payload' => 'test-payload-expired',
            'last_activity' => $now - ((30 * 60) + 1),
        ]);

        $response = $this
            ->withCredentials()
            ->withCookie(
                (string) config('session.cookie'),
                $currentSessionId
            )
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/sessions');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'ip_address' => '203.0.113.100',
                'user_agent' => 'SIGA Current Active Browser',
                'current' => true,
            ])
            ->assertJsonMissing([
                'ip_address' => '203.0.113.101',
            ])
            ->assertJsonMissing([
                'user_agent' => 'SIGA Expired Browser',
            ]);

        self::assertSame(
            1,
            $this->db
                ->table('system.sessions')
                ->where('id', $expiredSessionId)
                ->count(),
            'La prueba debe demostrar filtrado sin depender del garbage collector.'
        );
    }

    public function test_idle_timeout_boundary_excludes_exact_limit_and_keeps_session_one_second_before_limit(): void
    {
        $fixedNow = Carbon::parse('2026-10-08 12:00:00 UTC');
        $originalLottery = config('session.lottery');

        Carbon::setTestNow($fixedNow);
        config()->set('session.lottery', [0, 100]);

        try {
            $user = User::factory()->create([
                'name' => 'Usuario Limite Inactividad SIGA',
                'email' => 'limite.inactividad@siga.test',
            ]);

            $this->actingAs($user);

            $currentSessionId = str_repeat('4', 40);
            $boundarySessionId = str_repeat('5', 40);
            $stillActiveSessionId = str_repeat('6', 40);

            $lifetimeSeconds = (int) config('session.lifetime') * 60;
            $now = $fixedNow->timestamp;

            $session = $this->app['session']->driver();

            $session->setId($currentSessionId);
            $session->start();

            $session->put([
                'siga_authenticated_at' => $now,
            ]);

            $session->save();

            $updated = $this->db
                ->table('system.sessions')
                ->where('id', $currentSessionId)
                ->update([
                    'user_id' => $user->id,
                    'ip_address' => '203.0.113.110',
                    'user_agent' => 'SIGA Current Boundary Browser',
                    'last_activity' => $now,
                ]);

            self::assertSame(
                1,
                $updated,
                'La sesión actual debe existir para probar el límite de inactividad.'
            );

            $this->db->table('system.sessions')->insert([
                [
                    'id' => $boundarySessionId,
                    'user_id' => $user->id,
                    'ip_address' => '203.0.113.111',
                    'user_agent' => 'SIGA Exact Boundary Browser',
                    'payload' => 'test-payload-exact-boundary',
                    'last_activity' => $now - $lifetimeSeconds,
                ],
                [
                    'id' => $stillActiveSessionId,
                    'user_id' => $user->id,
                    'ip_address' => '203.0.113.112',
                    'user_agent' => 'SIGA Before Boundary Browser',
                    'payload' => 'test-payload-before-boundary',
                    'last_activity' => $now - $lifetimeSeconds + 1,
                ],
            ]);

            $response = $this
                ->withCredentials()
                ->withCookie(
                    (string) config('session.cookie'),
                    $currentSessionId
                )
                ->withHeader('Origin', 'http://localhost')
                ->getJson('/api/sessions');

            $response
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonFragment([
                    'ip_address' => '203.0.113.110',
                    'current' => true,
                ])
                ->assertJsonFragment([
                    'ip_address' => '203.0.113.112',
                    'user_agent' => 'SIGA Before Boundary Browser',
                    'current' => false,
                ])
                ->assertJsonMissing([
                    'ip_address' => '203.0.113.111',
                ])
                ->assertJsonMissing([
                    'user_agent' => 'SIGA Exact Boundary Browser',
                ]);
        } finally {
            Carbon::setTestNow();
            config()->set('session.lottery', $originalLottery);
        }
    }
}
