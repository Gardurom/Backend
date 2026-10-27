<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function index(): JsonResponse
    {
        $profiles = Profile::query()
            ->select([
                'id',
                'name',
                'code',
                'profile_group_id',
            ])
            ->with([
                'profileGroup:id,name,code,sort_order',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Profile $profile): array => [
                'id' => $profile->id,
                'name' => $profile->name,
                'code' => $profile->code,
                'group' => [
                    'id' => $profile->profileGroup->id,
                    'name' => $profile->profileGroup->name,
                    'code' => $profile->profileGroup->code,
                    'sort_order' => (int) $profile
                        ->profileGroup
                        ->sort_order,
                ],
            ])
            ->values();

        return response()->json([
            'data' => $profiles,
        ]);
    }
}