@php($token = session('token') ?? 'SEU_TOKEN')
<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Conectar minha IA</h1>
            <p class="text-gray-500 mt-1 text-sm">
                Já paga Claude, ChatGPT/Codex, Cursor ou outra IA? Conecte ao Garimpo por MCP e ela poderá buscar vagas,
                ler seu currículo, gerar currículos adaptados sem limite e atualizar suas candidaturas.
                A IA grátis do site continua disponível só para gerar currículos (5 por dia).
            </p>
        </div>

        @if (session('status'))
            <div class="p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('status') }}</div>
        @endif

        @if (session('token'))
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm">
                <p class="font-medium text-amber-900">Seu token (aparece só agora, copie e guarde):</p>
                <code class="mt-2 block break-all bg-white rounded p-2 border border-amber-200">{{ session('token') }}</code>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4 text-sm">
            <p><strong>1.</strong> Gere um token:</p>
            <form method="POST" action="{{ route('ai.store') }}">
                @csrf
                <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Gerar token</button>
            </form>

            <p><strong>2.</strong> Adicione o servidor na sua IA. Endereço: <code class="bg-gray-100 px-1 rounded">{{ $mcpUrl }}</code></p>

            <p class="font-medium text-gray-800">Claude Code</p>
            <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto text-xs">claude mcp add --transport http garimpo {{ $mcpUrl }} --header "Authorization: Bearer {{ $token }}"</pre>

            <p class="font-medium text-gray-800">Cursor, Codex, Windsurf e outros (JSON)</p>
            <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto text-xs">{
  "mcpServers": {
    "garimpo": {
      "url": "{{ $mcpUrl }}",
      "headers": { "Authorization": "Bearer {{ $token }}" }
    }
  }
}</pre>

            <p><strong>3.</strong> Peça, por exemplo: <em>"Busque no Garimpo vagas de PHP pleno remotas, compare com meu currículo e gere um currículo adaptado para a melhor."</em></p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 text-sm">
            <p class="font-medium text-gray-800">Acessos ativos: {{ $tokens->count() }}</p>
            @foreach ($tokens as $t)
                <p class="text-gray-500">Criado em {{ $t->created_at->format('d/m/Y H:i') }} · último uso: {{ $t->last_used_at?->diffForHumans() ?? 'nunca' }}</p>
            @endforeach
            @if ($tokens->isNotEmpty())
                <form method="POST" action="{{ route('ai.destroy') }}" class="mt-3" onsubmit="return confirm('Desconectar todas as IAs?')">
                    @csrf @method('DELETE')
                    <button class="text-red-600 hover:underline">Revogar todos</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
