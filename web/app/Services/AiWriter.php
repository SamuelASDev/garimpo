<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Resume;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * IA grátis do site (Groq → Gemini → OpenRouter). Sempre pede JSON no formato do ResumeTemplate,
 * para o currículo sair no modelo padrão do Garimpo.
 */
class AiWriter
{
    private const RULES = <<<'TXT'
    Você é especialista em currículos para sistemas ATS no Brasil. Responda SOMENTE com um objeto JSON válido.
    Formato (mesmas chaves, em português; omita seções sem conteúdo):
    {"nome":"","contato":"cidade - UF | telefone | email","links":"linkedin | github","objetivo":{"cargo":"","texto":""},
     "formacao":[{"curso":"","instituicao":"","periodo":""}],
     "experiencias":[{"cargo":"","empresa":"","periodo":"Mês/Ano – Mês/Ano","atividades":["verbo no passado + resultado"]}],
     "cursos":["Nome — Emissor — Mês/Ano"],"projetos":[{"nome":"","periodo":"","descricao":""}],
     "habilidades_tecnicas":[""],"idiomas":["Inglês (Intermediário)"],"outras_habilidades":"",
     "habilidades_interpessoais":[{"nome":"","descricao":""}]}
    Regras: NUNCA invente experiências, empresas, datas, cursos ou tecnologias. No objetivo e nas habilidades,
    cite SOMENTE tecnologias que já aparecem no currículo original (as que faltam vão em [[ADICIONE: ...]]).
    Quando faltar uma informação importante, escreva um aviso entre colchetes duplos, ex.:
    "[[ADICIONE: seu telefone]]" ou "[[ADICIONE: se você já usou Docker, cite um projeto]]".
    TXT;

    /** Organiza o texto de um currículo importado (PDF) no modelo do site. @return array{data: array<string, mixed>, provider: string} */
    public function structureImported(string $text): array
    {
        return $this->json(self::RULES."\n\nOrganize o currículo abaixo no formato acima, corrigindo português e deixando as atividades objetivas.\n\n=== CURRÍCULO ===\n".mb_substr($text, 0, 20000));
    }

    /** Adapta o currículo para a vaga. @return array{data: array<string, mixed>, provider: string} */
    public function tailor(Resume $resume, JobPosting $job, array $match): array
    {
        return $this->json($this->prompt($resume, $job, $match));
    }

    /** Prompt da adaptação (também entregue à IA do usuário pelo MCP). */
    public function prompt(Resume $resume, JobPosting $job, array $match): string
    {
        $missing = implode(', ', $match['missing']) ?: 'nenhuma';

        return self::RULES.<<<TXT


        Adapte o currículo abaixo para a vaga. Destaque primeiro o que a pessoa já tem e que a vaga pede,
        usando os MESMOS termos da vaga (o ATS procura a palavra literal). Ajuste o objetivo para o cargo da vaga.
        Para cada palavra-chave da vaga que NÃO aparece no currículo ({$missing}), coloque um aviso
        [[ADICIONE: ...]] no lugar certo, dizendo o que a pessoa deve escrever SE tiver essa experiência.
        Mantenha no JSON todo o conteúdo real da pessoa (ela decide o que tirar) e inclua também a chave
        "recomendacoes": lista de até 8 itens {"tipo":"remover|adicionar|ajustar","trecho":"","motivo":""}.
        Em "remover", "trecho" deve ser o texto EXATO de uma atividade, curso, projeto ou habilidade do JSON
        que não ajuda nesta vaga (ex.: experiência de outra área, curso irrelevante). Em "adicionar"/"ajustar",
        "trecho" é a sugestão. "motivo" em uma frase curta.

        === VAGA ===
        {$job->title} — {$job->company}
        {$job->description}

        === CURRÍCULO ATUAL ===
        {$resume->plainText()}
        TXT;
    }

    /** @return array{data: array<string, mixed>, provider: string} */
    private function json(string $prompt): array
    {
        foreach (['groq', 'gemini', 'openrouter'] as $provider) {
            if (! config("services.$provider.key")) {
                continue;
            }
            try {
                $raw = $this->$provider($prompt);
                $data = json_decode(trim(preg_replace('/^```(json)?|```$/m', '', $raw)), true);
                if (is_array($data) && isset($data['nome'])) {
                    return ['data' => $data, 'provider' => $provider];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        throw new RuntimeException('A IA grátis está indisponível agora. Tente de novo em alguns minutos ou use sua própria IA (menu "Conectar IA").');
    }

    private function gemini(string $prompt): string
    {
        $model = config('services.gemini.model');

        return Http::timeout(45)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ])->throw()->json('candidates.0.content.parts.0.text') ?? throw new RuntimeException('Gemini sem resposta');
    }

    private function groq(string $prompt): string
    {
        return $this->openAiCompatible('https://api.groq.com/openai/v1/chat/completions', 'groq', $prompt);
    }

    private function openrouter(string $prompt): string
    {
        return $this->openAiCompatible('https://openrouter.ai/api/v1/chat/completions', 'openrouter', $prompt);
    }

    /** Groq e OpenRouter usam o mesmo formato de API (compatível com OpenAI). */
    private function openAiCompatible(string $url, string $provider, string $prompt): string
    {
        return Http::timeout(45)
            ->withToken(config("services.$provider.key"))
            ->post($url, [
                'model' => config("services.$provider.model"),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
            ])->throw()->json('choices.0.message.content') ?? throw new RuntimeException("$provider sem resposta");
    }
}
