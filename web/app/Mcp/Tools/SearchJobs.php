<?php

namespace App\Mcp\Tools;

use App\Models\JobPosting;
use App\Services\JobClassifier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Busca vagas de TI no Garimpo, das mais novas para as mais antigas. Retorna resumo; use get-job para os detalhes.')]
class SearchJobs extends Tool
{
    public function handle(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'seniority' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'tech' => ['nullable', 'string'],
            'remote' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $jobs = JobPosting::filter($filters)->latest('posted_at')->limit($filters['limit'] ?? 20)
            ->get(['id', 'title', 'company', 'location', 'remote', 'seniority', 'area', 'techs', 'skills', 'url', 'posted_at']);

        return Response::json($jobs->map(fn (JobPosting $j): array => [
            ...$j->only(['id', 'title', 'company', 'location', 'remote', 'seniority', 'area', 'techs', 'skills', 'url']),
            'posted_at' => $j->posted_at?->toDateString(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Texto no cargo ou empresa'),
            'seniority' => $schema->string()->enum(JobClassifier::SENIORITIES),
            'area' => $schema->string()->enum(array_keys(JobClassifier::AREAS)),
            'tech' => $schema->string()->description('Tecnologia exata, ex.: PHP, React, AWS'),
            'remote' => $schema->boolean()->description('Só vagas remotas'),
            'limit' => $schema->integer()->description('Máximo de vagas (padrão 20, máx. 50)'),
        ];
    }
}
