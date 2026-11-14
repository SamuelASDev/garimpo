<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeTailoring extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recommendations' => 'array'];
    }

    public function jobPosting(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
