<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6" x-data="{ copiado: false }">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Conectar minha IA</h1>
            <p class="text-gray-500 mt-1 text-sm">
                Já usa Claude, ChatGPT, Codex ou Cursor? Conecte ao Garimpo e ela busca vagas, lê e adapta seus currículos
                e acompanha suas candidaturas, sem o limite da IA grátis.
            </p>
        </div>

        @foreach (['status' => 'bg-emerald-50 text-emerald-700'] as $key => $classes)
            @if (session($key))
                <div class="p-3 rounded-lg text-sm {{ $classes }}">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="bg-white rounded-xl border border-gray-200 p-6 text-sm space-y-4">
            <p class="font-semibold text-gray-900">Jeito fácil: só colar o endereço</p>
            <div class="flex gap-2">
                <code class="flex-1 bg-gray-100 rounded-lg px-3 py-2 break-all">{{ $mcpUrl }}</code>
                <button type="button" class="px-3 rounded-lg border border-gray-300 hover:bg-gray-50"
                        @click="navigator.clipboard.writeText(@js($mcpUrl)); copiado = true; setTimeout(() => copiado = false, 2000)"
                        x-text="copiado ? 'Copiado ✓' : 'Copiar'"></button>
            </div>
            <ul class="space-y-2 text-gray-600">
                <li><strong>Claude.ai:</strong> Configurações → Conectores → Adicionar conector personalizado → cole o endereço.</li>
                <li><strong>ChatGPT:</strong> Configurações → Apps e conectores → Criar (modo desenvolvedor) → cole o endereço, autenticação OAuth.</li>
                <li><strong>Claude Code:</strong> <code class="bg-gray-100 px-1 rounded">claude mcp add --transport http garimpo {{ $mcpUrl }}</code> e depois <code class="bg-gray-100 px-1 rounded">/mcp</code> para entrar.</li>
                <li><strong>Codex:</strong> <code class="bg-gray-100 px-1 rounded">codex mcp add garimpo --url {{ $mcpUrl }}</code> e depois <code class="bg-gray-100 px-1 rounded">codex mcp login garimpo</code>.</li>
            </ul>
            <p class="text-gray-500">Na primeira vez abre a tela de login do Garimpo e você clica em <strong>Autorizar</strong>. Pronto.</p>
        </div>

        <details class="bg-white rounded-xl border border-gray-200 p-6 text-sm">
            <summary class="font-semibold text-gray-900 cursor-pointer">Jeito manual: token (para apps que não fazem login)</summary>
            <div class="mt-4 space-y-3">
                @if (session('token'))
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200">
                        <p class="font-medium text-amber-900">Seu token (aparece só agora, copie e guarde):</p>
                        <code class="mt-2 block break-all bg-white rounded p-2 border border-amber-200 text-xs">{{ session('token') }}</code>
                    </div>
                @endif
                <form method="POST" action="{{ route('ai.store') }}">
                    @csrf
                    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Gerar token</button>
                </form>
                <p class="text-gray-600">Configuração em JSON (Cursor, Windsurf e outros):</p>
                <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto text-xs">{
  "mcpServers": {
    "garimpo": {
      "url": "{{ $mcpUrl }}",
      "headers": { "Authorization": "Bearer SEU_TOKEN" }
    }
  }
}</pre>
            </div>
        </details>

        <div class="bg-white rounded-xl border border-gray-200 p-6 text-sm">
            <p class="font-medium text-gray-800">IAs conectadas agora: {{ $tokens->count() }}</p>
            <ul class="mt-2 space-y-1 text-gray-500">
                @foreach ($tokens as $t)
                    <li>{{ $t->client?->name && $t->name !== 'mcp' ? $t->client->name : 'Token manual' }} · desde {{ $t->created_at->format('d/m/Y') }} · expira {{ $t->expires_at?->format('d/m/Y') }}</li>
                @endforeach
            </ul>
            @if ($tokens->isNotEmpty())
                <form method="POST" action="{{ route('ai.destroy') }}" class="mt-3" onsubmit="return confirm('Desconectar todas as IAs?')">
                    @csrf @method('DELETE')
                    <button class="text-red-600 hover:underline">Desconectar todas</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
