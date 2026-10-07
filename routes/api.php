<?php

use App\Http\Controllers\Api\PersonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user()->load(
        'person:id_persona,nombres,apellido_paterno,apellido_materno'
    );
})->middleware('auth:sanctum');

Route::post('/personas', [PersonController::class, 'store'])
    ->middleware('auth:sanctum');

Route::get('/personas/{id_persona}', [PersonController::class, 'show'])
    ->middleware([
        'auth:sanctum',
        'siga.permission:personas.ver',
    ]);

Route::patch('/personas/{id_persona}', [PersonController::class, 'update'])
    ->middleware('auth:sanctum');

Route::post('/personas/{id_persona}/baja', [PersonController::class, 'withdraw'])
    ->middleware('auth:sanctum');

Route::post('/personas/{id_persona}/reingreso', [PersonController::class, 'reinstate'])
    ->middleware('auth:sanctum');
