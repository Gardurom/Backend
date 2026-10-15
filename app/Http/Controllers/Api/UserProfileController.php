<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\ListUserProfiles;
use App\Actions\User\SetDefaultProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function index(
        Request $request,
        ListUserProfiles $listUserProfiles
    ): JsonResponse {
        return response()->json([
            'data' => $listUserProfiles->execute(
                $request->user()
            ),
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

        return response()->json([
            'data' => [
                'id' => $selectedProfile->id,
                'name' => $selectedProfile->name,
                'code' => $selectedProfile->code,
                'is_default' => (bool) $selectedProfile
                    ->pivot
                    ->is_default,
            ],
        ]);
    }
}
