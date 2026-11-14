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
        usando os MESMOS termos da vaga (o ATS procura a palavra literal).
        OBJETIVO: deve parecer escrito pela própria pessoa, nunca por IA. NÃO cite o nome da empresa,
        o título exato da vaga nem expressões como "esta vaga" ou "nesta empresa".
        "cargo" = cargo e nível genéricos separados por " | " (ex.: "Desenvolvedor Júnior | Back-end PHP").
        "texto" = uma ou duas frases no estilo: "Busco oportunidade como [cargo] na área de [área], onde posso
        aplicar e expandir meus conhecimentos em [habilidades que a pessoa já tem], contribuindo com resultados para a equipe."
        Para cada palavra-chave da vaga que NÃO aparece no currículo ({$missing}), coloque um aviso
        [[ADICIONE: ...]] no lugar certo, dizendo o que a pessoa deve escrever SE tiver essa experiência.
        Mantenha no JSON todo o conteúdo real da pessoa (ela decide o que tirar) e inclua também a chave
        "recomendacoes": lista de até 8 itens {"tipo":"remover|adicionar|ajustar","trecho":"","motivo":""}.
        Avalie CADA experiência, atividade, curso, projeto e habilidade em relação ao que a vaga pede:
        - "remover": só quando o item NÃO tem nenhum valor para esta vaga, nem transferível (ex.: curso de
          violão numa vaga de suporte; atividade de vendas sem relação com tecnologia). "trecho" = texto EXATO do item.
        - "ajustar": quando o item é de outra área mas tem algo aproveitável (ex.: dev indo para suporte →
          destacar atendimento a usuários, resolução de problemas, Linux/redes). Diga como reescrever.
        - "adicionar": palavra-chave ou informação que a vaga pede e a pessoa pode ter.
        Na dúvida entre remover e ajustar, escolha AJUSTAR: tempo de experiência e soft skills quase sempre contam.
        "motivo" em uma frase curta citando o que a vaga pede.

        === VAGA ===
        {$job->title} — {$job->company}
        {$job->description}

        === CURRÍCULO ATUAL ===
        {$resume->plainText()}
        TXT;
    }

    /**
     * Chat do editor: a pessoa pede uma mudança ("adicione que usei Docker na empresa X") ou uma dúvida
     * ("que melhorias você sugere?"). A IA responde e, se for mudança, devolve o documento inteiro atualizado.
     *
     * @return array{data: array{resposta?: string, html?: ?string}, provider: string}
     */
    public function assistant(string $html, string $message, ?JobPosting $job = null): array
    {
        $vaga = $job ? "\n=== VAGA ALVO DESTE CURRÍCULO ===\n{$job->title}\n".mb_substr((string) $job->description, 0, 6000)."\n" : '';

        return $this->json(<<<TXT
        Você é um assistente de currículos ATS no Brasil, dentro de um editor de texto. Responda SOMENTE com JSON:
        {"resposta": "mensagem curta e amigável em português para a pessoa", "html": "documento COMPLETO atualizado, ou null"}
        - Se a pessoa pediu para alterar/adicionar/remover algo, aplique no documento e devolva o HTML inteiro em "html".
          Use só as tags h1, h2, p, ul, li, strong, em, mark, br e mantenha a estrutura (h1 = nome, h2 = seções).
        - Se ela só perguntou (ex.: "que melhorias você sugere?", "o que falta para a vaga?"), responda em "resposta"
          com uma lista curta e objetiva e deixe "html": null.
        - NUNCA invente experiências, datas, empresas ou tecnologias: use apenas o que já está no documento
          ou o que a pessoa escreveu na mensagem. Se faltar um dado, pergunte em "resposta".
        - Em "resposta", fale como um recrutador conversando: nunca mencione HTML, tags, h1/h2 ou código.
        {$vaga}
        === DOCUMENTO ATUAL (HTML) ===
        {$html}

        === PEDIDO DA PESSOA ===
        {$message}
        TXT, 'resposta');
    }

    /** Melhorias gerais para o currículo principal (sem vaga específica). @return array{data: array<string, mixed>, provider: string} */
    public function suggest(Resume $resume): array
    {
        return $this->json(<<<TXT
        Você é recrutador e especialista em ATS no Brasil. Analise o currículo abaixo e responda SOMENTE com JSON:
        {"recomendacoes": [{"tipo": "remover|adicionar|ajustar", "trecho": "", "motivo": ""}]}
        Até 8 itens, os mais importantes primeiro. Este é o currículo PRINCIPAL (serve para várias vagas de TI),
        então quase tudo de tecnologia deve ficar. Prefira "ajustar" e "adicionar".
        - "remover": RARO. Só para item repetido, dado pessoal que não deve estar em currículo (CPF, RG, estado civil,
          idade, foto) ou item sem relação nenhuma com trabalho/TI. NUNCA sugira remover cidade/estado, contato,
          tecnologias, ferramentas (inclusive de IA), conceitos técnicos, cursos ou projetos de TI.
          "trecho" = texto EXATO do item.
        - "ajustar": como reescrever algo para ficar mais forte (verbo de ação, resultado, número, palavra-chave).
        - "adicionar": seção ou informação que falta (ex.: resumo, links de projetos, métricas).
        "motivo" em uma frase curta. Não invente fatos sobre a pessoa.

        === CURRÍCULO ===
        {$resume->plainText()}
        TXT, 'recomendacoes');
    }

    /** @return array{data: array<string, mixed>, provider: string} */
    private function json(string $prompt, string $requiredKey = 'nome'): array
    {
        foreach (['groq', 'gemini', 'openrouter'] as $provider) {
            if (! config("services.$provider.key")) {
                continue;
            }
            try {
                $raw = $this->$provider($prompt);
                $data = json_decode(trim(preg_replace('/^```(json)?|```$/m', '', $raw)), true);
                if (is_array($data) && array_key_exists($requiredKey, $data)) {
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
