<?php

namespace App\Mcp\Tools;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Services\ResumeTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Passo 2: salva o currículo adaptado para a vaga. Envie "data" no formato JSON descrito por prepare-tailored-resume (com "recomendacoes"); o Garimpo monta no modelo padrão e abre no editor do usuário.')]
class SaveTailoredResume extends Tool
{
    public function handle(Request $request, ResumeTemplate $template): Response
    {
        $input = $request->validate([
            'job_id' => ['required', 'integer'],
            'data' => ['required', 'array'],
            'data.nome' => ['required', 'string'],
        ]);
        $input['data'] = $request->get('data'); // validate() devolve só data.nome; aqui vai o currículo inteiro
        $job = JobPosting::find($input['job_id']);
        if (! $job) {
            return Response::error('Vaga não encontrada.');
        }

        ResumeTailoring::updateOrCreate(
            ['user_id' => $request->user()->id, 'job_posting_id' => $job->id],
            [
                'content' => $template->render($input['data']),
                'generated_by' => 'mcp',
                'recommendations' => ResumeTemplate::recommendations($input['data']['recomendacoes'] ?? []),
            ],
        );

        return Response::text('Currículo salvo. O usuário pode revisar e exportar em: '.route('jobs.resume', $job));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->required(),
            'data' => $schema->object()->description('Currículo no formato JSON do modelo (nome, contato, objetivo, experiencias, ... e recomendacoes)')->required(),
        ];
    }
}
