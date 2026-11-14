<?php

use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

Route::post('/ingest', IngestController::class)->middleware('throttle:30,1')->name('api.ingest');
Route::post('/ingest/pending', [IngestController::class, 'pending'])->middleware('throttle:30,1')->name('api.ingest.pending');
