<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ResumeVariantController;
use App\Services\ResumeTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Cria uma variação do currículo (versão para um foco: uma stack, uma área, um tipo de vaga de fora do Garimpo...) ou atualiza uma existente (variant_id, de list-resumes). Envie name, category e description (o foco) e "data" no mesmo formato JSON de save-resume. O currículo principal não muda. Nunca invente dados e confirme com o usuário antes de salvar.')]
class SaveVariant extends Tool
{
    public function handle(Request $request, ResumeTemplate $template): Response
    {
        $input = $request->validate([
            'variant_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:300'],
            'data' => ['required', 'array'],
            'data.nome' => ['required', 'string'],
        ]);
        $user = $request->user();
        $html = ResumeTemplate::withHeaderFrom($template->render($request->get('data')), $user->resume?->html);
        $details = ['name' => $input['name'], 'category' => $input['category'] ?? null, 'description' => $input['description'] ?? null, 'html' => $html];

        if ($input['variant_id'] ?? null) {
            $variant = $user->resumeVariants()->find($input['variant_id']);
            if (! $variant) {
                return Response::error('Variação não encontrada. Veja as variações em list-resumes.');
            }
            $variant->update($details);

            return Response::text("Variação \"{$variant->name}\" atualizada. O usuário pode revisar em: ".route('variants.edit', $variant));
        }

        if ($user->resumeVariants()->count() >= ResumeVariantController::MAX_PER_USER) {
            return Response::error('O usuário chegou ao limite de '.ResumeVariantController::MAX_PER_USER.' variações. Peça para ele excluir uma ou atualize uma existente (variant_id).');
        }

        $variant = $user->resumeVariants()->create($details);

        return Response::text("Variação \"{$variant->name}\" criada (variant_id {$variant->id}). O usuário pode revisar em: ".route('variants.edit', $variant));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'variant_id' => $schema->integer()->description('Opcional: ID da variação a atualizar (de list-resumes). Sem ele, cria uma nova.'),
            'name' => $schema->string()->description('Nome da variação, ex.: "Dev Júnior JS/React/Node"')->required(),
            'category' => $schema->string()->description('Categoria, ex.: Back-end, Front-end, Full stack, Dados, Suporte, Estágio'),
            'description' => $schema->string()->description('Foco da variação: para que serve e o que destacar (até 300 caracteres)'),
            'data' => $schema->object()->description('Currículo no formato JSON de save-resume (nome, contato, links, objetivo, formacao, experiencias, ...)')->required(),
        ];
    }
}
