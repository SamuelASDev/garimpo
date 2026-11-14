<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Resume;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gera o currículo adaptado usando provedores com camada grátis (Gemini, Groq).
 * Tenta cada provedor configurado em ordem até um responder.
 */
class AiWriter
{
    /** @return array{content: string, provider: string} */
    public function tailor(Resume $resume, JobPosting $job, array $match): array
    {
        $prompt = $this->prompt($resume, $job, $match);

        foreach (['gemini', 'groq'] as $provider) {
            if (! config("services.$provider.key")) {
                continue;
            }
            try {
                return ['content' => $this->$provider($prompt), 'provider' => $provider];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        throw new RuntimeException('Nenhuma IA disponível agora. Configure GEMINI_API_KEY ou GROQ_API_KEY, ou use sua própria IA pelo MCP.');
    }

    public function prompt(Resume $resume, JobPosting $job, array $match): string
    {
        $missing = implode(', ', $match['missing']) ?: 'nenhuma';

        return <<<TXT
        Você é especialista em currículos para sistemas ATS no Brasil.
        Reescreva o currículo abaixo para a vaga indicada, em português, formato texto simples de uma coluna,
        com as seções: Nome e contato, Título, Resumo, Competências, Experiência, Formação, Cursos e idiomas.
        Regras: NÃO invente experiências, empresas, datas ou tecnologias que a pessoa não citou.
        Use os mesmos termos da vaga quando a pessoa realmente tiver aquela experiência.
        No final, adicione a seção "O que falta para esta vaga" com sugestões honestas
        (palavras-chave ausentes: {$missing}).

        === VAGA ===
        {$job->title} — {$job->company}
        {$job->description}

        === CURRÍCULO ATUAL ===
        Nome: {$resume->full_name}
        Contato: {$resume->contact}
        Título: {$resume->headline}
        Resumo: {$resume->summary}
        Competências: {$resume->skills}
        Experiência: {$resume->experience}
        Formação: {$resume->education}
        Extras: {$resume->extras}
        TXT;
    }

    private function gemini(string $prompt): string
    {
        $model = config('services.gemini.model');

        return Http::timeout(60)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ])->throw()->json('candidates.0.content.parts.0.text') ?? throw new RuntimeException('Gemini sem resposta');
    }

    private function groq(string $prompt): string
    {
        return Http::timeout(60)
            ->withToken(config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ])->throw()->json('choices.0.message.content') ?? throw new RuntimeException('Groq sem resposta');
    }
}
