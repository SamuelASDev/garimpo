<?php

namespace App\Mcp\Tools;

use App\Services\ResumeTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Salva o currículo BASE do usuário no modelo padrão do Garimpo (ex.: depois de ler o PDF/currículo que ele te enviou). Envie "data" em JSON com as chaves: nome, contato, links, objetivo{cargo,texto}, formacao[{curso,instituicao,periodo}], experiencias[{cargo,empresa,periodo,atividades[]}], cursos[], projetos[{nome,periodo,descricao}], habilidades_tecnicas[], idiomas[], outras_habilidades, habilidades_interpessoais[{nome,descricao}]. Nunca invente dados; use "[[ADICIONE: ...]]" onde faltar informação. Substitui o currículo atual: confirme com o usuário antes.')]
class SaveResume extends Tool
{
    public function handle(Request $request, ResumeTemplate $template): Response
    {
        $request->validate(['data' => ['required', 'array'], 'data.nome' => ['required', 'string']]);
        $input = ['data' => $request->get('data')];

        $request->user()->resume()->updateOrCreate([], ['html' => $template->render($input['data'])]);

        return Response::text('Currículo salvo no modelo do Garimpo. O usuário pode revisar em: '.route('resume.edit'));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['data' => $schema->object()->description('Currículo estruturado no formato do modelo')->required()];
    }
}
