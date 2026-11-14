<?php

namespace App\Http\Controllers;

use App\Models\JobAlert;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobAlertController extends Controller
{
    public function index(Request $request): View
    {
        $alerts = $request->user()->jobAlerts()->latest()->get();

        return view('alerts.index', [
            'alerts' => $alerts,
            // Prévia: quantas vagas cada alerta encontraria hoje (das últimas 24h)
            'today' => $alerts->mapWithKeys(fn (JobAlert $a) => [$a->id => \App\Models\JobPosting::filter($a->filters)->where('created_at', '>', now()->subDay())->count()]),
        ]);
    }

    /** Cria o alerta com os filtros que estavam aplicados na lista de vagas. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->jobAlerts()->count() >= JobAlert::MAX_PER_USER) {
            return back()->with('error', 'Você já tem '.JobAlert::MAX_PER_USER.' alertas. Apague um para criar outro.');
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:60'],
            'seniority' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'tech' => ['nullable', 'array', 'max:10'],
            'tech.*' => ['string'],
            'remote' => ['nullable'],
        ]);

        $alert = new JobAlert(['filters' => JobAlert::cleanFilters($data)]);
        $alert->name = trim((string) ($data['name'] ?? '')) ?: mb_substr($alert->describe(), 0, 80);
        $user->jobAlerts()->save($alert);

        return redirect()->route('alerts.index')->with('status', 'Alerta criado! Você recebe no máximo 1 e-mail por dia com as vagas novas.');
    }

    public function update(Request $request, JobAlert $alert): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 404);
        $alert->update(['active' => ! $alert->active]);

        return back()->with('status', $alert->active ? 'Alerta reativado.' : 'Alerta pausado.');
    }

    public function destroy(Request $request, JobAlert $alert): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 404);
        $alert->delete();

        return back()->with('status', 'Alerta apagado.');
    }

    /** Link "parar de receber" do e-mail: assinado, funciona sem login e pausa todos os alertas. */
    public function unsubscribe(User $user): View
    {
        $user->jobAlerts()->update(['active' => false]);

        return view('alerts.unsubscribed');
    }
}
