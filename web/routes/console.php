<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resumo diário dos alertas de vagas (1 e-mail por pessoa, às 8h de Brasília).
// No servidor, um cron por minuto roda o agendador: * * * * * php artisan schedule:run
Illuminate\Support\Facades\Schedule::command('alerts:send')->dailyAt('08:00')->timezone('America/Sao_Paulo')->withoutOverlapping();

// Testa a configuração de e-mail do .env: php artisan garimpo:testar-email voce@exemplo.com
Illuminate\Support\Facades\Artisan::command('garimpo:testar-email {para}', function (string $para) {
    Illuminate\Support\Facades\Mail::raw('Se você recebeu esta mensagem, o envio de e-mails do '.config('app.name').' está funcionando.', function ($m) use ($para) {
        $m->to($para)->subject('Teste de e-mail - '.config('app.name'));
    });
    $this->info('Enviado para '.$para.' usando "'.config('mail.default').'" ('.config('mail.mailers.smtp.host').'). Confira a caixa de entrada e o spam.');
})->purpose('Envia um e-mail de teste com a configuração atual');
