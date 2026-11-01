<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class AdminUserProfileController extends Controller
{
    public function index(int $user): JsonResponse
    {
        $target = User::query()->findOrFail($user);

        $profiles = $target->profiles()
            ->select([
                'system.profiles.id',
                'system.profiles.code',
                'system.profiles.name',
                'system.profiles.profile_group_id',
            ])
            ->with('profileGroup:id,code,name,sort_order')
            ->orderByPivot('is_default', 'desc')
            ->orderBy('system.profiles.name')
            ->get()
            ->map(static fn (Profile $profile): array => [
                'id' => (int) $profile->id,
                'code' => $profile->code,
                'name' => $profile->name,
                'is_default' => (bool) $profile->pivot->is_default,
                'group' => [
                    'id' => (int) $profile->profileGroup->id,
                    'code' => $profile->profileGroup->code,
                    'name' => $profile->profileGroup->name,
                ],
            ])
            ->values();

        return response()->json([
            'user_id' => (int) $target->id,
            'data' => $profiles,
        ]);
    }

    public function store(
        \Illuminate\Http\Request $request,
        int $user,
        \App\Actions\User\ManageUserProfiles $manager
    ): JsonResponse {
        $validated = $request->validate([
            'profile_id' => ['required', 'integer', 'min:1'],
        ]);

        $profileId = (int) $validated['profile_id'];

        $assigned = $manager->assign(
            $user,
            $profileId,
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'user_id' => $user,
            'profile_id' => $profileId,
            'assigned' => $assigned,
        ], $assigned ? 201 : 200);
    }

    public function destroy(
        \Illuminate\Http\Request $request,
        int $user,
        int $profile,
        \App\Actions\User\ManageUserProfiles $manager
    ): JsonResponse {
        $removed = $manager->remove(
            $user,
            $profile,
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'user_id' => $user,
            'profile_id' => $profile,
            'removed' => $removed,
        ]);
    }
}
