<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

/**
 * Conexões da IA do usuário com o MCP do Garimpo: login OAuth (automático) ou token manual
 * com app, nome e validade escolhidos. Cada conexão pode ser desconectada sozinha.
 */
class AiConnectionController extends Controller
{
    /** @var array<string, string> app => rótulo */
    public const APPS = [
        'claude-code' => 'Claude Code',
        'codex' => 'Codex',
        'cursor' => 'Cursor',
        'windsurf' => 'Windsurf',
        'vscode' => 'VS Code',
        'outro' => 'Outro app',
    ];

    /** @var array<int, string> dias => rótulo */
    public const VALIDITY = [7 => '1 semana', 30 => '1 mês', 90 => '3 meses', 365 => '1 ano'];

    public function show(Request $request): View
    {
        return view('dev.index', [
            'tokens' => $request->user()?->tokens()->with('client')->where('revoked', false)->where('expires_at', '>', now())->latest()->get() ?? collect(),
            'mcpUrl' => url('/mcp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app' => ['required', Rule::in(array_keys(self::APPS))],
            'label' => ['nullable', 'string', 'max:40'],
            'days' => ['required', 'integer', Rule::in(array_keys(self::VALIDITY))],
        ]);

        // A validade vai dentro do próprio token (não dá para usar depois de expirar).
        Passport::personalAccessTokensExpireIn(now()->addDays((int) $data['days']));
        $name = trim(self::APPS[$data['app']].' · '.($data['label'] ?: 'sem nome'));
        $token = $request->user()->createToken(mb_substr($name, 0, 80), [Registrar::OAUTH_SCOPE]);

        return redirect()->to(route('dev').'#ia')->with([
            'token' => $token->accessToken,
            'token_app' => $data['app'],
            'status' => "Conexão \"$name\" criada. Válida até ".now()->addDays((int) $data['days'])->format('d/m/Y').'.',
        ]);
    }

    /** Desconecta uma conexão específica. */
    public function revoke(Request $request, string $tokenId): RedirectResponse
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->firstOrFail();
        $token->revoke();
        // Sem isso, um app OAuth renovaria o acesso sozinho com o refresh token.
        DB::table('oauth_refresh_tokens')->where('access_token_id', $token->id)->update(['revoked' => true]);

        return redirect()->to(route('dev').'#ia')->with('status', 'Conexão desconectada.');
    }

    /** Desconecta todas. */
    public function destroy(Request $request): RedirectResponse
    {
        $ids = $request->user()->tokens()->pluck('id');
        $request->user()->tokens()->update(['revoked' => true]);
        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $ids)->update(['revoked' => true]);

        return redirect()->to(route('dev').'#ia')->with('status', 'Todas as IAs foram desconectadas.');
    }
}
