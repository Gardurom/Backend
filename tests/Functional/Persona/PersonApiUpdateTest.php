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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA PROTEGIDA ACTUALIZACION', 'PRUEBA']
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno, correo_personal)
         VALUES (?, ?, ?)
         RETURNING id_persona',
            [
                'PERSONA ANTES DE ACTUALIZAR',
                'PRUEBA',
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA NOMBRE ORIGINAL', 'PRUEBA']
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA ESTATUS PROTEGIDO', 'PRUEBA']
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA AUDITORIA ACTUALIZACION', 'PRUEBA']
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA VALIDACION SEXO', 'PRUEBA']
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA VALIDACION ESTADO CIVIL', 'PRUEBA']
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA VALIDACION FECHA NACIMIENTO', 'PRUEBA']
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
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA SIN CAMBIOS API', 'PRUEBA']
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

    public function test_authenticated_user_cannot_remove_last_surname(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION APELLIDOS ACTUALIZACION',
            'email' => 'persona.update.surname@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            [
                'PERSONA VALIDACION APELLIDOS',
                'PRUEBA',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-surname-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'apellido_paterno' => null,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('apellido_paterno');
    }
    public function test_authenticated_user_cannot_remove_last_maternal_surname(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO VALIDACION APELLIDO MATERNO ACTUALIZACION',
            'email' => 'persona.update.maternal-surname@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_materno)
             VALUES (?, ?)
             RETURNING id_persona',
            [
                'PERSONA VALIDACION APELLIDO MATERNO',
                'PRUEBA',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-maternal-surname-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'apellido_materno' => null,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('apellido_materno');
    }
    public function test_authenticated_user_can_replace_paternal_surname_with_maternal_surname(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SUSTITUCION APELLIDO ACTUALIZACION',
            'email' => 'persona.update.replace-surname@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            [
                'PERSONA SUSTITUCION APELLIDO',
                'ORIGINAL',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-replace-surname-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'apellido_paterno' => null,
                    'apellido_materno' => 'NUEVO',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('apellido_paterno', null)
            ->assertJsonPath('apellido_materno', 'NUEVO');

        $storedPerson = $this->db
            ->table('institutional.persons')
            ->where('id_persona', $person->id_persona)
            ->first();

        self::assertNotNull($storedPerson);
        self::assertNull($storedPerson->apellido_paterno);
        self::assertSame(
            'NUEVO',
            $storedPerson->apellido_materno
        );
    }
    public function test_authenticated_user_can_update_other_field_without_resending_existing_surname(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO PATCH PARCIAL PERSONA',
            'email' => 'persona.update.partial@siga.test',
        ]);

        $this->assignRole($user, 'ROL_GESTOR_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_materno)
             VALUES (?, ?)
             RETURNING id_persona',
            [
                'PERSONA PATCH PARCIAL',
                'CONSERVADO',
            ]
        );

        $this->actingAs($user);

        $csrfToken = 'csrf-token-persona-update-partial-siga';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->patchJson(
                '/api/personas/'.$person->id_persona,
                [
                    'nombres' => 'PERSONA PATCH PARCIAL ACTUALIZADA',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'nombres',
                'PERSONA PATCH PARCIAL ACTUALIZADA'
            )
            ->assertJsonPath(
                'apellido_paterno',
                null
            )
            ->assertJsonPath(
                'apellido_materno',
                'CONSERVADO'
            );
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
