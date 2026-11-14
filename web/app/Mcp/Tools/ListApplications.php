<?php

namespace App\Mcp\Tools;

use App\Models\JobApplication;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista as candidaturas do usuário, com dias sem retorno e alerta de follow-up (follow_up após 15 dias, no_response após 30).')]
class ListApplications extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['status' => ['nullable', 'string']]);

        $apps = $request->user()->jobApplications()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('applied_at')->get();

        return Response::json($apps->map(fn (JobApplication $a): array => [
            ...$a->only(['id', 'job_posting_id', 'title', 'company', 'url', 'status', 'next_step', 'notes']),
            'applied_at' => $a->applied_at->toDateString(),
            'last_contact_at' => $a->last_contact_at?->toDateString(),
            'days_without_response' => $a->daysWithoutResponse(),
            'alert' => $a->alert(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['status' => $schema->string()->enum(array_keys(JobApplication::STATUSES))];
    }
}
