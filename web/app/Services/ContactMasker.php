<?php

namespace App\Services;

/**
 * LGPD (minimização, art. 6º III): troca e-mail, telefone, CPF e links de perfil por marcadores antes de enviar
 * texto para IAs de terceiros, e recoloca os dados originais na resposta.
 */
class ContactMasker
{
    private const PATTERNS = [
        'EMAIL' => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
        'CPF' => '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
        'PERFIL' => '~(?:https?://)?(?:[a-z]{2,3}\.)?(?:linkedin\.com/in|github\.com|gitlab\.com|wa\.me)/[^\s<>"|,;]+~i',
        // Exige DDD (10 ou 11 dígitos) para não confundir com períodos como "2024-2025".
        'TELEFONE' => '/(?:\+?55[\s.-]?)?\(?\b\d{2}\)?[\s.-]?9?\d{4}[\s.-]?\d{4}\b/',
    ];

    /** @var array<string, string> marcador => valor original */
    private array $map = [];

    /** Remove contatos de um texto qualquer (ex.: descrição de vaga), sem guardar o original. */
    public static function strip(string $text, string $replacement = '[contato removido: veja o anúncio original]'): string
    {
        foreach (self::PATTERNS as $pattern) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    public function mask(string $text): string
    {
        foreach (self::PATTERNS as $label => $pattern) {
            $text = preg_replace_callback($pattern, function (array $m) use ($label): string {
                $existing = array_search($m[0], $this->map, true);
                if ($existing !== false) {
                    return $existing;
                }
                $token = '['.$label.'_'.(count($this->map) + 1).']';
                $this->map[$token] = $m[0];

                return $token;
            }, $text);
        }

        return $text;
    }

    /** Recoloca os valores originais em strings, listas e objetos (resposta JSON da IA). */
    public function unmask(mixed $value): mixed
    {
        if (is_string($value)) {
            return strtr($value, $this->map);
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->unmask($v), $value);
        }

        return $value;
    }
}
