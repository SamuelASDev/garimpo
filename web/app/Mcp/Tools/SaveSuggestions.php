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

#[Description('Salva sugestões de melhoria (de review-resume) no painel "Sugestões da IA" do editor do site, onde o usuário troca ou remove cada item com um clique. Não altera o currículo em si.')]
class SaveSuggestions extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'job_id' => ['nullable', 'integer'],
            'recomendacoes' => ['required', 'array', 'max:15'],
        ]);
        $user = $request->user();
        $recommendations = ResumeTemplate::recommendations($request->get('recomendacoes'));
        if ($recommendations === []) {
            return Response::error('Nenhuma sugestão válida. Cada item precisa de "tipo" (remover|adicionar|ajustar), "trecho" (texto exato do currículo), "motivo" e, para ajustar, "novo".');
        }

        if ($jobId = $request->get('job_id')) {
            $tailoring = JobPosting::find($jobId)
                ? ResumeTailoring::where('user_id', $user->id)->where('job_posting_id', $jobId)->first()
                : null;
            if (! $tailoring) {
                return Response::error('Não há currículo adaptado para esta vaga.');
            }
            $tailoring->update(['recommendations' => $recommendations]);
            $link = route('jobs.resume', $jobId);
        } else {
            if (! $user->resume) {
                return Response::error('O usuário ainda não tem currículo principal.');
            }
            $user->resume->update(['recommendations' => $recommendations]);
            $link = route('resume.edit');
        }

        return Response::text(count($recommendations).' sugestões salvas. O usuário aplica cada uma no editor: '.$link);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->description('ID da vaga (omita para o currículo principal)'),
            'recomendacoes' => $schema->array()->description('Lista de {"tipo":"remover|adicionar|ajustar","trecho":"texto exato","novo":"texto reescrito (ajustar)","motivo":"..."}')->required(),
        ];
    }
}
