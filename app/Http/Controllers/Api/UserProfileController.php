<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\ListUserProfiles;
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
}
