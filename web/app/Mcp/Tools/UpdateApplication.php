<?php

namespace App\Mcp\Tools;

use App\Models\JobApplication;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Atualiza status, próximo passo ou observações de uma candidatura (ex.: empresa chamou para entrevista).')]
class UpdateApplication extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'application_id' => ['required', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(JobApplication::STATUSES))],
            'next_step' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $app = $request->user()->jobApplications()->find($data['application_id']);
        if (! $app) {
            return Response::error('Candidatura não encontrada.');
        }

        $changes = array_filter($data, fn ($v, $k) => $k !== 'application_id' && $v !== null, ARRAY_FILTER_USE_BOTH);
        $changes['last_contact_at'] = today();
        $app->update($changes);

        return Response::text("Candidatura #{$app->id} atualizada: ".JobApplication::STATUSES[$app->status].'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'application_id' => $schema->integer()->required(),
            'status' => $schema->string()->enum(array_keys(JobApplication::STATUSES)),
            'next_step' => $schema->string()->description('Ex.: entrevista 05/12 às 14h'),
            'notes' => $schema->string(),
        ];
    }
}
