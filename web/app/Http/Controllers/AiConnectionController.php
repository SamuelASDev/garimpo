<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gera o token que a IA do próprio usuário usa para acessar o servidor MCP do Garimpo.
 */
class AiConnectionController extends Controller
{
    public function show(Request $request): View
    {
        return view('ai.show', [
            'tokens' => $request->user()->tokens()->where('name', 'mcp')->latest()->get(),
            'mcpUrl' => url('/mcp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = $request->user()->createToken('mcp', ['mcp']);

        return redirect()->route('ai.show')->with('token', $token->plainTextToken);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->tokens()->where('name', 'mcp')->delete();

        return redirect()->route('ai.show')->with('status', 'Todos os acessos de IA foram revogados.');
    }
}
