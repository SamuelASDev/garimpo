<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\ResumeTailoring;
use App\Services\AiWriter;
use App\Services\AtsMatcher;
use App\Services\JobClassifier;
use App\Services\ResumeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(Request $request, AtsMatcher $matcher): View
    {
        $filters = $request->only(['q', 'seniority', 'area', 'tech', 'remote', 'mode', 'city', 'state']);
        $jobs = JobPosting::filter($filters)->latest('posted_at')->paginate(20)->withQueryString();

        // % de compatibilidade de cada vaga com o currículo do usuário (mesma conta do ATS).
        $have = ($resume = $request->user()?->resume) ? $matcher->keywords($resume->plainText()) : null;
        $scores = $have === null ? [] : $jobs->getCollection()->mapWithKeys(function (JobPosting $j) use ($have): array {
            $required = AtsMatcher::requirements($j);

            return [$j->id => $required ? (int) round(count(array_intersect($required, $have)) / count($required) * 100) : null];
        })->all();

        return view('jobs.index', [
            'jobs' => $jobs,
            'scores' => $scores,
            'filters' => $filters,
            'areas' => array_keys(JobClassifier::AREAS),
            'seniorities' => JobClassifier::SENIORITIES,
            'techs' => array_keys(JobClassifier::TECHS),
            'modes' => JobClassifier::WORK_MODES,
        ]);
    }

    public function show(Request $request, JobPosting $job, AtsMatcher $matcher): View
    {
        $job = $job->forUser($request->user()); // usa a descrição colada pela pessoa, se houver
        $resume = $request->user()?->resume;

        return view('jobs.show', [
            'job' => $job,
            'resume' => $resume,
            'match' => $resume ? $matcher->match($resume, $job) : null,
            'tailoring' => $resume ? ResumeTailoring::where('user_id', $request->user()->id)->where('job_posting_id', $job->id)->first() : null,
        ]);
    }

    /** Cria o currículo para a vaga (IA grátis; sem IA, copia o currículo base) e abre o editor. */
    public function tailor(Request $request, JobPosting $job, AtsMatcher $matcher, AiWriter $writer, ResumeTemplate $template): RedirectResponse
    {
        $job = $job->forUser($request->user()); // usa a descrição colada pela pessoa, se houver
        $user = $request->user();
        if (! $user->resume) {
            return redirect()->route('resume.edit')->with('error', 'Primeiro monte ou importe o seu currículo.');
        }

        $key = 'tailor:'.$user->id;
        $notice = null;
        try {
            if (! $user->hasAiConsent()) {
                throw new \RuntimeException('Você ainda não autorizou a IA grátis. Ative em Minha conta → Privacidade (seu currículo vai sem telefone, e-mail e links).');
            }
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw new \RuntimeException('Limite de 5 currículos por dia com a IA grátis atingido. Conecte sua IA na Seção dev para gerar sem limite.');
            }
            $result = $writer->tailor($user->resume, $job, $matcher->match($user->resume, $job));
            RateLimiter::hit($key, 86400);
            $html = ResumeTemplate::withHeaderFrom($template->render($result['data']), $user->resume->html);
            $provider = $result['provider'];
            $recommendations = ResumeTemplate::recommendations($result['data']['recomendacoes'] ?? []);
        } catch (\RuntimeException $e) {
            $html = $user->resume->html ?? $template->fromPlainText($user->resume->plainText());
            $provider = 'copia';
            $recommendations = [];
            $notice = $e->getMessage().' Abrimos uma cópia do seu currículo para você ajustar usando o painel "O que falta".';
        }

        ResumeTailoring::updateOrCreate(
            ['user_id' => $user->id, 'job_posting_id' => $job->id],
            ['content' => $html, 'generated_by' => $provider, 'recommendations' => $recommendations],
        );

        return redirect()->route('jobs.resume', $job)->with('error', $notice);
    }

    /** Editor do currículo adaptado + painel do que falta. */
    public function resume(Request $request, JobPosting $job, AtsMatcher $matcher): View
    {
        $job = $job->forUser($request->user()); // usa a descrição colada pela pessoa, se houver
        $tailoring = $this->tailoringFor($request, $job);
        $draft = new Resume(['html' => $tailoring->content]);

        return view('jobs.resume', [
            'job' => $job,
            'tailoring' => $tailoring,
            'match' => $matcher->match($draft, $job),
            'placeholders' => ResumeTemplate::placeholders($tailoring->content),
        ]);
    }

    public function saveResume(Request $request, JobPosting $job, AtsMatcher $matcher): JsonResponse
    {
        $job = $job->forUser($request->user()); // usa a descrição colada pela pessoa, se houver
        $data = $request->validate(['html' => ['required', 'string', 'max:200000']]);
        $data['html'] = ResumeTemplate::sanitize($data['html']);
        $tailoring = $this->tailoringFor($request, $job);
        $tailoring->update(['content' => $data['html']]);

        return response()->json([
            'message' => 'Currículo salvo!',
            'match' => $matcher->match(new Resume(['html' => $data['html']]), $job),
            'placeholders' => ResumeTemplate::placeholders($data['html']),
        ]);
    }

    public function destroyResume(Request $request, JobPosting $job): RedirectResponse
    {
        $this->tailoringFor($request, $job)->delete();

        return redirect()->route('resumes.index')->with('status', 'Currículo da vaga excluído.');
    }

    private function tailoringFor(Request $request, JobPosting $job): ResumeTailoring
    {
        return ResumeTailoring::where('user_id', $request->user()->id)->where('job_posting_id', $job->id)->firstOrFail();
    }
}
