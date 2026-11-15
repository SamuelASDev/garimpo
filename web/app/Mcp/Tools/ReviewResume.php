<?php

namespace App\Mcp\Tools;

use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\ResumeTailoring;
use App\Services\AiWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Analisa um currículo e sugere melhorias, igual ao botão "O que posso melhorar?" do editor. Sem job_id: o currículo principal. Com job_id: o currículo adaptado para aquela vaga (cargo alvo, objetivo, o que tirar, o que reescrever). Retorna as instruções e o currículo; escreva as sugestões seguindo-as, mostre ao usuário e, se ele quiser vê-las no editor, chame save-suggestions.')]
class ReviewResume extends Tool
{
    public function handle(Request $request, AiWriter $writer): Response
    {
        $data = $request->validate(['job_id' => ['nullable', 'integer'], 'pages' => ['nullable', 'integer', 'min:1', 'max:5']]);
        $user = $request->user();
        $job = null;

        if (! empty($data['job_id'])) {
            $job = JobPosting::find($data['job_id'])?->forUser($user);
            $html = $job ? ResumeTailoring::where('user_id', $user->id)->where('job_posting_id', $job->id)->value('content') : null;
            if (! $html) {
                return Response::error('Não há currículo adaptado para esta vaga. Gere com prepare-tailored-resume primeiro.');
            }
            $resume = new Resume(['html' => $html]);
        } else {
            $resume = $user->resume;
            if (! $resume) {
                return Response::error('O usuário precisa cadastrar o currículo em '.route('resume.edit').' primeiro.');
            }
        }

        // Sem a medida do editor, estima pelo tamanho (~3.000 caracteres por página A4 neste modelo).
        $pages = $data['pages'] ?? (int) max(1, ceil(mb_strlen($resume->plainText()) / 3000));
        $target = $job ? 'job_id='.$job->id : 'sem job_id (currículo principal)';

        return Response::text(
            $writer->reviewPrompt($resume, $job, $pages)
            ."\n\n=== AO TERMINAR ===\nNão responda só com JSON: mostre as sugestões ao usuário de forma clara (antes → depois). "
            ."Se ele quiser aplicar pelo editor do site, chame save-suggestions ({$target}) com a lista \"recomendacoes\"; "
            .'lá ele troca ou remove cada item com um clique. Nunca invente fatos sobre a pessoa.'
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->description('ID da vaga para analisar o currículo adaptado a ela (omita para o currículo principal)'),
            'pages' => $schema->integer()->description('Quantas páginas o currículo ocupa, se souber (1 a 5)'),
        ];
    }
}
