<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PastedDescription;
use App\Models\ResumeTailoring;
use Illuminate\Support\Str;

/**
 * Acha a mesma vaga quando ela chega por outro site com o nome escrito diferente
 * ("BEES" x "BEES Brasil", "Software Engineer GO PL" x "... GO PLENO", código e "- Híbrido" no cargo).
 *
 * É conservador de propósito: só junta quando empresa E cargo batem depois de normalizados,
 * o nível é o mesmo, o local não conflita e as descrições são quase iguais.
 * Na dúvida, mantém as duas: vaga repetida incomoda, vaga sumida é pior.
 */
class JobDeduplicator
{
    /** Parte das descrições que precisa coincidir (trechos de 3 palavras). */
    public const DESCRIPTION_MATCH = 0.6;

    /** No mesmo site, a descrição tem que ser praticamente a mesma. */
    public const SAME_SITE_MATCH = 0.9;

    /** Abaixo disso, duas descrições completas são de vagas diferentes. */
    public const DIFFERENT_BELOW = 0.3;

    /** Menos que isso de texto não dá para comparar com segurança. */
    private const MIN_SHINGLES = 20;

    /** Palavras que mudam de um site para outro no nome da mesma empresa. */
    private const COMPANY_NOISE = ['ltda', 'sa', 'ss', 'eireli', 'me', 'epp', 'inc', 'llc', 'ltd', 'corp', 'corporation', 'co',
        'brasil', 'brazil', 'latam', 'grupo', 'group', 'tecnologia', 'tecnologias', 'tecnologica', 'tecnologicas', 'technology',
        'technologies', 'solutions', 'solucoes', 'consultoria', 'participacoes', 'servicos', 'de', 'do', 'da', 'e'];

    /** Palavras de modalidade e de anúncio que não mudam o cargo. */
    private const TITLE_NOISE = ['remoto', 'remota', 'remote', 'hibrido', 'hibrida', 'hybrid', 'presencial', 'home', 'office', 'vaga', 'oportunidade'];

    private const LEVELS = ['jr' => 'junior', 'pl' => 'pleno', 'sr' => 'senior', 'estagiario' => 'estagio', 'estagiaria' => 'estagio', 'intern' => 'estagio',
        'trainee' => 'trainee', 'junior' => 'junior', 'pleno' => 'pleno', 'senior' => 'senior', 'especialista' => 'especialista',
        'i' => 'i', 'ii' => 'ii', 'iii' => 'iii', 'iv' => 'iv', 'lead' => 'lead', 'lider' => 'lead', 'staff' => 'staff', 'principal' => 'principal', 'intermediate' => 'pleno'];

    /** Empresa normalizada, sem espaços: "Qi Network - Google Cloud Partner" e "Qi Network Soluções Ltda" viram "qinetwork". */
    public static function companyKey(?string $company): string
    {
        // Depois de " - " ou " | " costuma vir slogan ("Atacadão Dia a Dia - Faça parte do nosso time").
        $company = preg_split('/\s+[-|–]\s+/u', (string) $company)[0];
        $words = self::words($company);

        return implode('', array_diff($words, self::COMPANY_NOISE)) ?: implode('', $words);
    }

    /** Cargo normalizado: sem códigos, sem "(a)", sem modalidade, com o nível escrito por extenso. */
    public static function titleKey(string $title): string
    {
        $title = preg_replace(['/\b[a-z]*\d{3,}[a-z0-9]*\b/i', '/\((a|o|as|os)\)|\/(a|o)\b/iu'], ' ', Str::ascii($title));
        $words = array_map(fn (string $w): string => self::LEVELS[$w] ?? $w, self::words($title));

        return implode(' ', array_values(array_diff($words, self::TITLE_NOISE)));
    }

    /**
     * As duas vagas são a mesma?
     *
     * @param  array{title: string, company?: ?string, location?: ?string, description?: ?string, source?: ?string}  $a
     * @param  array{title: string, company?: ?string, location?: ?string, description?: ?string, source?: ?string}  $b
     */
    public static function same(array $a, array $b): bool
    {
        if (self::differentCodes($a['title'], $b['title'])) {
            return false;
        }

        // O mesmo site listando duas vezes só conta como repetida com o texto praticamente idêntico.
        $sameSite = ! empty($a['source']) && ! empty($b['source']) && Str::lower($a['source']) === Str::lower($b['source']);

        return self::sameCompany($a['company'] ?? null, $b['company'] ?? null)
            && self::titleKey($a['title']) === self::titleKey($b['title'])
            && self::compatibleLocations($a['location'] ?? null, $b['location'] ?? null)
            && self::similarity((string) ($a['description'] ?? ''), (string) ($b['description'] ?? ''), $sameSite) >= ($sameSite ? self::SAME_SITE_MATCH : self::DESCRIPTION_MATCH);
    }

    /**
     * Cargo e empresa iguais, mas dá para ver que é outra vaga: outro código de requisição, outra cidade, ou as duas têm descrição completa
     * e quase nada em comum (a mesma empresa abrindo o mesmo cargo para outro time).
     *
     * @param  array{title?: string, location?: ?string, description?: ?string}  $a
     * @param  array{title?: string, location?: ?string, description?: ?string}  $b
     */
    public static function clearlyDifferent(array $a, array $b): bool
    {
        if (self::differentCodes($a['title'] ?? '', $b['title'] ?? '') || ! self::compatibleLocations($a['location'] ?? null, $b['location'] ?? null)) {
            return true;
        }
        $sa = self::shingles((string) ($a['description'] ?? ''));
        $sb = self::shingles((string) ($b['description'] ?? ''));

        return min(count($sa), count($sb)) >= self::MIN_SHINGLES
            && count(array_intersect_key($sa, $sb)) / min(count($sa), count($sb)) < self::DIFFERENT_BELOW;
    }

    /** Uma vaga já gravada que é a mesma desta (ou null). */
    public static function findExisting(array $item): ?JobPosting
    {
        $key = self::companyKey($item['company'] ?? null);
        if (strlen($key) < 2) {
            return null;
        }

        // Candidatas: empresas que começam igual ("bees" acha "bees" e "beesbrasil...").
        return JobPosting::query()
            ->where('company_key', 'like', substr($key, 0, 4).'%')
            ->where('created_at', '>=', now()->subDays((int) config('garimpo.retention_days', 60)))
            ->get(['id', 'url', 'source', 'title', 'company', 'location', 'description'])
            ->first(fn (JobPosting $job): bool => self::same($item, $job->only(['source', 'title', 'company', 'location', 'description'])));
    }

    /**
     * Pares repetidos entre as vagas gravadas: [fica, sai]. Fica a que alguém já usa (currículo adaptado,
     * candidatura, descrição colada); depois a de descrição mais completa; depois a mais antiga.
     * Se as duas têm dados de alguém, nenhuma sai.
     *
     * @return list<array{0: JobPosting, 1: JobPosting}>
     */
    public static function duplicatePairs(): array
    {
        $jobs = JobPosting::query()->whereNotNull('company_key')->orderBy('id')
            ->get(['id', 'title', 'company', 'company_key', 'location', 'description', 'source', 'url']);
        $used = array_flip([
            ...ResumeTailoring::pluck('job_posting_id')->all(),
            ...PastedDescription::pluck('job_posting_id')->all(),
            ...JobApplication::whereNotNull('job_posting_id')->pluck('job_posting_id')->all(),
        ]);

        $pairs = [];
        $gone = [];
        foreach ($jobs->groupBy(fn (JobPosting $job): string => substr($job->company_key, 0, 4)) as $group) {
            $group = $group->values();
            foreach ($group as $i => $a) {
                for ($j = $i + 1; $j < $group->count(); $j++) {
                    $b = $group[$j];
                    if (isset($gone[$a->id]) || isset($gone[$b->id]) || (isset($used[$a->id]) && isset($used[$b->id]))) {
                        continue;
                    }
                    if (! self::same($a->toArray(), $b->toArray())) {
                        continue;
                    }
                    $keepB = isset($used[$b->id]) || (! isset($used[$a->id]) && mb_strlen((string) $b->description) > mb_strlen((string) $a->description) + 200);
                    [$keep, $drop] = $keepB ? [$b, $a] : [$a, $b];
                    $gone[$drop->id] = true;
                    $pairs[] = [$keep, $drop];
                }
            }
        }

        return $pairs;
    }

    /** Mesma empresa: iguais, ou um nome é o outro com mais palavras ("Britânia" x "Britânia Eletrodomésticos"). */
    public static function sameCompany(?string $a, ?string $b): bool
    {
        $a = self::companyKey($a);
        $b = self::companyKey($b);
        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b || (min(strlen($a), strlen($b)) >= 4 && (str_starts_with($a, $b) || str_starts_with($b, $a)));
    }

    /** Cidades diferentes = vagas diferentes. "Brazil", "Remoto" ou vazio não conflitam com nada. */
    public static function compatibleLocations(?string $a, ?string $b): bool
    {
        $a = self::place($a);
        $b = self::place($b);

        if ($a['state'] && $b['state'] && $a['state'] !== $b['state']) {
            return false;
        }

        return ! ($a['city_key'] && $b['city_key'] && $a['city_key'] !== $b['city_key']);
    }

    /** Códigos de requisição diferentes no cargo ("12888211 - ..." x "12888087 - ...") são duas vagas abertas. */
    private static function differentCodes(string $a, string $b): bool
    {
        $codes = fn (string $title): array => preg_match_all('/\d{4,}/', $title, $m) ? array_unique($m[0]) : [];

        return $codes($a) && $codes($b) && array_diff($codes($a), $codes($b));
    }

    /** @return array{city_key: ?string, state: ?string} */
    private static function place(?string $location): array
    {
        $parsed = Locations::parse(str_replace(['—', '–'], '-', (string) $location));
        $vague = ['qualquer lugar', 'local nao informado', 'nao informado', 'remoto', 'remote', 'home office', 'anywhere'];

        return ['city_key' => in_array($parsed['city_key'], $vague, true) ? null : $parsed['city_key'], 'state' => $parsed['state']];
    }

    /**
     * Quanto das descrições coincide (0 a 1), em trechos de 3 palavras, sobre a menor delas:
     * um site que corta a descrição continua batendo com o que traz ela inteira.
     * Com $whole, conta sobre a maior: as duas precisam ter praticamente o mesmo texto.
     */
    public static function similarity(string $a, string $b, bool $whole = false): float
    {
        $a = self::shingles($a);
        $b = self::shingles($b);
        $smaller = min(count($a), count($b));

        return $smaller < self::MIN_SHINGLES ? 0.0 : count(array_intersect_key($a, $b)) / ($whole ? max(count($a), count($b)) : $smaller);
    }

    /** @return array<string, true> */
    private static function shingles(string $text): array
    {
        $words = self::words($text);
        $set = [];
        for ($i = 0, $n = count($words) - 2; $i < $n; $i++) {
            $set[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]] = true;
        }

        return $set;
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        return preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);
    }
}
