<?php

namespace Tests\Functional\Auth;

use App\Models\Profile;
use App\Models\ProfileGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Functional\FunctionalTestCase;

class ProfileGroupModelRelationshipTest extends FunctionalTestCase
{
    public function test_profile_group_model_uses_system_profile_groups_table(): void
    {
        $profileGroup = new ProfileGroup;

        self::assertSame(
            'system.profile_groups',
            $profileGroup->getTable()
        );
    }

    public function test_profile_group_has_many_profiles(): void
    {
        $relation = (new ProfileGroup)->profiles();

        self::assertInstanceOf(
            HasMany::class,
            $relation
        );

        self::assertInstanceOf(
            Profile::class,
            $relation->getRelated()
        );

        self::assertSame(
            'profile_group_id',
            $relation->getForeignKeyName()
        );

        self::assertSame(
            'id',
            $relation->getLocalKeyName()
        );
    }

    public function test_profile_belongs_to_profile_group(): void
    {
        $relation = (new Profile)->profileGroup();

        self::assertInstanceOf(
            BelongsTo::class,
            $relation
        );

        self::assertInstanceOf(
            ProfileGroup::class,
            $relation->getRelated()
        );

        self::assertSame(
            'profile_group_id',
            $relation->getForeignKeyName()
        );

        self::assertSame(
            'id',
            $relation->getOwnerKeyName()
        );
    }
}
