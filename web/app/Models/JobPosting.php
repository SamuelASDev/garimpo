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
        return ['techs' => 'array', 'remote' => 'boolean', 'posted_at' => 'datetime'];
    }

    /** Aplica filtros vindos da querystring (q, seniority, area, tech, remote). */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%$v%")->orWhere('company', 'like', "%$v%")))
            ->when($f['seniority'] ?? null, fn ($q, $v) => $q->where('seniority', $v))
            ->when($f['area'] ?? null, fn ($q, $v) => $q->where('area', $v))
            ->when(in_array($f['tech'] ?? null, array_keys(JobClassifier::TECHS), true), fn ($q) => $q->where('techs', 'like', '%'.json_encode($f['tech']).'%'))
            ->when($f['remote'] ?? null, fn ($q) => $q->where('remote', true));
    }
}
