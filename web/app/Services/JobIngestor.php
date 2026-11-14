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
    public function __construct(private JobClassifier $classifier) {}

    /**
     * @param  list<array{source: string, url: string, title: string, company?: ?string, location?: ?string, remote?: bool, description?: ?string, posted_at?: ?string}>  $items
     * @return array{created: int, duplicated: int, ignored: int}
     */
    public function ingest(array $items): array
    {
        $stats = ['created' => 0, 'duplicated' => 0, 'ignored' => 0];

        foreach ($items as $item) {
            if (! $this->classifier->isTech($item['title'])) {
                $stats['ignored']++;

                continue;
            }

            $fingerprint = self::fingerprint($item['title'], $item['company'] ?? '', $item['location'] ?? '');
            if (JobPosting::where('fingerprint', $fingerprint)->orWhere('url', $item['url'])->exists()) {
                $stats['duplicated']++;

                continue;
            }

            $description = trim(strip_tags($item['description'] ?? ''));
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
