<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Services\AiWriter;
use App\Services\AtsMatcher;
use App\Services\JobClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'seniority', 'area', 'tech', 'remote']);

        return view('jobs.index', [
            'jobs' => JobPosting::filter($filters)->latest('posted_at')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'areas' => array_keys(JobClassifier::AREAS),
            'seniorities' => JobClassifier::SENIORITIES,
            'techs' => array_keys(JobClassifier::TECHS),
        ]);
    }

    public function show(Request $request, JobPosting $job, AtsMatcher $matcher): View
    {
        $resume = $request->user()?->resume;

        return view('jobs.show', [
            'job' => $job,
            'resume' => $resume,
            'match' => $resume ? $matcher->match($resume, $job) : null,
            'tailoring' => $resume ? ResumeTailoring::where('user_id', $request->user()->id)->where('job_posting_id', $job->id)->first() : null,
        ]);
    }

    public function tailor(Request $request, JobPosting $job, AtsMatcher $matcher, AiWriter $writer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->resume, 422, 'Cadastre seu currículo primeiro.');

        $key = 'tailor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Limite de 5 currículos por dia atingido. Use sua própria IA pelo MCP para gerar mais.');
        }

        try {
            $result = $writer->tailor($user->resume, $job, $matcher->match($user->resume, $job));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        RateLimiter::hit($key, 86400);
        ResumeTailoring::updateOrCreate(
            ['user_id' => $user->id, 'job_posting_id' => $job->id],
            ['content' => $result['content'], 'generated_by' => $result['provider']],
        );

        return redirect()->route('jobs.resume', $job);
    }

    public function resume(Request $request, JobPosting $job): View
    {
        $tailoring = ResumeTailoring::where('user_id', $request->user()->id)->where('job_posting_id', $job->id)->firstOrFail();

        return view('jobs.resume', ['job' => $job, 'tailoring' => $tailoring]);
    }
}
