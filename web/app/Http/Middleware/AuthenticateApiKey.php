<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API pública: exige uma chave (Authorization: Bearer gp_... ou X-Api-Key: gp_...).
 * A chave encontrada fica em $request->attributes(api_key).
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) ($request->bearerToken() ?: $request->header('X-Api-Key'));
        $key = $plain !== '' ? ApiKey::findByPlain($plain) : null;

        if (! $key) {
            return response()->json([
                'erro' => 'Chave de API ausente ou inválida.',
                'como_resolver' => 'Crie uma chave em '.route('profile.edit').' e envie no cabeçalho Authorization: Bearer <sua-chave>. Documentação: '.route('api.docs'),
            ], 401);
        }

        // Registra o último uso no máximo uma vez por minuto (evita uma escrita no banco a cada requisição).
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
