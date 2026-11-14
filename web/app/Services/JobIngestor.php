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
     * @param  list<string>  $urls
     * @return list<string>
     */
    public function pendingDescriptions(array $urls): array
    {
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
        $stats = ['created' => 0, 'updated' => 0, 'duplicated' => 0, 'ignored' => 0];

        foreach ($items as $item) {
            if (! $this->classifier->isTech($item['title'])) {
                $stats['ignored']++;

                continue;
            }

            $description = trim(strip_tags($item['description'] ?? ''));
            $fingerprint = self::fingerprint($item['title'], $item['company'] ?? '', $item['location'] ?? '');
            $existing = JobPosting::where('url', $item['url'])->first() ?? JobPosting::where('fingerprint', $fingerprint)->first();

            if ($existing) {
                // Mesma vaga, mas agora com a descrição completa: completa a que estava só com resumo.
                if ($existing->url === $item['url'] && mb_strlen((string) $existing->description) < self::SHORT_DESCRIPTION && mb_strlen($description) > mb_strlen((string) $existing->description)) {
                    $existing->update([
                        'description' => $description,
                        'summary' => Str::limit(preg_replace('/\s+/', ' ', $description), 280),
                        ...$this->classifier->classify($existing->title, $description),
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
                'remote' => (bool) ($item['remote'] ?? false),
                'summary' => Str::limit(preg_replace('/\s+/', ' ', $description), 280),
                'description' => $description,
                'posted_at' => isset($item['posted_at']) ? Carbon::parse($item['posted_at']) : now(),
                ...$this->classifier->classify($item['title'], $description),
            ]);
            $stats['created']++;
        }

        return $stats;
    }

    /** Mesma vaga em fontes diferentes gera o mesmo hash (minúsculas, sem acento, sem pontuação). */
    public static function fingerprint(string $title, string $company, string $location): string
    {
        // Códigos internos ("12888211 - ", "RHA4554") não fazem parte do cargo.
        $normalize = fn (string $s): string => preg_replace(
            ['/\b[a-z]*\d{3,}[a-z0-9]*\b/', '/[^a-z]+/'], ['', ' '], Str::lower(Str::ascii($s))
        );

        return hash('sha256', trim($normalize($title)).'|'.trim($normalize($company)).'|'.trim($normalize($location)));
    }
}
