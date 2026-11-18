@php
    use App\Http\Controllers\AiConnectionController as Ai;
    $token = session('token');
    $app = session('token_app');
@endphp
<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ copiado: '' }">
        <p class="text-xs uppercase tracking-widest text-emerald-700 font-semibold">Para desenvolvedores</p>
        <h1 class="mt-1 text-3xl font-bold text-gray-900">Dev</h1>
        <p class="mt-2 text-gray-600">Duas formas de usar o Garimpo fora do site: conecte <strong>sua IA</strong> (Claude, ChatGPT, Codex, Cursor) à sua conta,
            ou use as <strong>vagas no seu próprio projeto</strong> pela API.</p>

        <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Nesta página">
            @foreach (['#ia' => '🤖 Conectar minha IA', '#chaves-api' => '🔑 Chaves da API', '#api' => '📘 Documentação da API'] as $anchor => $label)
                <a href="{{ $anchor }}" class="px-3 py-1.5 rounded-full border border-gray-300 bg-white hover:border-emerald-500 hover:text-emerald-700">{{ $label }}</a>
            @endforeach
        </nav>

        @if (session('status'))
            <div class="mt-6 p-3 rounded-lg text-sm bg-emerald-50 text-emerald-700">{{ session('status') }}</div>
        @endif

        {{-- 1. IA conectada (MCP) --}}
        <section id="ia" class="mt-10 scroll-mt-20 space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Conectar minha IA</h2>
                <p class="text-gray-500 mt-1 text-sm">
                    Conecte sua IA ao Garimpo e ela busca vagas, lê e adapta seus currículos e acompanha suas candidaturas,
                    sem o limite da IA grátis. Você escolhe quem conecta, por quanto tempo, e desconecta quando quiser.
                </p>
            </div>
            @auth
        {{-- Token recém-criado + instrução do app escolhido --}}
            @if ($token)
                <div class="p-5 rounded-xl bg-amber-50 border border-amber-200 text-sm space-y-3">
                    <p class="font-semibold text-amber-900">🔑 Seu token aparece só agora. Copie e guarde.</p>
                    <div class="flex gap-2">
                        <code class="flex-1 block break-all bg-white rounded p-2 border border-amber-200 text-xs max-h-24 overflow-y-auto">{{ $token }}</code>
                        <button type="button" class="px-3 rounded-lg border border-amber-300 bg-white" @click="navigator.clipboard.writeText(@js($token)); copiado = 'token'" x-text="copiado === 'token' ? 'Copiado ✓' : 'Copiar'"></button>
                    </div>
                    <p class="font-medium text-amber-900">Como usar no {{ Ai::APPS[$app] ?? 'seu app' }}:</p>
                    @if ($app === 'claude-code')
                        <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto text-xs">claude mcp add --transport http garimpo {{ $mcpUrl }} --header "Authorization: Bearer {{ $token }}"</pre>
                    @elseif ($app === 'codex')
                        <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto text-xs">export GARIMPO_TOKEN="{{ $token }}"
codex mcp add garimpo --url {{ $mcpUrl }} --bearer-token-env-var GARIMPO_TOKEN</pre>
                    @else
                        <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto text-xs">{
  "mcpServers": {
    "garimpo": {
      "url": "{{ $mcpUrl }}",
      "headers": { "Authorization": "Bearer {{ $token }}" }
    }
  }
}</pre>
                    @endif
                </div>
            @endif

            {{-- Jeito fácil: OAuth --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 text-sm space-y-4">
                <p class="font-semibold text-gray-900">Jeito fácil: colar o endereço e fazer login</p>
                <div class="flex gap-2">
                    <code class="flex-1 bg-gray-100 rounded-lg px-3 py-2 break-all">{{ $mcpUrl }}</code>
                    <button type="button" class="px-3 rounded-lg border border-gray-300 hover:bg-gray-50"
                            @click="navigator.clipboard.writeText(@js($mcpUrl)); copiado = 'url'" x-text="copiado === 'url' ? 'Copiado ✓' : 'Copiar'"></button>
                </div>
                <ul class="space-y-2 text-gray-600">
                    <li><strong>Claude.ai:</strong> Configurações → Conectores → Adicionar conector personalizado → cole o endereço.</li>
                    <li><strong>ChatGPT:</strong> Configurações → Apps e conectores → Criar (modo desenvolvedor) → cole o endereço, autenticação OAuth.</li>
                    <li><strong>Claude Code:</strong> <code class="bg-gray-100 px-1 rounded">claude mcp add --transport http garimpo {{ $mcpUrl }}</code> e depois <code class="bg-gray-100 px-1 rounded">/mcp</code>.</li>
                    <li><strong>Codex:</strong> <code class="bg-gray-100 px-1 rounded">codex mcp add garimpo --url {{ $mcpUrl }}</code> e depois <code class="bg-gray-100 px-1 rounded">codex mcp login garimpo</code>.</li>
                </ul>
                <p class="text-gray-500">Abre a tela de login do Garimpo e você clica em <strong>Autorizar</strong>. O acesso dura 30 dias e o app renova sozinho enquanto você usar; desconecte abaixo quando quiser.</p>
            </div>

            {{-- Jeito manual: token com app, nome e validade --}}
            <form method="POST" action="{{ route('dev.ai.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 text-sm space-y-4">
                @csrf
                <p class="font-semibold text-gray-900">Jeito manual: criar um token</p>
                <p class="text-gray-500 -mt-2">Para apps que não fazem login, ou se você quer controlar a validade.</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-gray-700">App</span>
                        <select name="app" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            @foreach (Ai::APPS as $key => $label)
                                <option value="{{ $key }}" @selected(old('app') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-gray-700">Nome da conexão <span class="text-gray-400">(opcional)</span></span>
                        <input name="label" maxlength="40" value="{{ old('label') }}" placeholder="ex.: notebook do trabalho" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                </div>
                <fieldset>
                    <legend class="text-gray-700">Validade</legend>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach (Ai::VALIDITY as $days => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="days" value="{{ $days }}" class="peer sr-only" @checked((int) old('days', 30) === $days)>
                                <span class="block px-3 py-1.5 rounded-full border border-gray-300 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-input-error :messages="$errors->all()" />
                <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Criar conexão</button>
            </form>

            {{-- Conexões ativas --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 text-sm">
                <p class="font-semibold text-gray-900">IAs conectadas agora: {{ $tokens->count() }}</p>
                <ul class="mt-3 divide-y divide-gray-100">
                    @forelse ($tokens as $t)
                        <li class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 truncate">{{ $t->name ?: ($t->client?->name ?? 'App conectado') }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $t->name ? 'Token manual' : 'Login OAuth' }} · desde {{ $t->created_at->format('d/m/Y') }} ·
                                    @php($dias = (int) now()->diffInDays($t->expires_at))
                                    <span @class(['text-amber-600' => $dias <= 3])>expira {{ $t->expires_at->format('d/m/Y') }}</span>
                                </p>
                            </div>
                            <form method="POST" action="{{ route('dev.ai.revoke', $t->id) }}" onsubmit="return confirm('Desconectar esta IA?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline shrink-0">Desconectar</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-3 text-gray-500">Nenhuma IA conectada.</li>
                    @endforelse
                </ul>
                @if ($tokens->count() > 1)
                    <form method="POST" action="{{ route('dev.ai.destroy') }}" class="mt-3" onsubmit="return confirm('Desconectar todas as IAs?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 hover:underline">Desconectar todas</button>
                    </form>
                @endif
            </div>
            @else
                <div class="bg-white rounded-xl border border-gray-200 p-6 text-sm text-gray-600">
                    <a href="{{ route('login') }}" class="text-emerald-700 underline">Entre</a> ou <a href="{{ route('register') }}" class="text-emerald-700 underline">crie uma conta grátis</a> para conectar sua IA.
                </div>
            @endauth
        </section>

        {{-- 2. Chaves da API --}}
        <section id="chaves-api" class="mt-12 pt-10 border-t border-gray-200 scroll-mt-20">
            <div class="bg-white rounded-xl border border-gray-200 p-5 sm:p-6">
                @auth
                    @include('dev.api-keys')
                @else
                    <h2 class="text-lg font-medium text-gray-900">Chaves da API</h2>
                    <p class="mt-1 text-sm text-gray-600"><a href="{{ route('login') }}" class="text-emerald-700 underline">Entre</a> ou <a href="{{ route('register') }}" class="text-emerald-700 underline">crie uma conta grátis</a> para gerar sua chave.</p>
                @endauth
            </div>
        </section>

        {{-- 3. Documentação da API --}}
        <section id="api" class="mt-12 pt-10 border-t border-gray-200 scroll-mt-20">
            <h2 class="text-2xl font-bold text-gray-900">Documentação da API</h2>
            <p class="mt-2 text-gray-600">As vagas de TI que o Garimpo coleta, já sem duplicadas e classificadas (senioridade, área, modo, cidade, tecnologias e stack), em JSON. Somente leitura, grátis e com limite justo.</p>
            @include('dev._api-docs')
        </section>
    </div>
</x-app-layout>
