<?php

namespace Tests\Functional\Auth;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\Functional\FunctionalTestCase;

class RolePermissionModelRelationshipTest extends FunctionalTestCase
{
    public function test_permission_model_uses_system_permissions_table(): void
    {
        $permission = new Permission;

        self::assertSame(
            'system.permissions',
            $permission->getTable()
        );
    }

    public function test_role_belongs_to_many_permissions(): void
    {
        $relation = (new Role)->permissions();

        self::assertInstanceOf(
            BelongsToMany::class,
            $relation
        );

        self::assertInstanceOf(
            Permission::class,
            $relation->getRelated()
        );

        self::assertSame(
            'system.role_permissions',
            $relation->getTable()
        );

        self::assertSame(
            'role_id',
            $relation->getForeignPivotKeyName()
        );

        self::assertSame(
            'permission_id',
            $relation->getRelatedPivotKeyName()
        );
    }

    public function test_permission_belongs_to_many_roles(): void
    {
        $relation = (new Permission)->roles();

        self::assertInstanceOf(
            BelongsToMany::class,
            $relation
        );

        self::assertInstanceOf(
            Role::class,
            $relation->getRelated()
        );

        self::assertSame(
            'system.role_permissions',
            $relation->getTable()
        );

        self::assertSame(
            'permission_id',
            $relation->getForeignPivotKeyName()
        );

        self::assertSame(
            'role_id',
            $relation->getRelatedPivotKeyName()
        );
    }
}
