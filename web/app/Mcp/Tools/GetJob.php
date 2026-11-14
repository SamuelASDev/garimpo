<?php

namespace App\Mcp\Tools;

use App\Models\JobPosting;
use App\Services\AtsMatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Retorna a vaga completa (descrição) e, se o usuário tiver currículo, o score ATS com palavras-chave que faltam.')]
class GetJob extends Tool
{
    public function handle(Request $request, AtsMatcher $matcher): Response
    {
        $data = $request->validate(['job_id' => ['required', 'integer']]);
        $job = JobPosting::find($data['job_id']);
        if (! $job) {
            return Response::error('Vaga não encontrada.');
        }
        $resume = $request->user()?->resume;

        return Response::json([
            ...$job->only(['id', 'title', 'company', 'location', 'remote', 'seniority', 'area', 'techs', 'skills', 'soft_skills', 'url', 'source', 'description']),
            'ats_match' => $resume ? $matcher->match($resume, $job) : 'usuário sem currículo cadastrado',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['job_id' => $schema->integer()->description('ID da vaga (de search-jobs)')->required()];
    }
}
