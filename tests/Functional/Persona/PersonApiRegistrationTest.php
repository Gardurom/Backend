<?php

namespace Tests\Functional\Persona;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class PersonApiRegistrationTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_register_person(): void
    {
        $response = $this->postJson('/api/personas', [
            'nombres' => 'PERSONA NO AUTENTICADA',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_register_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO API PERSONA',
            'email' => 'persona.api@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-api-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'nombres' => 'PERSONA REGISTRADA API',
                'apellido_paterno' => 'PRUEBA',
                'correo_personal' => 'persona.api@example.test',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'nombres',
                'PERSONA REGISTRADA API'
            )
            ->assertJsonPath(
                'apellido_paterno',
                'PRUEBA'
            )
            ->assertJsonPath(
                'correo_personal',
                'persona.api@example.test'
            )
            ->assertJsonPath(
                'estatus',
                'ACTIVO'
            );

        $personId = $response->json('id_persona');

        self::assertNotNull($personId);

        $person = $this->db
            ->table('institutional.persons')
            ->where('id_persona', $personId)
            ->first();

        self::assertNotNull($person);
        self::assertSame(
            'PERSONA REGISTRADA API',
            $person->nombres
        );

        $activity = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $personId)
            ->where('accion', 'CREACION')
            ->first();

        self::assertNotNull($activity);
        self::assertSame(
            $user->id,
            $activity->id_usuario
        );
    }

    public function test_authenticated_user_cannot_register_person_without_name(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION API PERSONA',
            'email' => 'persona.validation@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-validation-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'apellido_paterno' => 'PRUEBA',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombres');
    }

    public function test_authenticated_user_name_is_trimmed_before_registration(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO NORMALIZACION ESPACIOS',
            'email' => 'persona.spaces@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-spaces-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'nombres' => ' PERSONA CON ESPACIOS ',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'nombres',
                'PERSONA CON ESPACIOS'
            );

        $personId = $response->json('id_persona');

        $person = $this->db
            ->table('institutional.persons')
            ->where('id_persona', $personId)
            ->first();

        self::assertNotNull($person);
        self::assertSame(
            'PERSONA CON ESPACIOS',
            $person->nombres
        );
    }

    public function test_authenticated_user_cannot_register_person_with_invalid_sex(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION SEXO',
            'email' => 'persona.sex@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-sex-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'nombres' => 'PERSONA SEXO INVALIDO',
                'sexo' => 'OTRO',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sexo');
    }

    public function test_authenticated_user_cannot_register_person_with_invalid_marital_status(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION ESTADO CIVIL',
            'email' => 'persona.marital-status@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-marital-status-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'nombres' => 'PERSONA ESTADO CIVIL INVALIDO',
                'estado_civil' => 'DIVORCIADO',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado_civil');
    }

    public function test_authenticated_user_cannot_register_person_with_future_birth_date(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION FECHA NACIMIENTO',
            'email' => 'persona.birth-date@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-birth-date-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'nombres' => 'PERSONA FECHA FUTURA',
                'fecha_nacimiento' => now()
                    ->timezone('America/Mexico_City')
                    ->addDay()
                    ->toDateString(),
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_nacimiento');
    }

    public function test_authenticated_user_cannot_override_system_status_on_registration(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SEGURIDAD ESTATUS',
            'email' => 'persona.status@siga.test',
        ]);

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-status-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'nombres' => 'PERSONA ESTATUS PROTEGIDO',
                'estatus' => 'BAJA',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('estatus', 'ACTIVO');

        $personId = $response->json('id_persona');

        $person = $this->db
            ->table('institutional.persons')
            ->where('id_persona', $personId)
            ->first();

        self::assertNotNull($person);
        self::assertSame('ACTIVO', $person->estatus);
        self::assertNull($person->fecha_baja);
    }

    public function test_authenticated_user_cannot_spoof_audit_user_on_registration(): void
    {
        $authenticatedUser = User::factory()->create([
            'name' => 'USUARIO AUTENTICADO API PERSONA',
            'email' => 'persona.audit.authenticated@siga.test',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'USUARIO SUPLANTADO API PERSONA',
            'email' => 'persona.audit.other@siga.test',
        ]);

        $this->actingAs($authenticatedUser);

        $csrfToken = 'csrf-token-persona-audit-user-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/personas', [
                'nombres' => 'PERSONA AUDITORIA SEGURA',
                'id_usuario' => $otherUser->id,
            ]);

        $response->assertCreated();

        $personId = $response->json('id_persona');

        $activity = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $personId)
            ->where('accion', 'CREACION')
            ->first();

        self::assertNotNull($activity);
        self::assertSame(
            $authenticatedUser->id,
            $activity->id_usuario
        );
        self::assertNotSame(
            $otherUser->id,
            $activity->id_usuario
        );
    }
}
