<?php

namespace App\Mcp\Tools;

use App\Models\ResumeTailoring;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista os currículos do usuário: o principal e os adaptados para vagas (com job_id). Use get-resume com job_id para ler um deles.')]
class ListResumes extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        return Response::json([
            'principal' => $user->resume ? ['atualizado_em' => $user->resume->updated_at->toDateTimeString()] : null,
            'por_vaga' => ResumeTailoring::with('jobPosting:id,title,company')->where('user_id', $user->id)->latest('updated_at')->get()
                ->filter(fn ($t) => $t->jobPosting)
                ->map(fn ($t) => ['job_id' => $t->job_posting_id, 'vaga' => $t->jobPosting->title, 'empresa' => $t->jobPosting->company, 'atualizado_em' => $t->updated_at->toDateTimeString()])
                ->values(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
