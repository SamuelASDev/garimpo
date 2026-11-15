<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Resume;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $softMissing = implode(', ', $match['soft_missing'] ?? []) ?: 'nenhuma';

        return self::RULES.<<<TXT


        Adapte o currículo abaixo para a vaga. Destaque primeiro o que a pessoa já tem e que a vaga pede,
        usando os MESMOS termos da vaga (o ATS procura a palavra literal).
        CARGO ALVO E OBJETIVO: reescreva SEMPRE para a área e o nível DESTA vaga, mesmo que o currículo original
        mire outro cargo. Ex.: currículo de "Desenvolvedor Júnior" e vaga de suporte → "Analista de Suporte Júnior | Suporte Técnico";
        currículo de suporte e vaga de dados → "Analista de Dados Júnior | BI". Nunca mantenha o cargo antigo se a área mudou.
        O objetivo deve parecer escrito pela própria pessoa, nunca por IA. NÃO cite o nome da empresa,
        o título exato da vaga nem expressões como "esta vaga" ou "nesta empresa".
        "cargo" = cargo e nível genéricos separados por " | " (ex.: "Desenvolvedor Júnior | Back-end PHP").
        Cargo alvo atual do currículo (troque se não for da área da vaga): {$this->currentTarget($resume)}.
        "texto" = uma ou duas frases no estilo: "Busco oportunidade como [cargo] na área de [área], onde posso
        aplicar e expandir meus conhecimentos em [habilidades que a pessoa já tem], contribuindo com resultados para a equipe."
        Leia a vaga inteira e identifique TUDO o que ela pede, não só linguagens: atividades (ex.: montagem e
        manutenção de computadores, atendimento presencial), conhecimentos (hardware, redes locais, nobreak),
        formação e requisitos. Pelo dicionário do site, faltam no currículo: {$missing}.
        Para cada requisito importante da vaga que NÃO aparece no currículo, coloque um aviso [[ADICIONE: ...]]
        no lugar certo (experiência, habilidades ou formação), em forma de orientação, ex.:
        "[[ADICIONE: se você já montou ou fez manutenção de computadores, descreva onde e o que fazia]]".
        Não repita esses avisos em "recomendacoes" (lá vão só remover/ajustar/adicionar sobre o conteúdo).
        Competências comportamentais pedidas e ainda não demonstradas: {$softMissing}. Se a pessoa já mostra isso
        de alguma forma, destaque; senão, sugira em [[ADICIONE: ...]] um exemplo real que ela poderia contar.
        Mantenha no JSON todo o conteúdo real da pessoa (ela decide o que tirar) e inclua também a chave
        "recomendacoes": {$this->recommendationRules('desta vaga', (int) ceil(mb_strlen($resume->plainText()) / 3000))}.

        === VAGA ===
        {$job->title} — {$job->company}
        {$job->description}

        === CURRÍCULO ATUAL ===
        {$resume->plainText()}
        TXT;
    }

    /** Como um currículo ATS deve ser escrito: a IA revisa o que já está lá com estas regras. */
    private const WRITING_RULES = <<<'TXT'
    Revise também a ESCRITA do que já está no currículo, item por item, pensando como recrutador e como ATS:
    o que faz sentido fica; o que está mal escrito, vago, longo ou confuso vira um "ajustar" com o "novo" reescrito.
    Regras de um bom currículo ATS: atividades começam com verbo de ação no passado ("Desenvolvi", "Implantei",
    "Reduzi") e dizem o resultado ou impacto (número quando a pessoa já informou); frases curtas e diretas, sem
    "responsável por" e sem primeira pessoa no meio das atividades; usar os mesmos termos da vaga/área (o ATS busca a
    palavra literal); tecnologias com a grafia oficial (JavaScript, Node.js, PostgreSQL); datas no formato Mês/Ano;
    sem repetir a mesma informação em seções diferentes; cada experiência com 2 a 5 tópicos objetivos.
    TXT;

    /** Cargo alvo que está hoje no currículo (para a IA saber o que trocar). */
    private function currentTarget(Resume $resume): string
    {
        return preg_match('~Cargo alvo:\s*(.+)~iu', $resume->plainText(), $m) ? trim($m[1]) : 'não informado';
    }

    /**
     * Currículo com mais de uma página: a IA sugere resumir para caber em uma,
     * mexendo primeiro no que menos importa e nunca no que é necessário.
     */
    private function onePageRule(int $pages): string
    {
        if ($pages <= 1) {
            return '';
        }

        return <<<TXT

        O currículo está ocupando {$pages} páginas; o ideal para TI é 1 página. Inclua sugestões para caber em uma:
        "ajustar" encurtando textos longos (objetivo, descrições de projetos, atividades repetitivas) com o "novo" já resumido,
        e "remover" itens antigos ou sem relação com o alvo. NUNCA sugira cortar o que é necessário: experiências
        profissionais, formação, tecnologias e requisitos que o alvo pede, contato e cargo alvo ficam (no máximo, resumidos).
        TXT;
    }

    /** Como a IA deve montar a lista de sugestões quando há uma vaga alvo. */
    private function recommendationRules(string $target, int $pages = 1): string
    {
        $onePage = $this->onePageRule($pages);
        $writing = self::WRITING_RULES;

        return <<<TXT
        lista de até 10 itens {"tipo":"remover|adicionar|ajustar","trecho":"","novo":"","motivo":""}, os mais importantes primeiro.
        Avalie CADA parte do currículo (cargo alvo, objetivo, experiências, atividades, cursos, projetos, habilidades técnicas,
        habilidades interpessoais) em relação ao que a vaga pede:
        - "ajustar" com "trecho" = texto EXATO que está hoje no currículo e "novo" = o texto reescrito, pronto para trocar.
          Use para: cargo alvo ou objetivo que não batem com a área {$target} (obrigatório se não baterem);
          atividade de outra área com algo aproveitável (ex.: dev indo para suporte → destacar atendimento a usuários,
          resolução de problemas, Linux/redes); frase fraca que pode usar os termos da vaga. Nunca invente fatos no "novo".
        - "remover": habilidade técnica, ferramenta, curso, projeto ou atividade que NÃO ajuda {$target}, nem como
          conhecimento transferível (ex.: Photoshop numa vaga de back-end; curso de violão numa vaga de suporte).
          Ajuda a deixar o currículo focado. "trecho" = texto EXATO do item. Não remova experiência profissional inteira:
          para ela, prefira "ajustar".
        - "adicionar": palavra-chave ou informação que a vaga pede e a pessoa pode ter ("novo" vazio).
        "motivo" em uma frase curta citando o que a vaga pede.
        {$writing}{$onePage}
        TXT;
    }

    /**
     * Sugestões para um currículo já adaptado ("Analisar de novo" na página da vaga).
     *
     * @return array{data: array<string, mixed>, provider: string}
     */
    public function suggestForJob(Resume $draft, JobPosting $job, int $pages = 1): array
    {
        $rules = $this->recommendationRules('desta vaga', $pages);

        return $this->json(<<<TXT
        Você é recrutador e especialista em ATS no Brasil. Compare o currículo com a vaga e responda SOMENTE com JSON:
        {"recomendacoes": [...]}, onde "recomendacoes" é uma {$rules}
        Cargo alvo atual: {$this->currentTarget($draft)}. Se não for da área da vaga, a primeira sugestão é "ajustar" o cargo alvo
        e a segunda, o objetivo. Não repita avisos [[ADICIONE: ...]] que já estão no currículo.

        === VAGA ===
        {$job->title}
        {$job->description}

        === CURRÍCULO ===
        {$draft->plainText()}
        TXT, 'recomendacoes');
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
    public function suggest(Resume $resume, int $pages = 1): array
    {
        $onePage = str_replace(['o alvo pede', 'com o alvo'], ['as vagas de TI pedem', 'com TI'], $this->onePageRule($pages));
        $writing = self::WRITING_RULES;

        return $this->json(<<<TXT
        Você é recrutador e especialista em ATS no Brasil. Analise o currículo abaixo e responda SOMENTE com JSON:
        {"recomendacoes": [{"tipo": "remover|adicionar|ajustar", "trecho": "", "novo": "", "motivo": ""}]}
        Até 8 itens, os mais importantes primeiro. Este é o currículo PRINCIPAL (serve para várias vagas de TI),
        então quase tudo de tecnologia deve ficar. Prefira "ajustar" e "adicionar".
        - "remover": RARO. Só para item repetido, dado pessoal que não deve estar em currículo (CPF, RG, estado civil,
          idade, foto) ou item sem relação nenhuma com trabalho/TI. NUNCA sugira remover cidade/estado, contato,
          tecnologias, ferramentas (inclusive de IA), conceitos técnicos, cursos ou projetos de TI.
          "trecho" = texto EXATO do item.
        - "ajustar": "trecho" = texto EXATO de hoje e "novo" = o texto reescrito mais forte (verbo de ação, resultado,
          número, palavra-chave), sem inventar fatos.
        - "adicionar": seção ou informação que falta (ex.: resumo, links de projetos, métricas).
        "motivo" em uma frase curta. Não invente fatos sobre a pessoa.
        {$writing}{$onePage}

        === CURRÍCULO ===
        {$resume->plainText()}
        TXT, 'recomendacoes');
    }

    /** @return array{data: array<string, mixed>, provider: string} */
    private function json(string $prompt, string $requiredKey = 'nome'): array
    {
        // LGPD: telefone, e-mail, CPF e links de perfil não saem do Garimpo; voltam só na resposta.
        $masker = new ContactMasker;
        $prompt = $masker->mask($prompt);

        // Gemini por último: a camada grátis do Google pode usar o conteúdo para treinar modelos.
        foreach (['groq', 'openrouter', 'gemini'] as $provider) {
            if (! config("services.$provider.key")) {
                continue;
            }
            try {
                $raw = $this->$provider($prompt);
                $data = json_decode(trim(preg_replace('/^```(json)?|```$/m', '', $raw)), true);
                if (is_array($data) && array_key_exists($requiredKey, $data)) {
                    return ['data' => $masker->unmask($data), 'provider' => $provider];
                }
                Log::warning("IA grátis: $provider respondeu fora do formato esperado", ['campo' => $requiredKey]);
            } catch (\Throwable $e) {
                // Só o motivo (status/mensagem curta): o texto do currículo não vai para o log.
                Log::warning("IA grátis: $provider falhou", ['erro' => mb_substr($e->getMessage(), 0, 300)]);
            }
        }

        Log::error('IA grátis: todos os provedores falharam', ['campo' => $requiredKey]);

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
