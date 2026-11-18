<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Painel de administração (/admin): só quem tem is_admin (promova com php artisan garimpo:admin email).
        Gate::define('admin', fn ($user): bool => (bool) $user->is_admin);

        // API pública: limite por chave (60 por minuto e 5.000 por dia); resposta 429 com Retry-After.
        RateLimiter::for('api-v1', function (Request $request): array {
            // O limite roda antes da autenticação: usa a própria chave enviada (hash) como identificador.
            $sent = (string) ($request->bearerToken() ?: $request->header('X-Api-Key'));
            $id = $sent !== '' ? hash('sha256', $sent) : $request->ip();

            return [Limit::perMinute(60)->by('min:'.$id), Limit::perDay(5000)->by('dia:'.$id)];
        });

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
