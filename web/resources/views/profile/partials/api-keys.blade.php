<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Chaves da API</h2>
        <p class="mt-1 text-sm text-gray-600">Use as vagas do Garimpo no seu próprio projeto. Veja a <a href="{{ route('api.docs') }}" class="underline">documentação da API</a>.</p>
    </header>

    @if (session('api_key_plain'))
        <div class="mt-4 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-sm" x-data="{ copiado: false }">
            <p class="font-semibold text-emerald-900">Sua chave nova (aparece só agora: copie e guarde)</p>
            <div class="mt-2 flex gap-2">
                <code class="flex-1 min-w-0 truncate px-3 py-2 rounded bg-white border border-emerald-200 text-gray-900" x-ref="chave">{{ session('api_key_plain') }}</code>
                <button type="button" class="px-3 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
                        @click="navigator.clipboard.writeText($refs.chave.innerText); copiado = true" x-text="copiado ? 'Copiada ✓' : 'Copiar'"></button>
            </div>
            <p class="mt-2 text-xs text-emerald-800">Não compartilhe nem coloque no código do navegador (front-end): quem tiver a chave usa a sua cota.</p>
        </div>
    @endif
    @if (session('api_error'))
        <div class="mt-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-sm">{{ session('api_error') }}</div>
    @endif
    @if (session('api_status'))
        <div class="mt-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('api_status') }}</div>
    @endif

    <ul class="mt-4 divide-y divide-gray-100 text-sm">
        @forelse ($user->apiKeys()->latest()->get() as $key)
            <li class="py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-medium text-gray-900 truncate">{{ $key->name }}</p>
                    <p class="text-xs text-gray-500"><code>{{ $key->prefix }}…</code> · criada {{ $key->created_at->diffForHumans() }} · {{ $key->last_used_at ? 'usada '.$key->last_used_at->diffForHumans() : 'nunca usada' }}</p>
                </div>
                <form method="POST" action="{{ route('api-keys.destroy', $key) }}" onsubmit="return confirm('Revogar esta chave? Quem usa ela perde o acesso na hora.')">
                    @csrf @method('DELETE')
                    <button class="text-red-500 hover:underline text-xs">Revogar</button>
                </form>
            </li>
        @empty
            <li class="py-3 text-gray-500">Nenhuma chave ainda.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('api-keys.store') }}" class="mt-4 flex gap-2">
        @csrf
        <input name="name" required maxlength="60" placeholder="Nome da chave (ex.: meu bot de vagas)" class="flex-1 rounded-lg border-gray-300 text-sm">
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Criar chave</button>
    </form>
    <x-input-error :messages="$errors->apiKey->get('name')" class="mt-2" />
    <p class="mt-2 text-xs text-gray-400">Até {{ \App\Models\ApiKey::MAX_PER_USER }} chaves. Limite de 60 requisições por minuto e 5.000 por dia em cada uma.</p>
</section>
