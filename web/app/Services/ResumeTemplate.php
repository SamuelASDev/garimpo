<?php

namespace App\Services;

/**
 * Monta o HTML do currículo (modelo ATS de uma coluna) a partir de dados estruturados.
 * O HTML usa só elementos que o editor entende: h1, h2, p, ul/li, strong, em, mark.
 * Trechos "[[ADICIONE: ...]]" viram marca-texto amarelo, avisando o que o usuário precisa preencher.
 */
class ResumeTemplate
{
    /** Estrutura que a IA deve devolver (também serve de exemplo para o modelo em branco). */
    public const EXAMPLE = [
        'nome' => '(SEU NOME)',
        'contato' => 'Sua Cidade - UF | (11) 00000-0000 | seuemail@gmail.com',
        'links' => 'linkedin.com/in/seulinkedin | github.com/seugithub',
        'objetivo' => ['cargo' => 'Desenvolvedor Júnior | Estágio', 'texto' => 'Estou em busca de oportunidades como [cargo] na área de [área de atuação], onde posso aplicar e expandir meus conhecimentos em [habilidade 1] e [habilidade 2], contribuindo com resultados sólidos para a equipe.'],
        'formacao' => [['curso' => 'Análise e Desenvolvimento de Sistemas', 'instituicao' => 'Nome da instituição', 'periodo' => 'Previsão de término: Mês/Ano']],
        'experiencias' => [['cargo' => 'Cargo', 'empresa' => 'Nome da Empresa', 'periodo' => 'Mês/Ano – Mês/Ano', 'atividades' => [
            'Descreva sua principal responsabilidade e quantifique o impacto sempre que possível.',
            'Detalhe uma conquista relevante, como melhoria de processos, redução de custos ou aumento de eficiência.',
            'Mencione ferramentas, sistemas ou metodologias utilizadas no dia a dia.',
        ]]],
        'cursos' => ['(Nome do certificado) — (Empresa emissora) — Mês/Ano'],
        'projetos' => [['nome' => 'Nome do Projeto', 'periodo' => 'Mês/Ano', 'descricao' => 'Breve descrição do projeto: objetivo, tecnologias usadas e resultado alcançado.']],
        'habilidades_tecnicas' => ['JavaScript', 'PHP', 'SQL', 'Git', '(adicione suas ferramentas)'],
        'idiomas' => ['Inglês (Intermediário)', 'Português (Nativo)'],
        'outras_habilidades' => 'Conhecimentos que não se encaixam nas categorias acima mas agregam valor ao seu perfil.',
        'habilidades_interpessoais' => [
            ['nome' => 'Responsabilidade', 'descricao' => 'Comprometimento em cumprir obrigações e assumir responsabilidades.'],
            ['nome' => 'Proatividade', 'descricao' => 'Iniciativa para identificar e abordar questões antes que se tornem problemas.'],
        ],
    ];

    /** @param  array<string, mixed>  $d */
    public function render(array $d): string
    {
        $h = [];
        $h[] = '<h1 style="text-align: center">'.$this->e($d['nome'] ?? '').'</h1>';
        $h[] = '<p style="text-align: center">'.$this->e($d['contato'] ?? '').'</p>';
        if (! empty($d['links'])) {
            $h[] = '<p style="text-align: center">'.$this->e($d['links']).'</p>';
        }

        if (! empty($d['objetivo']['texto']) || ! empty($d['objetivo']['cargo'])) {
            $h[] = '<h2>Objetivo</h2>';
            if (! empty($d['objetivo']['cargo'])) {
                $h[] = '<p><strong>Cargo alvo:</strong> '.$this->e($d['objetivo']['cargo']).'</p>';
            }
            $h[] = '<p>'.$this->e($d['objetivo']['texto'] ?? '').'</p>';
        }

        $this->section($h, 'Formação acadêmica', $d['formacao'] ?? [], fn (array $f): string => '<p><strong>'.$this->e($f['curso'] ?? '').'</strong>'
            .(empty($f['periodo']) ? '' : ' — <em>'.$this->e($f['periodo']).'</em>')
            .'<br>'.$this->e($f['instituicao'] ?? '').'</p>');

        $this->section($h, 'Experiência profissional', $d['experiencias'] ?? [], fn (array $x): string => '<p><strong>'.$this->e(trim(($x['cargo'] ?? '').' | '.($x['empresa'] ?? ''), ' |')).'</strong>'
            .(empty($x['periodo']) ? '' : ' — <em>'.$this->e($x['periodo']).'</em>').'</p>'
            .$this->list($x['atividades'] ?? []));

        if (! empty($d['cursos'])) {
            $h[] = '<h2>Cursos e certificações</h2>'.$this->list($d['cursos']);
        }

        $this->section($h, 'Projetos de destaque', $d['projetos'] ?? [], fn (array $p): string => '<p><strong>'.$this->e($p['nome'] ?? '').'</strong>'
            .(empty($p['periodo']) ? '' : ' — <em>'.$this->e($p['periodo']).'</em>')
            .'<br>'.$this->e($p['descricao'] ?? '').'</p>');

        if (! empty($d['habilidades_tecnicas'])) {
            $h[] = '<h2>Habilidades técnicas (tech skills)</h2><p>'.implode(' | ', array_map($this->e(...), $d['habilidades_tecnicas'])).'</p>';
        }
        if (! empty($d['idiomas'])) {
            $h[] = '<h2>Idiomas</h2>'.$this->list($d['idiomas']);
        }
        if (! empty($d['outras_habilidades'])) {
            $h[] = '<h2>Outras habilidades</h2><p>'.$this->e($d['outras_habilidades']).'</p>';
        }
        if (! empty($d['habilidades_interpessoais'])) {
            $h[] = '<h2>Habilidades interpessoais (soft skills)</h2>'.$this->list(array_map(
                fn ($s): string => is_array($s) ? '<strong>'.$this->e($s['nome'] ?? '').':</strong> '.$this->e($s['descricao'] ?? '') : $this->e($s),
                $d['habilidades_interpessoais'],
            ), escaped: true);
        }

        return implode("\n", $h);
    }

    /** Texto simples (ex.: PDF sem IA disponível) vira parágrafos no editor. */
    public function fromPlainText(string $text): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];

        return implode("\n", array_map(fn ($p): string => '<p>'.nl2br($this->e(trim($p)), false).'</p>', $paragraphs));
    }

    /**
     * Limpa as recomendações vindas de qualquer IA (grátis ou do usuário).
     *
     * @return list<array{tipo: string, trecho: string, motivo: string}>
     */
    public static function recommendations(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->filter(fn ($r) => is_array($r) && is_string($r['trecho'] ?? null) && trim($r['trecho']) !== '')
            ->reject(fn (array $r) => str_contains($r['trecho'], '[[')) // repete um aviso amarelo que já está no documento
            ->map(fn (array $r): array => [
                'tipo' => in_array($r['tipo'] ?? null, ['remover', 'adicionar', 'ajustar'], true) ? $r['tipo'] : 'ajustar',
                'trecho' => mb_substr(trim($r['trecho']), 0, 300),
                'motivo' => mb_substr((string) ($r['motivo'] ?? ''), 0, 300),
                'novo' => is_string($r['novo'] ?? null) && trim($r['novo']) !== '' && ! str_contains($r['novo'], '[[') ? mb_substr(trim($r['novo']), 0, 600) : null,
            ])
            ->take(10)->values()->all();
    }

    /**
     * Segurança: todo HTML que entra (navegador, IA, MCP) passa por aqui antes de ser salvo ou exportado.
     * Mantém só as tags do editor e, nos estilos, apenas alinhamento, cor e tamanho de fonte com valores seguros.
     * Remove scripts, eventos (onclick...), links, imagens, iframes e qualquer outro atributo.
     */
    public static function sanitize(string $html): string
    {
        $html = preg_replace('~<(script|style|iframe|object|embed|svg|math)\b.*?</\1>~is', '', $html);
        $html = strip_tags($html, '<h1><h2><h3><p><ul><ol><li><strong><em><u><s><mark><br><span>');

        return preg_replace_callback('~<(\w+)(\s[^>]*)?>~', function (array $m): string {
            $attrs = $m[2] ?? '';
            $style = preg_match('~\sstyle\s*=\s*"([^"]*)"~i', $attrs, $s) ? $s[1] : '';
            $safe = [];
            if (preg_match('~text-align:\s*(left|center|right|justify)~i', $style, $v)) {
                $safe[] = 'text-align: '.strtolower($v[1]);
            }
            if (preg_match('~(?<![-\w])color:\s*(#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))~i', $style, $v)) {
                $safe[] = 'color: '.$v[1];
            }
            if (preg_match('~font-size:\s*(\d{1,2}(?:\.\d)?(?:pt|px))~i', $style, $v)) {
                $safe[] = 'font-size: '.$v[1];
            }

            return '<'.strtolower($m[1]).($safe ? ' style="'.implode('; ', $safe).'"' : '').'>';
        }, $html);
    }

    /**
     * Cabeçalho (cidade, telefone, e-mail, links) = os parágrafos entre o nome (h1) e a primeira seção (h2).
     * O do currículo principal é a fonte da verdade: a IA nunca deve trocar esses dados por avisos.
     * Troca, linha a linha, as do $html pelas do $source quando a do $source está completa (sem aviso amarelo).
     */
    public static function withHeaderFrom(string $html, ?string $source): string
    {
        $lines = fn (string $doc): array => preg_match('~</h1>(.*?)(?=<h2|$)~is', $doc, $m)
            ? (preg_match_all('~<p\b[^>]*>.*?</p>~is', $m[1], $p) ? $p[0] : [])
            : [];
        $mine = $lines($html);
        $real = $source ? $lines($source) : [];
        if ($mine === [] || $real === []) {
            return $html;
        }

        $fixed = $mine;
        foreach ($real as $i => $line) {
            if (isset($fixed[$i]) && ! str_contains($line, '<mark') && ! str_contains($line, '[[')) {
                $fixed[$i] = $line;
            }
        }

        return preg_replace_callback('~</h1>(.*?)(?=<h2|$)~is', fn ($m) => '</h1>'.str_replace($mine, $fixed, $m[1]), $html, 1);
    }

    /** Lista os trechos marcados para o usuário completar. @return list<string> */
    public static function placeholders(string $html): array
    {
        preg_match_all('~<mark[^>]*>(.*?)</mark>~s', $html, $m);

        return array_values(array_unique(array_map(fn ($s) => trim(html_entity_decode(strip_tags($s))), $m[1])));
    }

    /** @param  list<string>  $h */
    private function section(array &$h, string $title, array $items, \Closure $render): void
    {
        $items = array_filter($items, 'is_array');
        if ($items !== []) {
            $h[] = '<h2>'.$title.'</h2>'.implode('', array_map($render, $items));
        }
    }

    private function list(array $items, bool $escaped = false): string
    {
        $items = array_filter($items, fn ($i) => is_string($i) && trim($i) !== '');

        return $items === [] ? '' : '<ul>'.implode('', array_map(fn ($i) => '<li><p>'.($escaped ? $i : $this->e($i)).'</p></li>', $items)).'</ul>';
    }

    /** Escapa HTML e transforma [[ADICIONE: ...]] em marca-texto. */
    private function e(mixed $value): string
    {
        $text = e(is_scalar($value) ? (string) $value : '');

        return preg_replace('~\[\[(.+?)\]\]~s', '<mark>$1</mark>', $text);
    }
}
