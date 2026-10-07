<?php

namespace Tests\Functional\Auth;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\Functional\FunctionalTestCase;

class UserProfileModelRelationshipTest extends FunctionalTestCase
{
    public function test_profile_model_uses_system_profiles_table(): void
    {
        $profile = new Profile;

        self::assertSame(
            'system.profiles',
            $profile->getTable()
        );
    }

    public function test_user_belongs_to_many_profiles(): void
    {
        $relation = (new User)->profiles();

        self::assertInstanceOf(
            BelongsToMany::class,
            $relation
        );

        self::assertInstanceOf(
            Profile::class,
            $relation->getRelated()
        );

        self::assertSame(
            'system.user_profiles',
            $relation->getTable()
        );

        self::assertSame(
            'user_id',
            $relation->getForeignPivotKeyName()
        );

        self::assertSame(
            'profile_id',
            $relation->getRelatedPivotKeyName()
        );

        self::assertContains(
            'is_default',
            $relation->getPivotColumns()
        );
    }

    public function test_profile_belongs_to_many_users(): void
    {
        $relation = (new Profile)->users();

        self::assertInstanceOf(
            BelongsToMany::class,
            $relation
        );

        self::assertInstanceOf(
            User::class,
            $relation->getRelated()
        );

        self::assertSame(
            'system.user_profiles',
            $relation->getTable()
        );

        self::assertSame(
            'profile_id',
            $relation->getForeignPivotKeyName()
        );

        self::assertSame(
            'user_id',
            $relation->getRelatedPivotKeyName()
        );

        self::assertContains(
            'is_default',
            $relation->getPivotColumns()
        );
    }
}
