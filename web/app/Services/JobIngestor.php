<?php

namespace App\Services;

use App\Models\JobPosting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Recebe vagas brutas do coletor, descarta o que não é TI, classifica e grava sem duplicar.
 */
class JobIngestor
{
    /** Abaixo disso consideramos que a vaga só tem o resumo da página de busca. */
    public const SHORT_DESCRIPTION = 400;

    public function __construct(private JobClassifier $classifier) {}

    /**
     * Das URLs informadas, devolve as que o coletor deve abrir para pegar a descrição completa:
     * vagas novas ou que estão salvas só com o resumo.
     *
     * Com títulos (`[url => título]`), também descarta o que não é TI: a vaga seria ignorada na ingestão,
     * então não vale abrir a página dela (economiza acessos e evita bloqueio dos sites).
     *
     * Com empresas (`[url => empresa]`), também descarta a vaga que já entrou por outro site (mesmo cargo + empresa):
     * ela seria marcada como repetida, então não vale abrir a página do site que só mostra resumo.
     *
     * @param  list<string>|array<string, string>  $urls
     * @param  array<string, ?string>  $companies
     * @return list<string>
     */
    public function pendingDescriptions(array $urls, array $companies = []): array
    {
        if ($companies !== [] && ! array_is_list($urls)) {
            $known = JobPosting::whereIn('fingerprint', collect($urls)->map(fn ($title, $url) => self::fingerprint((string) $title, (string) ($companies[$url] ?? '')))->all())
                ->get(['url', 'fingerprint'])->groupBy('fingerprint');
            $urls = array_filter($urls, function ($title, $url) use ($companies, $known): bool {
                $match = $known[self::fingerprint((string) $title, (string) ($companies[$url] ?? ''))] ?? null;

                return ! $match || $match->contains('url', $url);
            }, ARRAY_FILTER_USE_BOTH);
        }

        if (! array_is_list($urls)) {
            $urls = array_keys(array_filter($urls, fn ($title): bool => is_string($title) && $this->classifier->isTech($title)));
        }

        $complete = JobPosting::whereIn('url', $urls)
            ->whereRaw('length(description) >= ?', [self::SHORT_DESCRIPTION])
            ->pluck('url')->all();

        return array_values(array_diff($urls, $complete));
    }

    /**
     * @param  list<array{source: string, url: string, title: string, company?: ?string, location?: ?string, remote?: bool, description?: ?string, posted_at?: ?string}>  $items
     * @return array{created: int, duplicated: int, ignored: int}
     */
    public function ingest(array $items): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'duplicated' => 0, 'ignored' => 0, 'old' => 0];
        $oldest = self::oldestAccepted();

        foreach ($items as $item) {
            if (! $this->classifier->isTech($item['title'])) {
                $stats['ignored']++;

                continue;
            }

            $postedAt = self::parseDate($item['posted_at'] ?? null);
            if ($postedAt && $postedAt->lt($oldest)) {
                $stats['old']++;

                continue;
            }

            // LGPD: e-mail/telefone de recrutadores não ficam no Garimpo (o anúncio original continua com eles).
            $description = ContactMasker::strip(self::cleanDescription($item['description'] ?? ''));
            $fingerprint = self::fingerprint($item['title'], $item['company'] ?? '');
            $existing = JobPosting::where('url', $item['url'])->first() ?? JobPosting::where('fingerprint', $fingerprint)->first();

            if ($existing) {
                // Mesma vaga, mas agora com a descrição completa: completa a que estava só com resumo.
                if ($existing->url === $item['url'] && mb_strlen((string) $existing->description) < self::SHORT_DESCRIPTION && mb_strlen($description) > mb_strlen((string) $existing->description)) {
                    $existing->update([
                        'description' => $description,
                        'summary' => Str::limit(preg_replace('/\s+/', ' ', $description), 280),
                        ...$this->classifier->classify($existing->title, $description),
                        ...$this->workMode(['title' => $existing->title, 'location' => $existing->location, 'work_mode' => $item['work_mode'] ?? null, 'remote' => $item['remote'] ?? $existing->remote], $description),
                    ]);
                    $stats['updated']++;
                } else {
                    $stats['duplicated']++;
                }

                continue;
            }

            JobPosting::create([
                'source' => $item['source'],
                'url' => $item['url'],
                'fingerprint' => $fingerprint,
                'title' => Str::limit($item['title'], 250, ''),
                'company' => $item['company'] ?? null,
                'location' => $item['location'] ?? null,
                ...$this->workMode($item, $description),
                'summary' => Str::limit(preg_replace('/\s+/', ' ', $description), 280),
                'description' => $description,
                'posted_at' => $postedAt && $postedAt->isPast() ? $postedAt : now(),
                ...$this->classifier->classify($item['title'], $description),
            ]);
            $stats['created']++;
        }

        return $stats;
    }

    /** @return array{work_mode: ?string, remote: bool} */
    private function workMode(array $item, string $description): array
    {
        $hint = $item['work_mode'] ?? (! empty($item['remote']) ? 'remoto' : null);
        $mode = $this->classifier->workMode($item['title'], $item['location'] ?? null, $description, $hint);

        return ['work_mode' => $mode, 'remote' => $mode === 'remoto'];
    }

    /**
     * Vaga mais antiga aceita: na primeira coleta até 30 dias, depois só da última semana.
     * "Primeira coleta" = ainda não há vaga gravada há mais de 2h (os lotes de uma mesma coleta chegam em minutos).
     */
    public static function oldestAccepted(): Carbon
    {
        $days = JobPosting::where('created_at', '<', now()->subHours(2))->exists() ? config('garimpo.max_age_days', 7) : config('garimpo.first_max_age_days', 30);

        return now()->subDays((int) $days)->startOfDay();
    }

    private static function parseDate(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** HTML/texto bruto da fonte -> texto com quebras de linha certas (sem grudar parágrafos e itens). */
    public static function cleanDescription(string $raw): string
    {
        $raw = preg_replace('~<(script|style)\b.*?</\1>~is', ' ', $raw);
        $raw = preg_replace(['~<br\s*/?>|</(p|div|h\d|li|tr)>~i', '~<li[^>]*>~i'], ["\n", "\n- "], $raw);

        return JobDescriptionFormatter::normalize(strip_tags($raw));
    }

    /**
     * Mesma vaga em fontes diferentes gera o mesmo hash: cargo + empresa (minúsculas, sem acento, sem pontuação).
     * O local fica de fora porque cada site escreve de um jeito ("Remoto", "Brazil", "São Paulo, SP").
     */
    public static function fingerprint(string $title, string $company): string
    {
        // Códigos internos ("12888211 - ", "RHA4554") não fazem parte do cargo.
        $normalize = fn (string $s): string => trim(preg_replace(
            ['/\b[a-z]*\d{3,}[a-z0-9]*\b/', '/[^a-z]+/'], ['', ' '], Str::lower(Str::ascii($s))
        ));
        // "Nexdom Healthtech Ltda." e "NEXDOM healthtech" são a mesma empresa.
        $company = preg_replace('/\b(ltda|s a|sa|eireli|me|epp|inc|llc|ltd)\b/', '', $normalize($company));

        return hash('sha256', $normalize($title).'|'.trim(preg_replace('/\s+/', ' ', $company)));
    }
}
