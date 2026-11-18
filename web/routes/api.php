<?php

use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::post('/ingest', IngestController::class)->middleware('throttle:30,1')->name('api.ingest');
Route::post('/ingest/pending', [IngestController::class, 'pending'])->middleware('throttle:30,1')->name('api.ingest.pending');

// API pública v1 (somente leitura): chave obrigatória, 60 req/min e 5.000/dia por chave. Documentação em /api.
Route::prefix('v1')->name('api.v1.')->middleware([AuthenticateApiKey::class, 'throttle:api-v1'])->group(function () {
    Route::get('/vagas', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/vagas/{job}', [JobController::class, 'show'])->whereNumber('job')->name('jobs.show');
    Route::get('/estatisticas', [JobController::class, 'stats'])->name('stats');
});
