<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\ListUserSessions;
use App\Actions\Auth\RevokeOtherUserSessions;
use App\Actions\Auth\RevokeUserSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function index(
        Request $request,
        ListUserSessions $listUserSessions
    ): JsonResponse {
        if (! $request->hasSession()) {
            return response()->json(
                ['message' => 'Unauthenticated.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $sessions = $listUserSessions->execute(
            (int) $request->user()->getAuthIdentifier(),
            $request->session()->getId()
        );

        return response()->json([
            'data' => $sessions,
        ]);
    }

    public function destroy(
        Request $request,
        string $session,
        RevokeUserSession $revokeUserSession
    ): Response {
        if (! $request->hasSession()) {
            return response()->json(
                ['message' => 'Unauthenticated.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $revoked = $revokeUserSession->execute(
            (int) $request->user()->getAuthIdentifier(),
            $request->session()->getId(),
            $session
        );

        if (! $revoked) {
            return response()->json(
                ['message' => 'Not Found.'],
                Response::HTTP_NOT_FOUND
            );
        }

        return response()->noContent();
    }

    public function destroyOthers(
        Request $request,
        RevokeOtherUserSessions $revokeOtherUserSessions
    ): Response {
        if (! $request->hasSession()) {
            return response()->json(
                ['message' => 'Unauthenticated.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $revokeOtherUserSessions->execute(
            (int) $request->user()->getAuthIdentifier(),
            $request->session()->getId()
        );

        return response()->noContent();
    }
}
