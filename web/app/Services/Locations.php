<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Cada site escreve o local de um jeito ("São Paulo, São Paulo", "São Paulo - SP", "Sao Paulo / SP, Brazil").
 * Aqui separamos cidade e estado (UF) para filtrar e sugerir sem repetir a mesma cidade.
 */
class Locations
{
    public const STATES = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
        'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
        'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    /** Palavras que não são cidade ("Remoto, Brazil", "Home office"). */
    private const NOT_A_CITY = '~^(remot[oa]|remote|home ?office|anywhere|h(í|i)brido|hybrid|brasil|brazil|todo o brasil|diversas|v(á|a)rias)$~iu';

    /** Chave para comparar nomes: minúsculas, sem acento ("São Paulo" = "Sao Paulo"). */
    public static function key(?string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $name))));
    }

    /** UF a partir da sigla ou do nome do estado. */
    public static function state(string $text): ?string
    {
        $text = trim($text);
        if (strlen($text) === 2 && isset(self::STATES[strtoupper($text)])) {
            return strtoupper($text);
        }
        $key = self::key($text);
        foreach (self::STATES as $uf => $name) {
            if (self::key($name) === $key) {
                return $uf;
            }
        }

        return null;
    }

    /** @return array{city: ?string, city_key: ?string, state: ?string} */
    public static function parse(?string $location): array
    {
        $text = preg_replace(['~\(.*?\)~', '~^(greater|grande)\s+~i', '~\s+e\s+regi(ã|a)o.*$~iu', '~regi(ã|a)o metropolitana de\s+~iu', '~\s+(metropolitan\s+)?area\b~i'], '', (string) $location);
        $parts = array_values(array_filter(
            array_map('trim', preg_split('~\s*[,/|]\s*|\s+-\s+~u', $text)),
            fn (string $p): bool => $p !== '' && ! preg_match('~^(brasil|brazil)$~i', $p),
        ));

        $city = null;
        $state = null;
        foreach ($parts as $i => $part) {
            if ($i === 0) {
                // "SP" sozinho é estado; "São Paulo" sozinho é a cidade (e o estado de mesmo nome).
                if (strlen($part) === 2 && self::state($part)) {
                    $state = self::state($part);
                } elseif (! preg_match(self::NOT_A_CITY, $part)) {
                    $city = $part;
                }

                continue;
            }
            $state ??= self::state($part);
        }
        if ($city && ! $state && count($parts) === 1) {
            $state = self::state($city);
        }

        return ['city' => $city ? Str::limit($city, 120, '') : null, 'city_key' => $city ? self::key($city) : null, 'state' => $state];
    }
}
