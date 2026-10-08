<?php

namespace Tests\Functional\Auth;

use App\Actions\User\AssignProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Functional\FunctionalTestCase;

class AssignProfileActionTest extends FunctionalTestCase
{
    public function test_assigns_existing_profile_without_making_it_default(): void
    {
        $user = User::factory()->create();

        $profile = Profile::query()
            ->where('code', 'PERFIL_PERSONAS')
            ->firstOrFail();

        $assigned = (new AssignProfile)->execute(
            $user,
            $profile->id
        );

        self::assertSame(
            $profile->id,
            $assigned->id
        );

        $pivot = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->where('profile_id', $profile->id)
            ->first();

        self::assertNotNull($pivot);
        self::assertFalse(
            (bool) $pivot->is_default
        );
    }

    public function test_assigning_same_profile_again_is_idempotent(): void
    {
        $user = User::factory()->create();

        $profile = Profile::query()
            ->where('code', 'PERFIL_PERSONAS')
            ->firstOrFail();

        $action = new AssignProfile;

        $action->execute(
            $user,
            $profile->id
        );

        $action->execute(
            $user,
            $profile->id
        );

        $total = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->where('profile_id', $profile->id)
            ->count();

        self::assertSame(1, $total);
    }

    public function test_reassigning_current_default_preserves_default(): void
    {
        $user = User::factory()->create();

        $profile = Profile::query()
            ->where('code', 'PERFIL_PERSONAS')
            ->firstOrFail();

        $user->profiles()->attach(
            $profile->id,
            ['is_default' => true]
        );

        $assigned = (new AssignProfile)->execute(
            $user,
            $profile->id
        );

        self::assertSame(
            $profile->id,
            $assigned->id
        );

        $pivot = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->where('profile_id', $profile->id)
            ->first();

        self::assertNotNull($pivot);
        self::assertTrue(
            (bool) $pivot->is_default
        );
    }

    public function test_rejects_missing_profile(): void
    {
        $user = User::factory()->create();

        $missingProfileId = (int) DB::table('system.profiles')
            ->max('id') + 1000;

        $this->expectException(
            ModelNotFoundException::class
        );

        (new AssignProfile)->execute(
            $user,
            $missingProfileId
        );
    }
}
