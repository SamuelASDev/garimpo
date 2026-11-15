<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePastedDescriptionRequest;
use App\Models\JobPosting;
use App\Models\PastedDescription;
use App\Services\ContactMasker;
use App\Services\JobIngestor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Descrição completa colada pelo usuário (quando o site de origem só mostra um resumo). Vale só para ele.
 */
class PastedDescriptionController extends Controller
{
    public function store(StorePastedDescriptionRequest $request, JobPosting $job): RedirectResponse
    {
        $data = $request->validated();

        PastedDescription::updateOrCreate(
            ['user_id' => $request->user()->id, 'job_posting_id' => $job->id],
            ['description' => ContactMasker::strip(JobIngestor::cleanDescription($data['description']))],
        );

        return back()->with('status', 'Descrição salva. A compatibilidade agora considera tudo o que a vaga pede; gere o currículo de novo para a IA usar o texto completo.');
    }

    public function destroy(Request $request, JobPosting $job): RedirectResponse
    {
        PastedDescription::where('user_id', $request->user()->id)->where('job_posting_id', $job->id)->delete();

        return back()->with('status', 'Voltamos a usar a descrição do site de origem.');
    }
}
