<?php

namespace App\Mcp\Tools;

use App\Models\JobPosting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Registra uma candidatura que o usuário CONFIRMOU ter enviado. Use job_id para vagas do Garimpo, ou title/company/url para vagas de outros sites.')]
class AddApplication extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'job_id' => ['nullable', 'integer'],
            'title' => ['required_without:job_id', 'nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $user = $request->user();

        if ($data['job_id'] ?? null) {
            $job = JobPosting::find($data['job_id']);
            if (! $job) {
                return Response::error('Vaga não encontrada.');
            }
            $app = $user->jobApplications()->firstOrCreate(
                ['job_posting_id' => $job->id],
                ['title' => $job->title, 'company' => $job->company, 'url' => $job->url, 'applied_at' => today(), 'notes' => $data['notes'] ?? null],
            );
        } else {
            $app = $user->jobApplications()->create([
                'title' => $data['title'], 'company' => $data['company'] ?? null, 'url' => $data['url'] ?? null,
                'notes' => $data['notes'] ?? null, 'applied_at' => today(),
            ]);
        }

        return Response::text("Candidatura #{$app->id} registrada ({$app->title}).");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->description('ID da vaga no Garimpo'),
            'title' => $schema->string()->description('Cargo (se a vaga não for do Garimpo)'),
            'company' => $schema->string(),
            'url' => $schema->string()->description('Link da vaga'),
            'notes' => $schema->string(),
        ];
    }
}
