<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Retorna o currículo base do usuário no Garimpo (texto). Se não existir, peça o currículo dele e salve com save-resume.')]
class GetResume extends Tool
{
    public function handle(Request $request): Response
    {
        $resume = $request->user()?->resume;

        return $resume
            ? Response::text($resume->plainText())
            : Response::error('O usuário ainda não tem currículo no Garimpo. Peça o currículo (texto ou PDF) e salve com save-resume.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
