<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resumo diário dos alertas de vagas (1 e-mail por pessoa, às 8h de Brasília).
// No servidor, um cron por minuto roda o agendador: * * * * * php artisan schedule:run
Schedule::command('alerts:send')->dailyAt('08:00')->timezone('America/Sao_Paulo')->withoutOverlapping();

// Coleta de vagas a cada 4h. O agendador gratuito do GitHub atrasa ou pula execuções; disparada daqui,
// pelo cron do servidor, ela começa na hora. Só roda com GITHUB_DISPATCH_TOKEN configurado.
Schedule::command('garimpo:coletar')->cron('0 1,5,9,13,17,21 * * *')->timezone('America/Sao_Paulo')
    ->when(fn (): bool => (bool) config('services.github.token'));

// Pede ao GitHub Actions para rodar a coleta agora: php artisan garimpo:coletar
Artisan::command('garimpo:coletar', function () {
    $token = config('services.github.token');
    $repo = config('services.github.repo');
    if (! $token || ! $repo) {
        $this->error('Configure GITHUB_DISPATCH_TOKEN e GITHUB_REPO no .env (veja o README).');

        return 1;
    }

    $response = Http::withToken($token)->acceptJson()
        ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
        ->post("https://api.github.com/repos/{$repo}/actions/workflows/collect.yml/dispatches", ['ref' => 'main']);

    if ($response->status() === 204) {
        return $this->info('Coleta disparada. Acompanhe em https://github.com/'.$repo.'/actions');
    }

    Log::warning('Coleta: o GitHub recusou o disparo', ['status' => $response->status(), 'erro' => mb_substr($response->body(), 0, 300)]);
    $this->error('O GitHub recusou ('.$response->status().'). Confira se o token está válido e tem permissão Actions: Read and write no repositório.');

    return 1;
})->purpose('Dispara a coleta de vagas no GitHub Actions');

// Testa a configuração de e-mail do .env: php artisan garimpo:testar-email voce@exemplo.com
Artisan::command('garimpo:testar-email {para}', function (string $para) {
    Mail::raw('Se você recebeu esta mensagem, o envio de e-mails do '.config('app.name').' está funcionando.', function ($m) use ($para) {
        $m->to($para)->subject('Teste de e-mail - '.config('app.name'));
    });
    $this->info('Enviado para '.$para.' usando "'.config('mail.default').'" ('.config('mail.mailers.smtp.host').'). Confira a caixa de entrada e o spam.');
})->purpose('Envia um e-mail de teste com a configuração atual');

// Dá (ou tira, com --remover) acesso ao painel de administração: php artisan garimpo:admin voce@exemplo.com
Artisan::command('garimpo:admin {email} {--remover}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        return $this->error('Nenhuma conta com o e-mail '.$email.'.');
    }
    $user->forceFill(['is_admin' => ! $this->option('remover')])->save();
    $this->info($user->is_admin ? $email.' agora é administrador.' : $email.' não é mais administrador.');
})->purpose('Dá ou tira o acesso de administrador');
