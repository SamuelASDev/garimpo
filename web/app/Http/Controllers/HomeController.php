<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Services\JobClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Página inicial: explica o Garimpo e leva para as vagas (abertas a todos, sem conta).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        // Links antigos de filtro ("/?tech=PHP", e-mails de alerta) continuam funcionando.
        if ($request->query()) {
            return redirect()->route('jobs.index', $request->query(), 301);
        }

        // Números da vitrine: recalculados no máximo a cada 10 minutos.
        $numbers = Cache::remember('home.numbers', 600, function (): array {
            $recent = JobPosting::where('posted_at', '>=', now()->subDays(30))->get(['title', 'techs']);

            return [
                'jobs' => JobPosting::count(),
                'sources' => JobPosting::distinct()->count('source'),
                'updated' => JobPosting::max('created_at'),
                'stacks' => $recent->countBy(fn ($j) => JobClassifier::stackOf((string) $j->title, $j->techs ?? []))
                    ->forget('')->sortDesc()->take(5)->all(),
            ];
        });

        return view('home', [
            ...$numbers,
            'latest' => JobPosting::latest('posted_at')->take(4)->get(),
        ]);
    }
}
