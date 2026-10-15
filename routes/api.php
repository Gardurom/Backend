<?php

use App\Authorization\PermissionResolver;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (
    Request $request,
    PermissionResolver $permissionResolver
) {
    $user = $request->user();

    $user->load(
        'person:id_persona,nombres,apellido_paterno,apellido_materno'
    );

    $user->setAttribute(
        'permissions',
        $permissionResolver->effectivePermissions($user)
    );

    return $user;
})->middleware([
    'auth:sanctum',
    'siga.session.absolute',
]);

Route::get('/user/profiles', [UserProfileController::class, 'index'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
    ]);

Route::put(
    '/user/profiles/{profile}/default',
    [UserProfileController::class, 'setDefault']
)
    ->whereNumber('profile')
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
    ]);

Route::get('/sessions', [SessionController::class, 'index'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
    ]);

Route::delete('/sessions/others', [SessionController::class, 'destroyOthers'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.reauthenticated',
    ]);

Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])
    ->where('session', '[a-f0-9]{64}')
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.reauthenticated',
    ]);
Route::post('/personas', [PersonController::class, 'store'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.crear',
    ]);

Route::get('/personas', [PersonController::class, 'index'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.ver',
    ]);

Route::get('/personas/{id_persona}', [PersonController::class, 'show'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.ver',
    ]);

Route::patch('/personas/{id_persona}', [PersonController::class, 'update'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.actualizar',
    ]);

Route::post('/personas/{id_persona}/baja', [PersonController::class, 'withdraw'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.baja',
        'siga.reauthenticated',
    ]);

Route::post('/personas/{id_persona}/reingreso', [PersonController::class, 'reinstate'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.reingreso',
        'siga.reauthenticated',
    ]);
