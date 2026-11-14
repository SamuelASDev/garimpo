<?php

namespace App\Models;

use App\Services\JobClassifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    protected $guarded = ['id'];

    /** true quando esta instância usa a descrição colada pelo usuário (ver forUser). */
    public bool $pastedByUser = false;

    /** O site de origem só trouxe um resumo (a pessoa pode colar a descrição completa). */
    public function isShort(): bool
    {
        return mb_strlen((string) $this->getOriginal('description')) < \App\Services\JobIngestor::SHORT_DESCRIPTION;
    }

    /**
     * A vaga como esta pessoa a vê: se ela colou a descrição completa, usa esse texto e soma
     * as tecnologias/competências encontradas nele. Nunca é salvo (os outros usuários veem a vaga original).
     */
    public function forUser(?User $user): static
    {
        $text = $user ? PastedDescription::where('user_id', $user->id)->where('job_posting_id', $this->id)->value('description') : null;
        if (! $text) {
            return $this;
        }

        $found = app(JobClassifier::class)->classify($this->title, $text);
        $copy = clone $this;
        $copy->description = $text;
        foreach (['techs', 'skills', 'soft_skills'] as $field) {
            $copy->$field = array_values(array_unique([...($this->$field ?? []), ...$found[$field]]));
        }
        $copy->pastedByUser = true;

        return $copy;
    }

    protected function casts(): array
    {
        return ['techs' => 'array', 'skills' => 'array', 'soft_skills' => 'array', 'remote' => 'boolean', 'posted_at' => 'datetime'];
    }

    /**
     * Aplica filtros vindos da querystring (q, seniority, area, tech, remote).
     * "tech" pode ser uma tecnologia ou uma lista: a vaga precisa ter TODAS as marcadas.
     */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        $techs = array_intersect((array) ($f['tech'] ?? []), array_keys(JobClassifier::TECHS));
        foreach ($techs as $tech) {
            $query->where('techs', 'like', '%'.json_encode($tech).'%');
        }

        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%$v%")->orWhere('company', 'like', "%$v%")))
            ->when($f['seniority'] ?? null, fn ($q, $v) => $q->where('seniority', $v))
            ->when($f['area'] ?? null, fn ($q, $v) => $q->where('area', $v))
            ->when($f['remote'] ?? null, fn ($q) => $q->where('remote', true));
    }
}
