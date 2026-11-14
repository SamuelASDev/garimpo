<?php

namespace App\Mcp\Tools;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Salva no Garimpo um currículo adaptado para uma vaga (texto simples, uma coluna, sem inventar experiências). O usuário verá e poderá exportar em PDF.')]
class SaveTailoredResume extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'job_id' => ['required', 'integer'],
            'content' => ['required', 'string', 'min:200', 'max:30000'],
        ]);
        $job = JobPosting::find($data['job_id']);
        if (! $job) {
            return Response::error('Vaga não encontrada.');
        }

        ResumeTailoring::updateOrCreate(
            ['user_id' => $request->user()->id, 'job_posting_id' => $job->id],
            ['content' => $data['content'], 'generated_by' => 'mcp'],
        );

        return Response::text('Currículo salvo. Link: '.route('jobs.resume', $job));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->required(),
            'content' => $schema->string()->description('Currículo completo adaptado à vaga')->required(),
        ];
    }
}
