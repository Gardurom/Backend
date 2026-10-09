<?php

use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user()->load(
        'person:id_persona,nombres,apellido_paterno,apellido_materno'
    );
})->middleware([
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
    ]);

Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])
    ->where('session', '[a-f0-9]{64}')
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
    ]);
Route::post('/personas', [PersonController::class, 'store'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.crear',
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
    ]);

Route::post('/personas/{id_persona}/reingreso', [PersonController::class, 'reinstate'])
    ->middleware([
        'auth:sanctum',
        'siga.session.absolute',
        'siga.permission:personas.reingreso',
    ]);
