<?php

namespace App\Mcp\Tools;

use App\Models\Resume;
use App\Models\ResumeTailoring;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Retorna o texto do currículo principal do usuário, do currículo adaptado de uma vaga (job_id) ou de uma variação (variant_id, veja list-resumes). Para alterar: save-resume (principal) ou save-tailored-resume (vaga) ou save-variant (variação).')]
class GetResume extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['job_id' => ['nullable', 'integer'], 'variant_id' => ['nullable', 'integer']]);
        $user = $request->user();

        if ($data['variant_id'] ?? null) {
            $variant = $user->resumeVariants()->find($data['variant_id']);

            return $variant
                ? Response::text("Variação: {$variant->name}".($variant->category ? " ({$variant->category})" : '').($variant->description ? "\nFoco: {$variant->description}" : '')."\n\n".(new Resume(['html' => $variant->html]))->plainText())
                : Response::error('Variação não encontrada. Veja as variações em list-resumes.');
        }

        if ($data['job_id'] ?? null) {
            $tailoring = ResumeTailoring::where('user_id', $user->id)->where('job_posting_id', $data['job_id'])->first();

            return $tailoring
                ? Response::text((new Resume(['html' => $tailoring->content]))->plainText())
                : Response::error('Não existe currículo para essa vaga. Gere com prepare-tailored-resume.');
        }

        return $user->resume
            ? Response::text($user->resume->plainText())
            : Response::error('O usuário ainda não tem currículo no Garimpo. Peça o currículo (texto ou PDF) e salve com save-resume.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->description('Opcional: ID da vaga para ler o currículo adaptado'),
            'variant_id' => $schema->integer()->description('Opcional: ID da variação (de list-resumes) para ler'),
        ];
    }
}
