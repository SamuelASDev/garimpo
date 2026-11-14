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

    /**
     * Texto do currículo (sem HTML) usado para comparar com as vagas.
     * Trechos ainda marcados em amarelo ("ADICIONE: ...") não contam: são pendências, não experiência.
     */
    public function plainText(): string
    {
        if ($this->html) {
            $text = preg_replace(['~<mark[^>]*>.*?</mark>~is', '~<(br|/p|/li|/h[1-6])[^>]*>~i', '~[ \t]+~'], ['', "\n", ' '], $this->html);

            return trim(html_entity_decode(strip_tags($text)));
        }

        return implode("\n", array_filter([$this->full_name, $this->headline, $this->contact, $this->summary, $this->skills, $this->experience, $this->education, $this->extras]));
    }
}
