<?php

use App\Http\Controllers\JobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [JobController::class, 'index'])->name('jobs.index');
Route::get('/vagas/{job}', [JobController::class, 'show'])->name('jobs.show');

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/curriculo')->name('dashboard');
    Route::get('/curriculo', [ResumeController::class, 'edit'])->name('resume.edit');
    Route::put('/curriculo', [ResumeController::class, 'update'])->name('resume.update');
    Route::post('/vagas/{job}/adaptar', [JobController::class, 'tailor'])->name('jobs.tailor');
    Route::get('/vagas/{job}/curriculo', [JobController::class, 'resume'])->name('jobs.resume');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
