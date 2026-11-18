<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\ResumeTailoring;
use App\Models\ResumeVariant;
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
            $result = $writer->suggest($resume, $this->pages($request));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $resume->update(['recommendations' => ResumeTemplate::recommendations($result['data']['recomendacoes'] ?? [])]);

        return response()->json(['recommendations' => $resume->recommendations]);
    }

    /** Sugestões para o currículo adaptado a uma vaga (cargo alvo, objetivo, o que tirar e o que ajustar). */
    public function jobSuggestions(Request $request, JobPosting $job, AiWriter $writer): JsonResponse
    {
        $job = $job->forUser($request->user());
        $tailoring = ResumeTailoring::where('user_id', $request->user()->id)->where('job_posting_id', $job->id)->first();
        if (! $tailoring) {
            return response()->json(['message' => 'Gere o currículo para esta vaga primeiro.'], 422);
        }
        if ($limit = $this->limitReached($request)) {
            return $limit;
        }

        try {
            $result = $writer->suggestForJob(new Resume(['html' => $tailoring->content]), $job, $this->pages($request));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $tailoring->update(['recommendations' => ResumeTemplate::recommendations($result['data']['recomendacoes'] ?? [])]);

        return response()->json(['recommendations' => $tailoring->recommendations]);
    }

    /** Chat no editor de uma variação: a IA sabe qual é o foco dela. */
    public function variantChat(Request $request, ResumeVariant $variant, AiWriter $writer): JsonResponse
    {
        abort_unless($variant->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'html' => ['required', 'string', 'max:200000'],
        ]);
        if ($limit = $this->limitReached($request)) {
            return $limit;
        }

        try {
            $result = $writer->assistant($data['html'], $data['message'], $variant->asTarget());
        } catch (\RuntimeException $e) {
            return response()->json(['reply' => $e->getMessage(), 'html' => null], 503);
        }

        $html = $result['data']['html'] ?? null;

        return response()->json([
            'reply' => (string) ($result['data']['resposta'] ?? 'Pronto!'),
            'html' => is_string($html) && trim($html) !== '' ? ResumeTemplate::sanitize($html) : null,
        ]);
    }

    /** Sugestões para uma variação: compara com o foco (nome, categoria e descrição) que a pessoa deu. */
    public function variantSuggestions(Request $request, ResumeVariant $variant, AiWriter $writer): JsonResponse
    {
        abort_unless($variant->user_id === $request->user()->id, 404);
        if ($limit = $this->limitReached($request)) {
            return $limit;
        }

        try {
            $result = $writer->suggestForJob(new Resume(['html' => $variant->html]), $variant->asTarget(), $this->pages($request));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $variant->update(['recommendations' => ResumeTemplate::recommendations($result['data']['recomendacoes'] ?? [])]);

        return response()->json(['recommendations' => $variant->recommendations]);
    }

    /** Páginas que o currículo ocupa no editor (1 a 5). */
    private function pages(Request $request): int
    {
        return max(1, min(5, $request->integer('pages', 1)));
    }

    private function limitReached(Request $request): ?JsonResponse
    {
        if (! $request->user()->hasAiConsent()) {
            return response()->json(['reply' => 'Você ainda não autorizou a IA grátis. Ative em Minha conta → Privacidade (seu currículo vai sem telefone, e-mail e links).', 'message' => 'Você ainda não autorizou a IA grátis. Ative em Minha conta → Privacidade (seu currículo vai sem telefone, e-mail e links).', 'html' => null], 403);
        }

        $key = 'assistant:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, self::DAILY_MESSAGES)) {
            return response()->json([
                'reply' => 'Você usou as '.self::DAILY_MESSAGES.' mensagens grátis de hoje. Conecte sua própria IA (menu Seção dev) para continuar sem limite.',
                'message' => 'Limite diário da IA grátis atingido.',
                'html' => null,
            ], 429);
        }
        RateLimiter::hit($key, 86400);

        return null;
    }
}
