<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resume extends Model
{
    protected $guarded = ['id', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Texto completo usado para comparar com a vaga. */
    public function plainText(): string
    {
        return implode("\n", [$this->headline, $this->summary, $this->skills, $this->experience, $this->education, $this->extras]);
    }
}
