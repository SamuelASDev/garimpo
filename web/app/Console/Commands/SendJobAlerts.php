<?php

namespace App\Console\Commands;

use App\Mail\JobAlertDigest;
use App\Models\JobAlert;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envia o resumo diário de alertas: no máximo 1 e-mail por pessoa, só se houver vaga nova,
 * só para e-mails confirmados.
 */
class SendJobAlerts extends Command
{
    protected $signature = 'alerts:send {--dry-run : Mostra quem receberia, sem enviar}';

    protected $description = 'Envia o e-mail diário com as vagas novas dos alertas';

    public function handle(): int
    {
        $startedAt = now();
        $sent = 0;

        $users = User::whereNotNull('email_verified_at')
            ->whereHas('jobAlerts', fn ($q) => $q->where('active', true))
            ->with(['jobAlerts' => fn ($q) => $q->where('active', true)->oldest()]);

        foreach ($users->lazy() as $user) {
            $seen = [];
            $sections = [];

            foreach ($user->jobAlerts as $alert) {
                $jobs = $alert->newJobs()->whereNotIn('id', $seen)->limit(JobAlert::JOBS_PER_ALERT)->get();
                if ($jobs->isEmpty()) {
                    continue;
                }
                $seen = [...$seen, ...$jobs->pluck('id')->all()];
                $sections[] = ['alert' => $alert, 'jobs' => $jobs, 'total' => $alert->newJobs()->count()];
            }

            if ($sections === []) {
                continue; // sem vaga nova, sem e-mail
            }

            $this->line("{$user->email}: ".count($seen).' vagas em '.count($sections).' alerta(s)');
            if ($this->option('dry-run')) {
                continue;
            }

            try {
                Mail::to($user)->send(new JobAlertDigest($user, $sections));
                // Todos os alertas ativos avançam: inclusive os cujas vagas já saíram em outro alerta deste e-mail.
                $user->jobAlerts()->where('active', true)->update(['last_sent_at' => $startedAt]);
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $this->error("Falha ao enviar para {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info("E-mails enviados: $sent");

        return self::SUCCESS;
    }
}
