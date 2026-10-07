<?php

namespace Tests\Functional\Persona;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class PersonApiUpdateTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_update_person(): void
    {
        $response = $this->patchJson(
            '/api/personas/01900000-0000-7000-8000-000000000001',
            [
                'nombres' => 'PERSONA ACTUALIZADA',
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_update_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO ACTUALIZAR PERSONA',
            'email' => 'persona.update.forbidden@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
             VALUES (?)
             RETURNING id_persona',
            ['PERSONA PROTEGIDA ACTUALIZACION']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-forbidden-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'nombres' => 'PERSONA NO AUTORIZADA',
                ]
            );

        $response->assertForbidden();
    }

    public function test_authenticated_user_can_update_existing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO ACTUALIZA PERSONA',
            'email' => 'persona.update@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres, correo_personal)
         VALUES (?, ?)
         RETURNING id_persona',
            [
                'PERSONA ANTES DE ACTUALIZAR',
                'antes@example.test',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'nombres' => 'PERSONA ACTUALIZADA API',
                    'correo_personal' => 'despues@example.test',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id_persona',
                $person->id_persona
            )
            ->assertJsonPath(
                'nombres',
                'PERSONA ACTUALIZADA API'
            )
            ->assertJsonPath(
                'correo_personal',
                'despues@example.test'
            );
    }

    public function test_authenticated_user_cannot_update_person_with_empty_name(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION ACTUALIZACION PERSONA',
            'email' => 'persona.update.validation@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA NOMBRE ORIGINAL']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-validation-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'nombres' => '',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombres');
    }

    public function test_authenticated_user_receives_not_found_when_updating_missing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO ACTUALIZA PERSONA INEXISTENTE',
            'email' => 'persona.update.missing@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-missing-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/01900000-0000-7000-8000-000000000001',
                [
                    'nombres' => 'PERSONA INEXISTENTE',
                ]
            );

        $response->assertNotFound();
    }

    public function test_authenticated_user_cannot_override_system_status_on_update(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SEGURIDAD ESTATUS ACTUALIZACION',
            'email' => 'persona.update.status@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA ESTATUS PROTEGIDO']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-status-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'estatus' => 'BAJA',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('estatus', 'ACTIVO');

        $storedPerson = $this->db
            ->table('institutional.persons')
            ->where('id_persona', $person->id_persona)
            ->first();

        self::assertNotNull($storedPerson);
        self::assertSame('ACTIVO', $storedPerson->estatus);
        self::assertNull($storedPerson->fecha_baja);
    }

    public function test_authenticated_user_cannot_spoof_audit_user_on_update(): void
    {
        $authenticatedUser = User::factory()->create([
            'name' => 'USUARIO AUTENTICADO ACTUALIZACION',
            'email' => 'persona.update.audit.authenticated@siga.test',
        ]);

        $this->assignRole($authenticatedUser, 'ROL_GESTOR_PERSONAS');

        $otherUser = User::factory()->create([
            'name' => 'USUARIO SUPLANTADO ACTUALIZACION',
            'email' => 'persona.update.audit.other@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA AUDITORIA ACTUALIZACION']
        );

        $this->actingAs($authenticatedUser);

        $csrfToken = 'csrf-token-persona-update-audit-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'nombres' => 'PERSONA AUDITORIA ACTUALIZADA',
                    'id_usuario' => $otherUser->id,
                ]
            );

        $response->assertOk();

        $activity = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $person->id_persona)
            ->where('accion', 'ACTUALIZACION')
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

    public function test_authenticated_user_cannot_update_person_with_invalid_sex(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION SEXO ACTUALIZACION',
            'email' => 'persona.update.sex@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA VALIDACION SEXO']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-sex-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'sexo' => 'OTRO',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sexo');
    }

    public function test_authenticated_user_cannot_update_person_with_invalid_marital_status(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION ESTADO CIVIL ACTUALIZACION',
            'email' => 'persona.update.marital-status@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA VALIDACION ESTADO CIVIL']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-marital-status-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'estado_civil' => 'DIVORCIADO',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado_civil');
    }

    public function test_authenticated_user_cannot_update_person_with_future_birth_date(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION FECHA NACIMIENTO ACTUALIZACION',
            'email' => 'persona.update.birth-date@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA VALIDACION FECHA NACIMIENTO']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-birth-date-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'fecha_nacimiento' => now()
                        ->timezone('America/Mexico_City')
                        ->addDay()
                        ->toDateString(),
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_nacimiento');
    }

    public function test_authenticated_user_update_without_changes_does_not_create_audit(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO ACTUALIZACION SIN CAMBIOS',
            'email' => 'persona.update.no-changes@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
         VALUES (?)
         RETURNING id_persona',
            ['PERSONA SIN CAMBIOS API']
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-no-changes-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'nombres' => 'PERSONA SIN CAMBIOS API',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id_persona',
                $person->id_persona
            );

        $exists = $this->db
            ->table('system.activities')
            ->where('entidad', 'PERSONA')
            ->where('id_entidad', $person->id_persona)
            ->where('accion', 'ACTUALIZACION')
            ->exists();

        self::assertFalse($exists);
    }

    private function assignRole(
        User $user,
        string $roleCode
    ): void {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $roleCode)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$roleCode}."
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
