<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variação do currículo para um foco (stack, área, tipo de vaga), criada e preenchida pela própria pessoa.
 * Diferente do currículo por vaga, não está ligada a um anúncio.
 */
class ResumeVariant extends Model
{
    protected $fillable = ['name', 'category', 'description', 'html', 'recommendations'];

    protected function casts(): array
    {
        return ['recommendations' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** O alvo da variação, no formato que a IA usa para comparar (como se fosse uma vaga). */
    public function asTarget(): JobPosting
    {
        $focus = trim(($this->category ? $this->category.' — ' : '').$this->name);

        return new JobPosting([
            'title' => $focus,
            'description' => 'Currículo de uso geral (não é para uma vaga específica), com foco em: '.$focus.'.'
                .($this->description ? "\nO que a pessoa quer destacar: ".$this->description : ''),
        ]);
    }
}
