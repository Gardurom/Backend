<?php

use App\Http\Controllers\Api\AcademicRegistryController;
use App\Http\Controllers\Api\AcademicOrganizationController;
use Illuminate\Support\Facades\Route;

// Registro administrativo maestro.
// No habilita consultas globales a cadetes ni a formadores por su función.
Route::prefix('academico')->middleware([
    'auth:sanctum',
    'siga.session.absolute',
    'siga.access',
])->group(function () {
    Route::get('/participantes', [AcademicRegistryController::class, 'participants'])
        ->middleware('siga.permission:academico.participantes.ver');

    Route::get('/participantes/{id}', [AcademicRegistryController::class, 'participant'])
        ->whereUuid('id')
        ->middleware('siga.permission:academico.participantes.ver');

    Route::post('/participantes', [AcademicRegistryController::class, 'storeParticipant'])
        ->middleware('siga.permission:academico.participantes.registrar');

    Route::get('/formadores', [AcademicRegistryController::class, 'trainers'])
        ->middleware('siga.permission:academico.formadores.ver');

    Route::get('/formadores/{id}', [AcademicRegistryController::class, 'trainer'])
        ->whereUuid('id')
        ->middleware('siga.permission:academico.formadores.ver');

    Route::post('/formadores', [AcademicRegistryController::class, 'storeTrainer'])
        ->middleware('siga.permission:academico.formadores.registrar');

    Route::middleware('siga.permission:academico.organizacion.ver')
        ->group(function () {
            Route::get('/programas', [AcademicOrganizationController::class, 'programs']);
            Route::get('/cohortes', [AcademicOrganizationController::class, 'cohorts']);
            Route::get('/sedes', [AcademicOrganizationController::class, 'sites']);
            Route::get('/grupos', [AcademicOrganizationController::class, 'groups']);
            Route::get('/historial-sedes', [AcademicOrganizationController::class, 'siteHistory']);
        });
});
