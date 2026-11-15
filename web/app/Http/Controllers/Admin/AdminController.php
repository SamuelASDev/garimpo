<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateJobPostingRequest;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\JobClassifier;
use App\Services\Locations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Painel de administração: corrigir/apagar vagas e gerenciar contas. Protegido pelo Gate "admin".
 */
class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'jobs' => JobPosting::count(),
            'jobsToday' => JobPosting::where('created_at', '>=', now()->subDay())->count(),
            'users' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'admins' => User::where('is_admin', true)->count(),
            'sources' => JobPosting::selectRaw('source, count(*) as total, max(created_at) as last')->groupBy('source')->orderByDesc('total')->get(),
        ]);
    }

    public function jobs(Request $request): View
    {
        $jobs = JobPosting::query()
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('company', 'like', "%$v%")))
            ->when($request->source, fn ($q, $v) => $q->where('source', $v))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.jobs', ['jobs' => $jobs, 'sources' => JobPosting::distinct()->orderBy('source')->pluck('source')]);
    }

    public function editJob(JobPosting $job): View
    {
        return view('admin.job-edit', ['job' => $job, 'labels' => JobClassifier::LABELS]);
    }

    public function updateJob(UpdateJobPostingRequest $request, JobPosting $job): RedirectResponse
    {
        $data = $request->validated();

        // Só tecnologias conhecidas (são as que os filtros entendem), com a grafia oficial.
        $known = collect(array_keys(JobClassifier::TECHS))->keyBy(fn ($t) => Str::lower($t));
        $techs = collect(explode(',', (string) $data['techs']))
            ->map(fn ($t) => $known[Str::lower(trim($t))] ?? null)->filter()->unique()->values()->all();

        $job->update([
            ...$data,
            'techs' => $techs,
            'remote' => ($data['work_mode'] ?? null) === 'remoto',
            ...Locations::parse($data['location'] ?? null),
            'description' => (string) $data['description'],
            'summary' => Str::limit(preg_replace('/\s+/', ' ', (string) $data['description']), 280),
        ]);

        return redirect()->route('admin.jobs')->with('status', 'Vaga atualizada.');
    }

    public function destroyJob(JobPosting $job): RedirectResponse
    {
        $job->delete();

        return back()->with('status', 'Vaga apagada.');
    }

    /** Apaga todas as vagas (para recoletar com regras novas). Opcionalmente preserva as que têm currículo adaptado. */
    public function destroyAllJobs(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:APAGAR']], ['confirm.in' => 'Digite APAGAR para confirmar.', 'confirm.required' => 'Digite APAGAR para confirmar.']);

        $query = JobPosting::query();
        if ($request->boolean('keep_used')) {
            $query->whereDoesntHave('tailorings')->whereDoesntHave('pastedDescriptions');
        }
        $total = $query->count();
        $query->delete();

        return redirect()->route('admin.index')->with('status', "$total vagas apagadas. A próxima coleta recomeça do zero (até 30 dias para trás).");
    }

    public function users(Request $request): View
    {
        $users = User::query()
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->withCount(['jobApplications', 'jobAlerts'])
            ->latest()->paginate(30)->withQueryString();

        return view('admin.users', ['users' => $users]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Você não pode tirar o seu próprio acesso.']);
        }
        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin ? "{$user->email} agora é administrador." : "{$user->email} não é mais administrador.");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Para apagar a sua conta, use Minha conta.']);
        }
        $user->delete();

        return back()->with('status', 'Conta apagada (com currículos, candidaturas, alertas e acessos de IA).');
    }
}
