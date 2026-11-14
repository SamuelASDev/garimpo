<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Services\AiWriter;
use App\Services\ResumeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * IA grátis dentro do editor: chat que altera o currículo ou responde dúvidas, e sugestões de melhoria.
 * Quem conectou a própria IA faz o mesmo pelo MCP, sem esses limites.
 */
class ResumeAssistantController extends Controller
{
    private const DAILY_MESSAGES = 40;

    /** Chat no currículo principal (sem $job) ou no currículo de uma vaga. */
    public function chat(Request $request, AiWriter $writer, ?JobPosting $job = null): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'html' => ['required', 'string', 'max:200000'],
        ]);
        if ($job) {
            ResumeTailoring::where('user_id', $request->user()->id)->where('job_posting_id', $job->id)->firstOrFail();
        }
        if ($limit = $this->limitReached($request)) {
            return $limit;
        }

        try {
            $result = $writer->assistant($data['html'], $data['message'], $job?->forUser($request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['reply' => $e->getMessage(), 'html' => null], 503);
        }

        $html = $result['data']['html'] ?? null;

        return response()->json([
            'reply' => (string) ($result['data']['resposta'] ?? 'Pronto!'),
            'html' => is_string($html) && trim($html) !== '' ? ResumeTemplate::sanitize($html) : null,
        ]);
    }

    /** Sugestões de melhoria para o currículo principal (ficam salvas para aplicar depois). */
    public function suggestions(Request $request, AiWriter $writer): JsonResponse
    {
        $resume = $request->user()->resume;
        if (! $resume) {
            return response()->json(['message' => 'Salve o seu currículo principal primeiro.'], 422);
        }
        if ($limit = $this->limitReached($request)) {
            return $limit;
        }

        try {
            $result = $writer->suggest($resume);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $resume->update(['recommendations' => ResumeTemplate::recommendations($result['data']['recomendacoes'] ?? [])]);

        return response()->json(['recommendations' => $resume->recommendations]);
    }

    private function limitReached(Request $request): ?JsonResponse
    {
        if (! $request->user()->hasAiConsent()) {
            return response()->json(['reply' => 'Você ainda não autorizou a IA grátis. Ative em Minha conta → Privacidade (seu currículo vai sem telefone, e-mail e links).', 'message' => 'Você ainda não autorizou a IA grátis. Ative em Minha conta → Privacidade (seu currículo vai sem telefone, e-mail e links).', 'html' => null], 403);
        }

        $key = 'assistant:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, self::DAILY_MESSAGES)) {
            return response()->json([
                'reply' => 'Você usou as '.self::DAILY_MESSAGES.' mensagens grátis de hoje. Conecte sua própria IA (menu "Conectar IA") para continuar sem limite.',
                'message' => 'Limite diário da IA grátis atingido.',
                'html' => null,
            ], 429);
        }
        RateLimiter::hit($key, 86400);

        return null;
    }
}
