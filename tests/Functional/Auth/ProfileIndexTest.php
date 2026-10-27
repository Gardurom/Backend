<?php

namespace Tests\Functional\Auth;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class ProfileIndexTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_list_profiles(): void
    {
        $this->getJson('/api/perfiles')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_list_profiles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/perfiles')
            ->assertForbidden();
    }

    public function test_admin_can_list_profiles_with_groups(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $response = $this->getJson('/api/perfiles');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'group' => [
                            'id',
                            'name',
                            'code',
                            'sort_order',
                        ],
                    ],
                ],
            ]);

        $profiles = $response->json('data');

        self::assertNotEmpty($profiles);

        foreach ($profiles as $profile) {
            self::assertSame(
                ['id', 'name', 'code', 'group'],
                array_keys($profile)
            );

            self::assertSame(
                ['id', 'name', 'code', 'sort_order'],
                array_keys($profile['group'])
            );

            self::assertNotEmpty($profile['code']);
            self::assertNotEmpty($profile['group']['code']);
        }
    }

    public function test_profiles_are_ordered_by_name(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $response = $this->getJson('/api/perfiles');

        $response->assertOk();

        $names = collect($response->json('data'))
            ->pluck('name')
            ->all();

        $expected = $names;
        sort($expected, SORT_STRING);

        self::assertSame($expected, $names);
    }

    public function test_profile_does_not_grant_administrative_permission(): void
    {
        $user = User::factory()->create();

        $profileId = $this->db
            ->table('system.profiles')
            ->value('id');

        self::assertNotNull($profileId);

        $user->profiles()->attach($profileId, [
            'is_default' => true,
        ]);

        $resolver = new PermissionResolver;

        self::assertFalse(
            $resolver->allows($user, 'perfiles.ver')
        );

        $this->actingAs($user);

        $this->getJson('/api/perfiles')
            ->assertForbidden();
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
}