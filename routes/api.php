<?php

use App\Http\Controllers\Api\PersonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/personas', [PersonController::class, 'store'])
    ->middleware('auth:sanctum');

Route::get('/personas/{id_persona}', [PersonController::class, 'show'])
    ->middleware('auth:sanctum');

Route::patch('/personas/{id_persona}', [PersonController::class, 'update'])
    ->middleware('auth:sanctum');

Route::post('/personas/{id_persona}/baja', [PersonController::class, 'withdraw'])
    ->middleware('auth:sanctum');
