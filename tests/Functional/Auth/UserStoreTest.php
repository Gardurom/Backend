<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\HttpFunctionalTestCase;

class UserStoreTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_create_user(): void
    {
        $this->postJson('/api/usuarios', [])
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_create_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->authenticatedPost([])
            ->assertForbidden();
    }

    public function test_authorized_admin_can_create_user(): void
    {
        $admin = $this->createAdmin();
        $personId = $this->createPerson();

        $this->actingAs($admin);

        $response = $this->authenticatedPost([
            'id_persona' => $personId,
            'email' => ' NUEVO.USUARIO@SIGA.TEST ',
            'password' => 'ClaveSegura123456!',
            'password_confirmation' => 'ClaveSegura123456!',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('email', 'nuevo.usuario@siga.test')
            ->assertJsonPath('id_persona', $personId);

        $user = User::query()
            ->where('email', 'nuevo.usuario@siga.test')
            ->firstOrFail();

        self::assertSame($personId, (string) $user->id_persona);

        self::assertTrue(
            Hash::check('ClaveSegura123456!', $user->password)
        );

        self::assertSame(0, $user->roles()->count());

        self::assertArrayNotHasKey(
            'password',
            $response->json()
        );

        self::assertArrayNotHasKey(
            'remember_token',
            $response->json()
        );
    }

    public function test_duplicate_email_is_rejected_after_normalization(): void
    {
        $admin = $this->createAdmin();
        $personId = $this->createPerson();

        User::factory()->create([
            'email' => 'correo.existente@siga.test',
        ]);

        $this->actingAs($admin);

        $this->authenticatedPost([
            'id_persona' => $personId,
            'email' => ' CORREO.EXISTENTE@SIGA.TEST ',
            'password' => 'ClaveSegura123456!',
            'password_confirmation' => 'ClaveSegura123456!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_duplicate_person_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $personId = $this->createPerson();

        User::factory()->create([
            'id_persona' => $personId,
        ]);

        $this->actingAs($admin);

        $this->authenticatedPost([
            'id_persona' => $personId,
            'email' => 'segunda.cuenta@siga.test',
            'password' => 'ClaveSegura123456!',
            'password_confirmation' => 'ClaveSegura123456!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_persona']);
    }

    public function test_short_password_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $this->authenticatedPost([
            'id_persona' => $this->createPerson(),
            'email' => 'clave.corta@siga.test',
            'password' => 'Corta123!',
            'password_confirmation' => 'Corta123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_is_required(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $this->authenticatedPost([
            'id_persona' => $this->createPerson(),
            'email' => 'sin.confirmacion@siga.test',
            'password' => 'ClaveSegura123456!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_recent_reauthentication_is_required(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $csrfToken = 'csrf-user-store-reauthentication-siga';

        $this
            ->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/usuarios', [
                'id_persona' => $this->createPerson(),
                'email' => 'sin.reautenticacion@siga.test',
                'password' => 'ClaveSegura123456!',
                'password_confirmation' => 'ClaveSegura123456!',
            ])
            ->assertStatus(423);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $roleId = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_ADMIN_SISTEMA')
            ->value('id');

        self::assertNotNull($roleId);

        $admin->roles()->attach($roleId);

        return $admin;
    }

    private function createPerson(): string
    {
        return (string) $this->db
            ->table('institutional.persons')
            ->insertGetId([
                'nombres' => 'PERSONA REGISTRO USUARIO',
                'apellido_paterno' => 'PRUEBA',
            ], 'id_persona');
    }

    private function authenticatedPost(array $data)
    {
        $csrfToken = 'csrf-user-store-siga';

        return $this
            ->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
                'siga_reauthenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/usuarios', $data);
    }
}