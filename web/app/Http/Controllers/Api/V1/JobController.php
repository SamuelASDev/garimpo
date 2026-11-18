<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\JobPosting;
use App\Services\JobClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * API pública de vagas (somente leitura). As respostas ficam em cache por alguns minutos: a coleta roda a cada
 * 4h, então ninguém perde vaga nova, e muitas pessoas pedindo a mesma lista viram uma consulta só no banco.
 */
class JobController extends Controller
{
    public const CACHE_SECONDS = 300;

    public const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $params = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'senioridade' => ['nullable', 'string', 'in:'.implode(',', JobClassifier::SENIORITIES)],
            'area' => ['nullable', 'string', 'in:'.implode(',', array_keys(JobClassifier::AREAS))],
            'modo' => ['nullable', 'string', 'in:'.implode(',', array_keys(JobClassifier::WORK_MODES))],
            'tecnologia' => ['nullable', 'string', 'max:200'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'size:2'],
            'desde' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        ksort($params);

        $body = Cache::remember('api:v1:jobs:'.md5(json_encode($params)), self::CACHE_SECONDS, function () use ($params, $request): array {
            $jobs = JobPosting::filter([
                'q' => $params['q'] ?? null,
                'seniority' => $params['senioridade'] ?? null,
                'area' => $params['area'] ?? null,
                'mode' => $params['modo'] ?? null,
                'tech' => array_filter(array_map('trim', explode(',', $params['tecnologia'] ?? ''))),
                'city' => $params['cidade'] ?? null,
                'state' => $params['uf'] ?? null,
            ])
                ->when($params['desde'] ?? null, fn ($q, $since) => $q->where('created_at', '>=', $since))
                ->latest('posted_at')->latest('id')
                ->paginate($params['por_pagina'] ?? 20)->appends($request->except('page'));

            return JobResource::collection($jobs)->response()->getData(true);
        });

        return response()->json($body)->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }

    public function show(JobPosting $job): JsonResponse
    {
        $body = Cache::remember('api:v1:job:'.$job->id, self::CACHE_SECONDS, fn (): array => ['data' => (new JobResource($job))->resolve()]);

        return response()->json($body)->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }

    /** Números do período (7, 30 ou 90 dias): os mesmos da página de estatísticas. */
    public function stats(Request $request): JsonResponse
    {
        $days = (int) ($request->validate(['dias' => ['nullable', 'integer', 'in:7,30,90']])['dias'] ?? 30);

        $body = Cache::remember('api:v1:stats:'.$days, 600, function () use ($days): array {
            $jobs = JobPosting::where('posted_at', '>=', now()->subDays($days)->startOfDay())
                ->get(['title', 'techs', 'seniority', 'area', 'work_mode', 'source']);
            $count = fn (Collection $values, int $limit = 20): array => $values->filter()->countBy()->sortDesc()->take($limit)->all();

            return ['data' => [
                'periodo_dias' => $days,
                'total_vagas' => $jobs->count(),
                'tecnologias' => $count($jobs->pluck('techs')->flatten()),
                'stacks' => $count($jobs->map(fn ($j) => JobClassifier::stackOf((string) $j->title, $j->techs ?? []))),
                'senioridades' => $count($jobs->pluck('seniority')),
                'areas' => $count($jobs->pluck('area')),
                'modos' => $count($jobs->pluck('work_mode')),
                'fontes' => $count($jobs->pluck('source')),
                'atualizado_em' => now()->toIso8601String(),
            ]];
        });

        return response()->json($body)->header('Cache-Control', 'public, max-age=600');
    }
}
