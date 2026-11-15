<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resumo diário dos alertas de vagas (1 e-mail por pessoa, às 8h de Brasília).
// No servidor, um cron por minuto roda o agendador: * * * * * php artisan schedule:run
Schedule::command('alerts:send')->dailyAt('08:00')->timezone('America/Sao_Paulo')->withoutOverlapping();

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

// Dá (ou tira, com --remover) acesso ao painel de administração: php artisan garimpo:admin voce@exemplo.com
Artisan::command('garimpo:admin {email} {--remover}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        return $this->error('Nenhuma conta com o e-mail '.$email.'.');
    }
    $user->forceFill(['is_admin' => ! $this->option('remover')])->save();
    $this->info($user->is_admin ? $email.' agora é administrador.' : $email.' não é mais administrador.');
})->purpose('Dá ou tira o acesso de administrador');
