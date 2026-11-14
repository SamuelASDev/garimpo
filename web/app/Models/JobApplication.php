<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Candidatura de um usuário (substitui a planilha "Controle de Candidaturas").
 */
class JobApplication extends Model
{
    /** @var array<string, string> status => rótulo */
    public const STATUSES = [
        'enviado' => 'Enviado',
        'em_analise' => 'Em análise',
        'entrevista' => 'Entrevista',
        'teste_tecnico' => 'Teste técnico',
        'oferta' => 'Oferta',
        'recusado' => 'Recusado',
        'sem_retorno' => 'Sem retorno',
    ];

    /** Status que ainda esperam resposta da empresa. */
    public const OPEN = ['enviado', 'em_analise', 'entrevista', 'teste_tecnico'];

    public const FOLLOW_UP_DAYS = 15;

    public const NO_RESPONSE_DAYS = 30;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return ['applied_at' => 'date', 'last_contact_at' => 'date'];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Dias desde o último contato (ou desde o envio). */
    public function daysWithoutResponse(): int
    {
        return (int) ($this->last_contact_at ?? $this->applied_at)->diffInDays(today());
    }

    /** @return 'follow_up'|'no_response'|null */
    public function alert(): ?string
    {
        if (! $this->isOpen()) {
            return null;
        }
        $days = $this->daysWithoutResponse();

        return match (true) {
            $days > self::NO_RESPONSE_DAYS => 'no_response',
            $days > self::FOLLOW_UP_DAYS => 'follow_up',
            default => null,
        };
    }
}
