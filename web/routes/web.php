<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AiConnectionController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobAlertController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PastedDescriptionController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResumeAssistantController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/vagas', [JobController::class, 'index'])->name('jobs.index');
Route::get('/vagas/{job}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/privacidade', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/estatisticas', StatsController::class)->name('stats');
Route::get('/cidades', CityController::class)->middleware('throttle:60,1')->name('cities');
Route::get('/termos', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/alertas/parar/{user}', [JobAlertController::class, 'unsubscribe'])->middleware('signed')->name('alerts.unsubscribe');

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/curriculos')->name('dashboard');
    Route::get('/curriculos', [ResumeController::class, 'index'])->name('resumes.index');
    Route::post('/curriculo/assistente', [ResumeAssistantController::class, 'chat'])->middleware('throttle:20,1')->name('resume.assistant');
    Route::post('/curriculo/sugestoes', [ResumeAssistantController::class, 'suggestions'])->middleware('throttle:10,1')->name('resume.suggestions');
    Route::get('/curriculo', [ResumeController::class, 'edit'])->name('resume.edit');
    Route::put('/curriculo', [ResumeController::class, 'update'])->name('resume.update');
    Route::post('/curriculo/importar', [ResumeController::class, 'import'])->middleware('throttle:10,1')->name('resume.import');
    Route::post('/exportar/word', [ExportController::class, 'word'])->middleware('throttle:20,1')->name('export.word');
    Route::post('/exportar/pdf', [ExportController::class, 'pdf'])->middleware('throttle:20,1')->name('export.pdf');
    Route::post('/vagas/{job}/adaptar', [JobController::class, 'tailor'])->name('jobs.tailor');
    Route::get('/vagas/{job}/curriculo', [JobController::class, 'resume'])->name('jobs.resume');
    Route::put('/vagas/{job}/curriculo', [JobController::class, 'saveResume'])->name('jobs.resume.update');
    Route::delete('/vagas/{job}/curriculo', [JobController::class, 'destroyResume'])->name('jobs.resume.destroy');
    Route::put('/vagas/{job}/descricao', [PastedDescriptionController::class, 'store'])->middleware('throttle:20,1')->name('jobs.description.store');
    Route::delete('/vagas/{job}/descricao', [PastedDescriptionController::class, 'destroy'])->name('jobs.description.destroy');
    Route::post('/vagas/{job}/curriculo/sugestoes', [ResumeAssistantController::class, 'jobSuggestions'])->middleware('throttle:10,1')->name('jobs.resume.suggestions');
    Route::post('/vagas/{job}/curriculo/assistente', [ResumeAssistantController::class, 'chat'])->middleware('throttle:20,1')->name('jobs.resume.assistant');
    Route::post('/vagas/{job}/candidatura', [JobApplicationController::class, 'storeFromJob'])->name('jobs.apply');

    Route::get('/alertas', [JobAlertController::class, 'index'])->name('alerts.index');
    Route::post('/alertas', [JobAlertController::class, 'store'])->middleware('throttle:20,1')->name('alerts.store');
    Route::patch('/alertas/{alert}', [JobAlertController::class, 'update'])->name('alerts.update');
    Route::delete('/alertas/{alert}', [JobAlertController::class, 'destroy'])->name('alerts.destroy');

    Route::get('/candidaturas', [JobApplicationController::class, 'index'])->name('applications.index');
    Route::post('/candidaturas', [JobApplicationController::class, 'store'])->name('applications.store');
    Route::patch('/candidaturas/{application}', [JobApplicationController::class, 'update'])->name('applications.update');
    Route::delete('/candidaturas/{application}', [JobApplicationController::class, 'destroy'])->name('applications.destroy');

    Route::get('/conectar-ia', [AiConnectionController::class, 'show'])->name('ai.show');
    Route::post('/conectar-ia', [AiConnectionController::class, 'store'])->middleware('throttle:10,1')->name('ai.store');
    Route::delete('/conectar-ia', [AiConnectionController::class, 'destroy'])->name('ai.destroy');
    Route::delete('/conectar-ia/{tokenId}', [AiConnectionController::class, 'revoke'])->name('ai.revoke');

    Route::patch('/conta/privacidade', [PrivacyController::class, 'updateConsent'])->name('privacy.consent');
    Route::get('/conta/meus-dados', [PrivacyController::class, 'export'])->middleware('throttle:5,1')->name('privacy.export');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->controller(AdminController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/vagas', 'jobs')->name('jobs');
    Route::delete('/vagas', 'destroyAllJobs')->name('jobs.destroyAll');
    Route::get('/vagas/{job}', 'editJob')->name('jobs.edit');
    Route::put('/vagas/{job}', 'updateJob')->name('jobs.update');
    Route::delete('/vagas/{job}', 'destroyJob')->name('jobs.destroy');
    Route::get('/usuarios', 'users')->name('users');
    Route::patch('/usuarios/{user}/admin', 'toggleAdmin')->name('users.admin');
    Route::delete('/usuarios/{user}', 'destroyUser')->name('users.destroy');
});

require __DIR__.'/auth.php';
