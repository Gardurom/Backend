<?php

namespace Tests\Functional\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\Functional\FunctionalTestCase;

class UserRoleModelRelationshipTest extends FunctionalTestCase
{
    public function test_role_model_uses_system_roles_table(): void
    {
        $role = new Role;

        self::assertSame(
            'system.roles',
            $role->getTable()
        );
    }

    public function test_user_belongs_to_many_roles(): void
    {
        $relation = (new User)->roles();

        self::assertInstanceOf(
            BelongsToMany::class,
            $relation
        );

        self::assertInstanceOf(
            Role::class,
            $relation->getRelated()
        );

        self::assertSame(
            'system.user_roles',
            $relation->getTable()
        );

        self::assertSame(
            'user_id',
            $relation->getForeignPivotKeyName()
        );

        self::assertSame(
            'role_id',
            $relation->getRelatedPivotKeyName()
        );
    }

    public function test_role_belongs_to_many_users(): void
    {
        $relation = (new Role)->users();

        self::assertInstanceOf(
            BelongsToMany::class,
            $relation
        );

        self::assertInstanceOf(
            User::class,
            $relation->getRelated()
        );

        self::assertSame(
            'system.user_roles',
            $relation->getTable()
        );

        self::assertSame(
            'role_id',
            $relation->getForeignPivotKeyName()
        );

        self::assertSame(
            'user_id',
            $relation->getRelatedPivotKeyName()
        );
    }
}
