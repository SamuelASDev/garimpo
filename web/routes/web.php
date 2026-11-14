<?php

use App\Http\Controllers\AiConnectionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResumeAssistantController;
use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [JobController::class, 'index'])->name('jobs.index');
Route::get('/vagas/{job}', [JobController::class, 'show'])->name('jobs.show');

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/curriculos')->name('dashboard');
    Route::get('/curriculos', [ResumeController::class, 'index'])->name('resumes.index');
    Route::post('/curriculo/assistente', [ResumeAssistantController::class, 'chat'])->middleware('throttle:20,1')->name('resume.assistant');
    Route::post('/curriculo/sugestoes', [ResumeAssistantController::class, 'suggestions'])->middleware('throttle:10,1')->name('resume.suggestions');
    Route::get('/curriculo', [ResumeController::class, 'edit'])->name('resume.edit');
    Route::put('/curriculo', [ResumeController::class, 'update'])->name('resume.update');
    Route::post('/curriculo/importar', [ResumeController::class, 'import'])->middleware('throttle:10,1')->name('resume.import');
    Route::post('/exportar/word', [ExportController::class, 'word'])->name('export.word');
    Route::post('/exportar/pdf', [ExportController::class, 'pdf'])->name('export.pdf');
    Route::post('/vagas/{job}/adaptar', [JobController::class, 'tailor'])->name('jobs.tailor');
    Route::get('/vagas/{job}/curriculo', [JobController::class, 'resume'])->name('jobs.resume');
    Route::put('/vagas/{job}/curriculo', [JobController::class, 'saveResume'])->name('jobs.resume.update');
    Route::delete('/vagas/{job}/curriculo', [JobController::class, 'destroyResume'])->name('jobs.resume.destroy');
    Route::post('/vagas/{job}/curriculo/assistente', [ResumeAssistantController::class, 'chat'])->middleware('throttle:20,1')->name('jobs.resume.assistant');
    Route::post('/vagas/{job}/candidatura', [JobApplicationController::class, 'storeFromJob'])->name('jobs.apply');

    Route::get('/candidaturas', [JobApplicationController::class, 'index'])->name('applications.index');
    Route::post('/candidaturas', [JobApplicationController::class, 'store'])->name('applications.store');
    Route::patch('/candidaturas/{application}', [JobApplicationController::class, 'update'])->name('applications.update');
    Route::delete('/candidaturas/{application}', [JobApplicationController::class, 'destroy'])->name('applications.destroy');

    Route::get('/conectar-ia', [AiConnectionController::class, 'show'])->name('ai.show');
    Route::post('/conectar-ia', [AiConnectionController::class, 'store'])->name('ai.store');
    Route::delete('/conectar-ia', [AiConnectionController::class, 'destroy'])->name('ai.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
