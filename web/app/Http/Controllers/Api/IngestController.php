<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JobIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoint usado pelo coletor Python (GitHub Actions) para enviar vagas.
 */
class IngestController extends Controller
{
    public function __invoke(Request $request, JobIngestor $ingestor): JsonResponse
    {
        $token = (string) config('services.ingest.token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 401);

        $request->validate(['jobs' => ['required', 'array', 'max:500']]);

        $rules = [
            'source' => ['required', 'string', 'max:40'],
            'url' => ['required', 'url', 'max:1000'],
            'title' => ['required', 'string', 'max:500'],
            'company' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'remote' => ['nullable', 'boolean'],
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
}
