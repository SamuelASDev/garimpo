<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Senha forte em produção: 10+ caracteres, letras e números, e não pode estar em vazamentos conhecidos
        // (consulta anônima ao Have I Been Pwned: só os 5 primeiros caracteres do hash saem do servidor).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));

        // Tela "Permitir que <IA> acesse sua conta do Garimpo?" do login OAuth das IAs conectadas.
        Passport::authorizationView(fn (array $parameters) => view('mcp::authorize', $parameters));

        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(90));
        Passport::personalAccessTokensExpireIn(now()->addYear());
    }
}
