<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class AvailablePersonsTest extends HttpFunctionalTestCase
{
    private const URL = '/api/usuarios/personas-disponibles';

    public function test_guest_cannot_search(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_search(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_admin_can_search_by_name_and_surname(): void
    {
        $admin = $this->createAdmin();

        $personId = $this->createPerson(
            'NOMBREBUSQUEDASIGA',
            'APELLIDOBUSQUEDASIGA'
        );

        $this->actingAs($admin);

        foreach (['nombrebusquedasiga', 'apellidobusquedasiga'] as $term) {
            $response = $this->getJson(
                self::URL.'?buscar='.$term
            );

            $response->assertOk();

            self::assertContains(
                $personId,
                array_column($response->json('data'), 'id_persona')
            );
        }
    }

    public function test_excludes_persons_with_existing_users(): void
    {
        $admin = $this->createAdmin();

        $linkedId = $this->createPerson(
            'PERSONAVINCULADABUSQUEDA',
            'PRUEBA'
        );

        $freeId = $this->createPerson(
            'PERSONADISPONIBLEBUSQUEDA',
            'PRUEBA'
        );

        User::factory()->create([
            'id_persona' => $linkedId,
        ]);

        $this->actingAs($admin);

        $response = $this->getJson(
            self::URL.'?buscar=BUSQUEDA'
        )->assertOk();

        $ids = array_column($response->json('data'), 'id_persona');

        self::assertContains($freeId, $ids);
        self::assertNotContains($linkedId, $ids);
    }

    public function test_results_are_paginated(): void
    {
        $admin = $this->createAdmin();

        for ($i = 1; $i <= 21; $i++) {
            $this->createPerson(
                sprintf('PAGINACIONUSUARIOS%02d', $i),
                'PRUEBA'
            );
        }

        $this->actingAs($admin);

        $first = $this->getJson(
            self::URL.'?buscar=PAGINACIONUSUARIOS&page=1'
        );

        $second = $this->getJson(
            self::URL.'?buscar=PAGINACIONUSUARIOS&page=2'
        );

        $first->assertOk()
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20);

        $second->assertOk()
            ->assertJsonPath('meta.current_page', 2);

        self::assertCount(20, $first->json('data'));
        self::assertCount(1, $second->json('data'));
    }

    public function test_rejects_search_longer_than_limit(): void
    {
        $this->actingAs($this->createAdmin());

        $this->getJson(
            self::URL.'?buscar='.str_repeat('A', 101)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buscar']);
    }

    public function test_available_person_includes_registration_traceability(): void
    {
        $admin = $this->createAdmin();

        $personId = $this->createPerson(
            'PERSONAFECHAREGISTROSIGA',
            'PRUEBA'
        );

        $this->actingAs($admin);

        $response = $this->getJson(
            self::URL.'?buscar=PERSONAFECHAREGISTROSIGA'
        )->assertOk();

        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id_persona',
            $personId
        );

        $response->assertJsonPath(
            'data.0.forma_registro',
            'NO_DETERMINADO'
        );

        $response->assertJsonStructure([
            'data' => [
                [
                    'id_persona',
                    'fecha_registro',
                    'forma_registro',
                ],
            ],
        ]);

        self::assertNotEmpty(
            $response->json('data.0.fecha_registro')
        );
    }
    public function test_filters_person_expedient_and_registration_date(): void
    {
        $admin = $this->createAdmin();

        $targetId = $this->createPerson(
            'FILTROPERSONAUNICA',
            'GARCIA'
        );

        $otherId = $this->createPerson(
            'FILTROPERSONADIFERENTE',
            'LOPEZ'
        );

        $target = $this->db->table('institutional.persons')
            ->where('id_persona', $targetId)
            ->first(['num_expediente']);

        self::assertNotNull($target);

        $today = $this->db->selectOne(
            "SELECT (CURRENT_TIMESTAMP AT TIME ZONE 'America/Mexico_City')::date AS fecha"
        )->fecha;

        $this->actingAs($admin);

        $response = $this->getJson(self::URL.'?'.http_build_query([
            'persona' => 'FILTROPERSONAUNICA GARCIA',
            'expediente' => $target->num_expediente,
            'fecha_registro' => $today,
        ]))->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $targetId)
            ->assertJsonPath('meta.total', 1);

        self::assertNotContains(
            $otherId,
            array_column($response->json('data'), 'id_persona')
        );

        $this->getJson(self::URL.'?'.http_build_query([
            'persona' => 'FILTROPERSONADIFERENTE',
            'expediente' => $target->num_expediente,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->getJson(self::URL.'?'.http_build_query([
            'persona' => 'FILTROPERSONAUNICA',
            'fecha_registro' => '2000-01-01',
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_rejects_invalid_column_filters(): void
    {
        $this->actingAs($this->createAdmin());

        $this->getJson(self::URL.'?'.http_build_query([
            'persona' => str_repeat('A', 101),
            'expediente' => str_repeat('1', 14),
            'fecha_registro' => '2026-99-99',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'persona',
                'expediente',
                'fecha_registro',
            ]);
    }
    public function test_filters_by_documented_registration_origin(): void
    {
        $admin = $this->createAdmin();

        $individualId = $this->createPerson(
            'ORIGENFILTROINDIVIDUAL',
            'SIGA'
        );

        $massId = $this->createPerson(
            'ORIGENFILTROMASIVO',
            'SIGA'
        );

        $unknownId = $this->createPerson(
            'ORIGENFILTRONODETERMINADO',
            'SIGA'
        );

        $this->db->table('system.activities')->insert([
            'id_usuario' => $admin->id,
            'entidad' => 'PERSONA',
            'id_entidad' => $individualId,
            'accion' => 'CREACION',
            'datos_anteriores' => null,
            'datos_nuevos' => json_encode(
                ['origen_registro' => 'INDIVIDUAL'],
                JSON_THROW_ON_ERROR
            ),
            'campos_modificados' => json_encode(
                [],
                JSON_THROW_ON_ERROR
            ),
        ]);

        $batchId = $this->db
            ->table('system.import_batches')
            ->insertGetId([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'import_type' => 'PERSONAS',
                'status' => 'COMPLETED',
                'original_filename' => 'filtro-origen.xlsx',
                'storage_disk' => 'local',
                'storage_path' => 'test/filtro-origen.xlsx',
                'file_sha256' => str_repeat('b', 64),
                'total_rows' => 1,
                'valid_rows' => 1,
                'invalid_rows' => 0,
                'processed_rows' => 1,
                'failed_rows' => 0,
                'created_by' => $admin->id,
            ]);

        $this->db->table('system.import_rows')->insert([
            'import_batch_id' => $batchId,
            'row_number' => 2,
            'status' => 'PROCESSED',
            'raw_data' => json_encode([], JSON_THROW_ON_ERROR),
            'normalized_data' => json_encode([], JSON_THROW_ON_ERROR),
            'validation_errors' => json_encode([], JSON_THROW_ON_ERROR),
            'result_data' => json_encode(
                ['id_persona' => $massId],
                JSON_THROW_ON_ERROR
            ),
        ]);

        $this->actingAs($admin);

        foreach ([
            'INDIVIDUAL' => $individualId,
            'MASIVO' => $massId,
            'NO_DETERMINADO' => $unknownId,
        ] as $origin => $expectedId) {
            $response = $this->getJson(
                self::URL.'?'.http_build_query([
                    'buscar' => 'ORIGENFILTRO',
                    'forma_registro' => $origin,
                ])
            )->assertOk();

            $response
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id_persona', $expectedId)
                ->assertJsonPath('data.0.forma_registro', $origin);
        }
    }

    public function test_rejects_invalid_registration_origin_filter(): void
    {
        $this->actingAs($this->createAdmin());

        $this->getJson(self::URL.'?'.http_build_query([
            'forma_registro' => 'DESCONOCIDO',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['forma_registro']);
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

    private function createPerson(
        string $name,
        string $surname
    ): string {
        return (string) $this->db
            ->table('institutional.persons')
            ->insertGetId([
                'nombres' => $name,
                'apellido_paterno' => $surname,
            ], 'id_persona');
    }
}
