<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\ListUserProfiles;
use App\Actions\User\SetDefaultProfile;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function index(
        Request $request,
        ListUserProfiles $listUserProfiles
    ): JsonResponse {
        $profiles = $listUserProfiles
            ->execute($request->user())
            ->map(
                fn (Profile $profile): array => $this->profileData(
                    $profile
                )
            )
            ->values();

        return response()->json([
            'data' => $profiles,
        ]);
    }

    public function setDefault(
        Request $request,
        int $profile,
        SetDefaultProfile $setDefaultProfile
    ): JsonResponse {
        $selectedProfile = $setDefaultProfile->execute(
            $request->user(),
            $profile
        );

        $selectedProfile->load(
            'profileGroup:id,name,code,sort_order'
        );

        return response()->json([
            'data' => $this->profileData(
                $selectedProfile
            ),
        ]);
    }

    private function profileData(Profile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'code' => $profile->code,
            'is_default' => (bool) $profile
                ->pivot
                ->is_default,
            'group' => [
                'id' => $profile->profileGroup->id,
                'name' => $profile->profileGroup->name,
                'code' => $profile->profileGroup->code,
                'sort_order' => (int) $profile
                    ->profileGroup
                    ->sort_order,
            ],
        ];
    }
}
