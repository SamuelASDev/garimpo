<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Mcp\Server\Registrar;

/**
 * Conexão da IA do usuário com o MCP do Garimpo: por login OAuth (automático) ou por token pessoal (manual).
 */
class AiConnectionController extends Controller
{
    public function show(Request $request): View
    {
        return view('ai.show', [
            // Tokens ativos: os do login OAuth (Claude.ai, ChatGPT...) e os pessoais gerados aqui.
            'tokens' => $request->user()->tokens()->with('client')->where('revoked', false)->where('expires_at', '>', now())->latest()->get(),
            'mcpUrl' => url('/mcp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = $request->user()->createToken('mcp', [Registrar::OAUTH_SCOPE]);

        return redirect()->route('ai.show')->with('token', $token->accessToken);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->tokens()->update(['revoked' => true]);

        return redirect()->route('ai.show')->with('status', 'Todas as IAs foram desconectadas.');
    }
}
