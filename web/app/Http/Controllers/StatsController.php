<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Services\JobClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Estatísticas do mercado a partir das vagas coletadas (stacks, senioridade, área, sites...), com filtros.
 */
class StatsController extends Controller
{
    public const PERIODS = [7 => '7 dias', 30 => '30 dias', 90 => '90 dias'];

    public function __invoke(Request $request): View
    {
        $days = array_key_exists((int) $request->days, self::PERIODS) ? (int) $request->days : 30;
        $filters = $request->only(['seniority', 'area', 'tech', 'remote', 'mode', 'city', 'state']);

        $jobs = JobPosting::filter($filters)
            ->where('posted_at', '>=', now()->subDays($days)->startOfDay())
            ->get(['title', 'techs', 'skills', 'soft_skills', 'seniority', 'area', 'source', 'work_mode', 'company', 'posted_at']);

        $labels = JobClassifier::LABELS + ['nao_informada' => 'Não informada', 'outras' => 'Outras'];
        $count = fn (Collection $values, int $limit = 15): array => $values->filter()->countBy()->sortDesc()->take($limit)->all();
        $label = fn (array $counts): array => collect($counts)->mapWithKeys(fn ($n, $key) => [$labels[$key] ?? $key => $n])->all();

        $perDay = collect(range($days - 1, 0))->mapWithKeys(fn ($ago) => [now()->subDays($ago)->format('Y-m-d') => 0])
            ->merge($jobs->countBy(fn ($j) => $j->posted_at?->format('Y-m-d')))->only(
                collect(range($days - 1, 0))->map(fn ($ago) => now()->subDays($ago)->format('Y-m-d'))->all()
            );

        return view('stats.index', [
            'total' => $jobs->count(),
            'days' => $days,
            'filters' => $filters,
            'perDay' => $perDay->all(),
            'techs' => $count($jobs->pluck('techs')->flatten()),
            'stacks' => $count($jobs->map(fn ($j) => JobClassifier::stackOf((string) $j->title, $j->techs ?? []))),
            'withoutStack' => $jobs->filter(fn ($j) => JobClassifier::stackOf((string) $j->title, $j->techs ?? []) === null)->count(),
            'skills' => $count($jobs->pluck('skills')->flatten(), 12),
            'softSkills' => $count($jobs->pluck('soft_skills')->flatten(), 8),
            'seniorities' => $label($count($jobs->pluck('seniority')->map(fn ($s) => $s ?: 'nao_informada'))),
            'areas' => $label($count($jobs->pluck('area')->map(fn ($a) => $a ?: 'outras'))),
            'sources' => $count($jobs->pluck('source')),
            'companies' => $count($jobs->pluck('company'), 10),
            'modes' => $jobs->countBy(fn ($j) => JobClassifier::WORK_MODES[$j->work_mode] ?? 'Não informada')->sortDesc()->all(),
            'options' => ['areas' => array_keys(JobClassifier::AREAS), 'seniorities' => JobClassifier::SENIORITIES, 'techs' => array_keys(JobClassifier::TECHS)],
        ]);
    }
}
