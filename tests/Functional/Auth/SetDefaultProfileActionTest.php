<?php

namespace Tests\Functional\Auth;

use App\Actions\User\SetDefaultProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Functional\FunctionalTestCase;

class SetDefaultProfileActionTest extends FunctionalTestCase
{
    public function test_sets_assigned_profile_as_default_and_clears_previous_default(): void
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

        $profile = (new SetDefaultProfile)->execute(
            $user,
            $auditoria->id
        );

        self::assertSame(
            $auditoria->id,
            $profile->id
        );

        $assignments = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->pluck('is_default', 'profile_id');

        self::assertFalse(
            (bool) $assignments[$personas->id]
        );

        self::assertTrue(
            (bool) $assignments[$auditoria->id]
        );
    }

    public function test_rejects_profile_not_assigned_to_user(): void
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

        try {
            (new SetDefaultProfile)->execute(
                $user,
                $auditoria->id
            );
        } catch (ModelNotFoundException) {
            $assignment = DB::table('system.user_profiles')
                ->where('user_id', $user->id)
                ->where('profile_id', $personas->id)
                ->first();

            self::assertNotNull($assignment);
            self::assertTrue(
                (bool) $assignment->is_default
            );

            return;
        }

        self::fail(
            'No debe establecerse como predeterminado un perfil no asignado al usuario.'
        );
    }

    public function test_setting_current_default_again_is_idempotent(): void
    {
        $user = User::factory()->create();

        $personas = Profile::query()
            ->where('code', 'PERFIL_PERSONAS')
            ->firstOrFail();

        $user->profiles()->attach(
            $personas->id,
            ['is_default' => true]
        );

        $profile = (new SetDefaultProfile)->execute(
            $user,
            $personas->id
        );

        self::assertSame(
            $personas->id,
            $profile->id
        );

        $defaults = DB::table('system.user_profiles')
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->count();

        self::assertSame(1, $defaults);
    }
}
