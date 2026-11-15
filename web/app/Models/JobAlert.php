<?php

namespace App\Models;

use App\Services\JobClassifier;
use App\Services\Locations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta de vagas: os mesmos filtros da lista de vagas, salvos para o resumo diário por e-mail.
 */
class JobAlert extends Model
{
    public const MAX_PER_USER = 10;

    public const JOBS_PER_ALERT = 10;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'active' => 'boolean', 'last_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Vagas que bateram com o alerta desde o último envio (ou desde que o alerta foi criado). */
    public function newJobs(): Builder
    {
        return JobPosting::filter($this->filters ?? [])
            ->where('created_at', '>', $this->last_sent_at ?? $this->created_at)
            // Só vagas publicadas nas últimas 24h: anúncio antigo que entrou agora não vira alerta.
            ->where('posted_at', '>=', now()->subDay())
            ->latest('posted_at');
    }

    /** Resumo legível dos filtros: "Júnior · Desenvolvimento · PHP + Laravel · Remoto". */
    public function describe(): string
    {
        $f = $this->filters ?? [];
        $labels = JobClassifier::LABELS;

        $parts = array_filter([
            ! empty($f['q']) ? '"'.$f['q'].'"' : null,
            $labels[$f['seniority'] ?? ''] ?? null,
            $labels[$f['area'] ?? ''] ?? null,
            ! empty($f['tech']) ? implode(' + ', (array) $f['tech']) : null,
            ! empty($f['remote']) ? 'Remoto' : null,
            JobClassifier::WORK_MODES[$f['mode'] ?? ''] ?? null,
            ! empty($f['city']) ? 'em '.$f['city'] : null,
            ! empty($f['state']) ? 'estado: '.$f['state'] : null,
        ]);

        return $parts ? implode(' · ', $parts) : 'Todas as vagas de TI';
    }

    /** Guarda só filtros conhecidos (nada de chave arbitrária vinda do formulário). */
    public static function cleanFilters(array $input): array
    {
        return array_filter([
            'q' => isset($input['q']) ? mb_substr(trim((string) $input['q']), 0, 60) : null,
            'seniority' => in_array($input['seniority'] ?? null, JobClassifier::SENIORITIES, true) ? $input['seniority'] : null,
            'area' => array_key_exists($input['area'] ?? '', JobClassifier::AREAS) ? $input['area'] : null,
            'tech' => array_values(array_intersect((array) ($input['tech'] ?? []), array_keys(JobClassifier::TECHS))) ?: null,
            'remote' => ! empty($input['remote']) ? true : null,
            'mode' => array_key_exists($input['mode'] ?? '', JobClassifier::WORK_MODES) ? $input['mode'] : null,
            'city' => isset($input['city']) ? mb_substr(trim((string) $input['city']), 0, 60) : null,
            'state' => isset(Locations::STATES[strtoupper((string) ($input['state'] ?? ''))]) ? strtoupper($input['state']) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
