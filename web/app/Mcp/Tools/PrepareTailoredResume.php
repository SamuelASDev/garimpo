<?php

namespace App\Mcp\Tools;

use App\Models\JobPosting;
use App\Services\AiWriter;
use App\Services\AtsMatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Passo 1 para gerar um currículo adaptado: retorna as instruções completas (vaga, currículo do usuário, palavras-chave faltando e regras ATS). Escreva o currículo seguindo-as e depois chame save-tailored-resume.')]
class PrepareTailoredResume extends Tool
{
    public function handle(Request $request, AtsMatcher $matcher, AiWriter $writer): Response
    {
        $data = $request->validate(['job_id' => ['required', 'integer']]);
        $job = JobPosting::find($data['job_id']);
        $resume = $request->user()?->resume;

        if (! $job) {
            return Response::error('Vaga não encontrada.');
        }
        if (! $resume) {
            return Response::error('O usuário precisa cadastrar o currículo em '.route('resume.edit').' primeiro.');
        }

        return Response::text(
            $writer->prompt($resume, $job, $matcher->match($resume, $job))
            ."\n\n=== AO TERMINAR ===\nChame save-tailored-resume com job_id={$job->id} e o JSON acima (incluindo recomendacoes) em data. "
            .'Depois mostre ao usuário o link retornado e o que falta para a vaga.'
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return ['job_id' => $schema->integer()->description('ID da vaga (de search-jobs)')->required()];
    }
}
