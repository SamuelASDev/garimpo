<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Resumo diário: um e-mail por pessoa com as vagas novas de todos os alertas dela.
 */
class JobAlertDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{alert: \App\Models\JobAlert, jobs: \Illuminate\Support\Collection, total: int}>  $sections
     */
    public function __construct(public User $user, public array $sections) {}

    public function envelope(): Envelope
    {
        $total = array_sum(array_column($this->sections, 'total'));

        return new Envelope(subject: "🔔 {$total} ".($total === 1 ? 'vaga nova' : 'vagas novas').' para você no '.config('app.name'));
    }

    /** Cabeçalho padrão de descadastro (Gmail/Outlook mostram o botão "Cancelar inscrição"). */
    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.job-alerts', with: [
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('alerts.unsubscribe', ['user' => $this->user->id]);
    }
}
