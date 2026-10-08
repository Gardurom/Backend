<?php

namespace Tests\Functional\Auth;

use App\Actions\User\UnassignProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Functional\FunctionalTestCase;

class UnassignProfileActionTest extends FunctionalTestCase
{
    public function test_unassigns_profile_from_user(): void
    {
        $user = User::factory()->create();

        $profile = Profile::query()
            ->where('code', 'PERFIL_PERSONAS')
            ->firstOrFail();

        $user->profiles()->attach(
            $profile->id,
            ['is_default' => false]
        );

        (new UnassignProfile)->execute(
            $user,
            $profile->id
        );

        $exists = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->where('profile_id', $profile->id)
            ->exists();

        self::assertFalse($exists);
    }

    public function test_unassigning_default_profile_does_not_select_another_default(): void
    {
        $user = User::factory()->create();

        $personas = Profile::query()
            ->where('code', 'PERFIL_PERSONAS')
            ->firstOrFail();

        $auditoria = Profile::query()
            ->where('code', 'PERFIL_AUDITORIA')
            ->firstOrFail();

        $user->profiles()->attach(
            $personas->id,
            ['is_default' => true]
        );

        $user->profiles()->attach(
            $auditoria->id,
            ['is_default' => false]
        );

        (new UnassignProfile)->execute(
            $user,
            $personas->id
        );

        $remaining = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->get();

        self::assertCount(1, $remaining);

        self::assertSame(
            $auditoria->id,
            $remaining->first()->profile_id
        );

        self::assertFalse(
            (bool) $remaining->first()->is_default
        );

        $defaults = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->count();

        self::assertSame(0, $defaults);
    }

    public function test_unassigning_same_existing_profile_again_is_idempotent(): void
    {
        $user = User::factory()->create();

        $profile = Profile::query()
            ->where('code', 'PERFIL_PERSONAS')
            ->firstOrFail();

        $user->profiles()->attach(
            $profile->id,
            ['is_default' => false]
        );

        $action = new UnassignProfile;

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

        self::assertSame(0, $total);
    }

    public function test_rejects_missing_profile(): void
    {
        $user = User::factory()->create();

        $missingProfileId = (int) DB::table('system.profiles')
            ->max('id') + 1000;

        $this->expectException(
            ModelNotFoundException::class
        );

        (new UnassignProfile)->execute(
            $user,
            $missingProfileId
        );
    }
}
