<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Retorna o currículo cadastrado pelo usuário no Garimpo.')]
class GetResume extends Tool
{
    public function handle(Request $request): Response
    {
        $resume = $request->user()?->resume;

        return $resume
            ? Response::json($resume->only(['full_name', 'headline', 'contact', 'summary', 'skills', 'experience', 'education', 'extras']))
            : Response::error('O usuário ainda não cadastrou o currículo no Garimpo.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
