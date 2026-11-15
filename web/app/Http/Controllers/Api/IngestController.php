<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JobIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints usados pelo coletor Python (GitHub Actions) para enviar vagas.
 */
class IngestController extends Controller
{
    public function __invoke(Request $request, JobIngestor $ingestor): JsonResponse
    {
        $this->authorizeCollector($request);
        $request->validate(['jobs' => ['required', 'array', 'max:500']]);

        $rules = [
            'source' => ['required', 'string', 'max:40'],
            'url' => ['required', 'url', 'max:1000'],
            'title' => ['required', 'string', 'max:500'],
            'company' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'remote' => ['nullable', 'boolean'],
            'work_mode' => ['nullable', 'in:remoto,hibrido,presencial'],
            'description' => ['nullable', 'string', 'max:60000'],
            'posted_at' => ['nullable', 'date'],
        ];

        $valid = collect($request->input('jobs'))
            ->filter(fn ($item): bool => is_array($item) && Validator::make($item, $rules)->passes())
            ->values();

        return response()->json([
            ...$ingestor->ingest($valid->all()),
            'invalid' => count($request->input('jobs')) - $valid->count(),
        ]);
    }

    /**
     * Quais vagas ainda precisam da descrição completa (novas ou só com resumo).
     * Aceita `jobs: [{url, title}]` (filtra o que não é TI) ou o formato antigo `urls: [...]`.
     */
    public function pending(Request $request, JobIngestor $ingestor): JsonResponse
    {
        $this->authorizeCollector($request);
        $data = $request->validate([
            'jobs' => ['required_without:urls', 'array', 'max:3000'],
            'jobs.*.url' => ['required', 'string', 'max:1000'],
            'jobs.*.title' => ['required', 'string', 'max:500'],
            'jobs.*.company' => ['nullable', 'string', 'max:255'],
            'urls' => ['required_without:jobs', 'array', 'max:3000'],
            'urls.*' => ['string', 'max:1000'],
        ]);

        $input = isset($data['jobs']) ? array_column($data['jobs'], 'title', 'url') : $data['urls'];
        $companies = isset($data['jobs']) ? array_column($data['jobs'], 'company', 'url') : [];

        return response()->json(['urls' => $ingestor->pendingDescriptions($input, $companies)]);
    }

    private function authorizeCollector(Request $request): void
    {
        $token = (string) config('services.ingest.token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 401);
    }
}
