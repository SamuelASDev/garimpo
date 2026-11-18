<?php

namespace App\Http\Resources;

use App\Models\JobPosting;
use App\Services\JobClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Vaga na API pública. Entrega o que o Garimpo produz (classificação, local, tecnologias) e o link do
 * anúncio original; a descrição completa pertence ao site de origem, então vai só um resumo curto.
 *
 * @mixin JobPosting
 */
class JobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $summary = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->summary ?: $this->description))));

        return [
            'id' => $this->id,
            'titulo' => $this->title,
            'empresa' => $this->company,
            'local' => $this->location,
            'cidade' => $this->city,
            'uf' => $this->state,
            'modo' => $this->work_mode,
            'senioridade' => $this->seniority,
            'area' => $this->area,
            'stack' => JobClassifier::stackOf((string) $this->title, $this->techs ?? []),
            'tecnologias' => $this->techs ?? [],
            'competencias' => $this->skills ?? [],
            'resumo' => $summary !== '' ? Str::limit($summary, 280) : null,
            'fonte' => $this->source,
            'url_original' => $this->url,
            'url_garimpo' => route('jobs.show', $this->id),
            'publicada_em' => $this->posted_at?->toIso8601String(),
            'coletada_em' => $this->created_at?->toIso8601String(),
        ];
    }
}
