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
