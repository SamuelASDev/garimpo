<?php

namespace App\Models;

use App\Services\JobClassifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    protected $guarded = ['id'];

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
