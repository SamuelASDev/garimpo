<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Arruma descrições de vaga que chegam "grudadas" (sites que tiram as quebras de linha) ou em Markdown cru,
 * e gera HTML seguro para exibir com títulos, listas e negrito.
 */
class JobDescriptionFormatter
{
    /** Títulos de seção comuns em vagas brasileiras (os mais longos primeiro para casar antes). */
    public const SECTIONS = [
        'Requisitos obrigatórios', 'Requisitos desejáveis', 'Requisitos e qualificações', 'Responsabilidades e atribuições',
        'Atividades e Responsabilidades', 'Principais atividades', 'Principais responsabilidades', 'Competências esperadas',
        'Competências comportamentais', 'Informações da vaga', 'Informações Adicionais', 'Informações adicionais',
        'Descrição da empresa', 'Descrição da vaga', 'Sobre a empresa', 'Sobre a vaga', 'Sobre nosso cliente', 'Sobre você',
        'O que oferecemos', 'O que nós oferecemos', 'O que você vai fazer', 'Outros requisitos', 'Pré-requisitos',
        'Qualificações', 'Atribuições', 'Atividades', 'Responsabilidades', 'Requisitos', 'Diferenciais', 'Desejáveis',
        'Benefícios', 'Idiomas', 'Etapas do processo',
    ];

    /** Rótulos de informação curta ("Salário: R$ 4.000") que ficam com o rótulo em negrito. */
    private const LABELS = '(?:Salário|Horário|Local de trabalho|Local de atuação|Formato de trabalho|Tipo de contrato|Jornada de trabalho|Regime|Modalidade|Carga horária)';

    /** Texto puro arrumado: seções em linhas próprias e itens separados. Usado ao salvar a vaga. */
    /** Palavras de tecnologia em "CamelCase" que não devem ser separadas. */
    private const CAMEL_WORDS = ['powershell', 'powerpoint', 'powerapps', 'powerautomate', 'sharepoint', 'elasticsearch',
        'onedrive', 'stackoverflow', 'cloudformation', 'codeigniter', 'clickup', 'salesforce', 'microstrategy'];

    public static function normalize(string $text): string
    {
        // Entidades (&nbsp; &amp;) que alguns sites mandam sem converter.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);

        // "LaboratorialPrincipais atividades:Elaborar" / "atribuiçõesAtuar" -> seção em linha própria.
        $sections = implode('|', array_map(fn (string $s): string => preg_quote($s, '~'), self::SECTIONS));
        // Aceita "Requisitos:", "**Requisitos:**", "**Requisitos**:" e "## Requisitos:".
        $text = preg_replace('~[ \t]*(?<![\w-])(?:#{1,4}\s*)?\*{0,2}('.$sections.')(?::\*{0,2}|\*{0,2}\s*:)\s*~u', "\n\n$1:\n", $text);
        $text = preg_replace('~(?<=[a-zà-ú\)\.;:])('.$sections.')\s*:\s*~u', "\n\n$1:\n", $text);
        $text = preg_replace('~(?<=[a-zà-ú\)\.;:]|^)('.$sections.')(?=[A-ZÀ-Ú][a-zà-ú])~um', "\n\n$1:\n", $text);

        // Palavras coladas no meio do texto ("estratégicoConhecimentos"), sem quebrar PowerShell, SharePoint...
        $text = preg_replace_callback('~(\p{Ll}{4,})(\p{Lu}\p{Ll}{3,})~u', function (array $m): string {
            $joint = mb_strtolower(mb_substr($m[1], -4).$m[2]);
            foreach (self::CAMEL_WORDS as $word) {
                if (str_contains($word, $joint)) {
                    return $m[0];
                }
            }

            return $m[1]."\n".$m[2];
        }, $text);

        // Links colados no texto ou um no outro.
        $text = preg_replace('~(?<=\S)(https?://)~u', ' $1', $text);

        // Rótulos curtos colados ("17h.Local de trabalho:") em linha própria.
        $text = preg_replace('~(?<=\S)\s*('.self::LABELS.')\s*:\s*~u', "\n$1: ", $text);

        // Itens colados: "...laboratório;Preparar" e "...(QD).Apoiar".
        $text = preg_replace('~;\s*(?=[A-ZÀ-Ú])~u', ";\n", $text);
        $text = preg_replace('~(?<=[a-zà-ú\)])\.(?=[A-ZÀ-Ú][a-zà-ú])~u', ".\n", $text);

        // Listas com hífen na mesma linha: "...estabelecidos;- Realizar" / ": - Realizar".
        $text = preg_replace('~(?<=[;:.])\s*-\s+(?=\S)~u', "\n- ", $text);

        // Dados pessoais de consultores (Michael Page e afins): "Contato\nNome Sobrenome" e "Nome do consultor Fulano".
        $text = preg_replace('~^\s*Contato\s*\n\s*\p{Lu}[\p{L}\'-]+(?:\s+\p{Lu}[\p{L}\'-]+){0,4}\s*$~mu', '', $text);
        $text = preg_replace('~\s*Nome do consultor\s+\p{Lu}[\p{L}\'-]+(?:\s+(?!Refer)\p{Lu}[\p{L}\'-]+){0,3}~u', '', $text);
        $text = preg_replace('~\s*Refer[eê]ncia da vaga\s*:?\s*[A-Z]{2,4}-[\w-]+~iu', '', $text);

        // Limpeza final: espaços nas pontas, textos de botões/links e no máximo uma linha em branco seguida.
        $lines = array_map('trim', explode("\n", $text));
        $lines = array_map(fn (string $l): string => self::isUiNoise($l) ? '' : $l, $lines);

        return trim(preg_replace("~\n{3,}~", "\n\n", implode("\n", $lines)));
    }

    /** HTML seguro para exibir (Markdown sem HTML cru, sem links inseguros). */
    public static function toHtml(string $text): string
    {
        $md = [];
        foreach (explode("\n", self::normalize($text)) as $line) {
            $plain = trim($line, " *#");
            if ($line === '') {
                $md[] = '';
            } elseif (preg_match('~^#{1,6}\s+(.+)$~u', $line, $m) || self::isSection($plain)) {
                $md[] = '';
                $md[] = '#### '.rtrim(trim($m[1] ?? $plain, ' *'), ':');
                $md[] = '';
            } elseif (preg_match('~^(?:[-*•·]|\d+[.)])\s+(.+)$~u', $line, $m)) {
                $md[] = '- '.rtrim($m[1], ';');
            } elseif (str_ends_with($line, ';')) {
                $md[] = '- '.rtrim($line, ';');
            } else {
                // Linha comum logo após uma lista: separa, senão o Markdown a junta ao último item.
                if (str_starts_with((string) end($md), '- ')) {
                    $md[] = '';
                }
                $md[] = preg_match('~^('.self::LABELS.'):\s*(.+)$~u', $line, $m) ? '**'.$m[1].':** '.$m[2] : $line;
                $md[] = '';
            }
        }

        return Str::markdown(implode("\n", $md), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /** Textos de botões e links que os sites deixam no meio da descrição. */
    private const UI_NOISE = [
        'salvar vaga', 'salvar', 'candidate-se', 'candidatar-se', 'candidate-se agora', 'candidatar', 'quero me candidatar',
        'aplicar', 'aplicar agora', 'inscreva-se', 'inscrever-se', 'enviar currículo', 'enviar curriculo', 'compartilhar',
        'compartilhar vaga', 'compartilhe esta vaga', 'compartilhe', 'voltar', 'voltar à pesquisa', 'voltar a pesquisa',
        'voltar para as vagas', 'ver mais', 'ver menos', 'leia mais', 'mostrar mais', 'mostrar menos', 'ver vaga',
        'ver detalhes', 'mais detalhes', 'denunciar vaga', 'denunciar', 'imprimir', 'copiar link', 'favoritar',
        'seguir empresa', 'seguir', 'vagas similares', 'empregos similares', 'referência da vaga', 'referencia da vaga',
        'apply', 'apply now', 'save job', 'share',
    ];

    private static function isUiNoise(string $line): bool
    {
        $plain = mb_strtolower(trim($line, " \t*-•·#>|"));

        return $plain === ''
            || in_array($plain, self::UI_NOISE, true)
            || preg_match('~^[?!.:;,•·*\-–—]+$~u', $plain)          // pontuação ou marcador sozinho
            || preg_match('~^(#\w+\s*)+$~u', trim($line))          // só hashtags (#VagaUrgente)
            || preg_match('~^jn-\d{6}-\d+$~i', $plain);             // código de referência sozinho
    }

    private static function isSection(string $line): bool
    {
        $line = rtrim($line, ': ');

        return in_array(mb_strtolower($line), array_map('mb_strtolower', self::SECTIONS), true);
    }
}
