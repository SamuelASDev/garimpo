<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900">Currículos</h1>
        <p class="text-gray-500 text-sm mt-1">Seu currículo principal é a base. Para cada vaga, o Garimpo cria uma cópia adaptada, sem mexer no principal.</p>

        @if (session('status'))
            <div class="mt-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('status') }}</div>
        @endif

        {{-- Currículo principal --}}
        <a href="{{ route('resume.edit') }}" class="mt-6 block rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-700 text-white p-6 hover:shadow-lg transition">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-emerald-100">⭐ Currículo principal</p>
                    <p class="mt-1 text-xl font-semibold">{{ $resume ? auth()->user()->name : 'Você ainda não montou seu currículo' }}</p>
                    <p class="mt-1 text-sm text-emerald-100">
                        @if ($resume)
                            Atualizado {{ $resume->updated_at->diffForHumans() }}
                            @if ($mainPending) · 🖍 {{ $mainPending }} {{ $mainPending === 1 ? 'trecho' : 'trechos' }} para completar @endif
                            @if ($resume->recommendations) · 🤖 {{ count($resume->recommendations) }} sugestões da IA @endif
                        @else
                            Importe o PDF do seu currículo ou comece pelo modelo pronto.
                        @endif
                    </p>
                </div>
                <span class="px-4 py-2 rounded-lg bg-white text-emerald-700 text-sm font-medium">{{ $resume ? 'Abrir e editar' : 'Começar agora' }} →</span>
            </div>
        </a>

        @if (session('error'))
            <div class="mt-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-sm">{{ session('error') }}</div>
        @endif

        {{-- Variações: currículos para um foco (stack, área), montados pela própria pessoa --}}
        <div class="mt-8" x-data="{ criando: {{ $errors->any() ? 'true' : 'false' }} }">
            <div class="flex items-end justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-gray-900">Minhas variações <span class="text-gray-400 font-normal">({{ $variants->count() }})</span></h2>
                    <p class="text-xs text-gray-500">Versões do seu currículo para um foco: uma stack, uma área, estágio, vagas em inglês...</p>
                </div>
                <button type="button" @click="criando = !criando" class="text-sm text-emerald-700 hover:underline" x-text="criando ? 'Cancelar' : '+ Nova variação'"></button>
            </div>

            <form x-show="criando" x-cloak method="POST" action="{{ route('variants.store') }}" class="mt-3 bg-white rounded-xl border border-emerald-200 p-5 grid sm:grid-cols-2 gap-4 text-sm">
                @csrf
                <label class="block">
                    <span class="font-medium text-gray-800">Nome da variação *</span>
                    <input name="name" required maxlength="80" value="{{ old('name') }}" placeholder="Ex.: Back-end PHP/Laravel" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <x-input-error :messages="$errors->get('name')" />
                </label>
                <label class="block">
                    <span class="font-medium text-gray-800">Categoria</span>
                    <input name="category" list="variant-categories" maxlength="40" value="{{ old('category') }}" placeholder="Escolha ou escreva" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <datalist id="variant-categories">
                        @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
                    </datalist>
                    <x-input-error :messages="$errors->get('category')" />
                </label>
                <label class="block sm:col-span-2">
                    <span class="font-medium text-gray-800">Descrição <span class="text-gray-400 font-normal">(para que serve, o que destacar; a IA usa isso nas sugestões)</span></span>
                    <textarea name="description" rows="2" maxlength="300" placeholder="Ex.: vagas júnior de back-end; destacar Laravel, APIs REST e MySQL" class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ old('description') }}</textarea>
                    <x-input-error :messages="$errors->get('description')" />
                </label>
                <fieldset class="sm:col-span-2">
                    <span class="font-medium text-gray-800">Começar a partir de</span>
                    <div class="mt-1 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="base" value="principal" @checked(old('base', $resume ? 'principal' : 'modelo') === 'principal') @disabled(! $resume)> Uma cópia do meu currículo principal</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="base" value="modelo" @checked(old('base', $resume ? 'principal' : 'modelo') === 'modelo')> O modelo em branco (preencho do zero)</label>
                    </div>
                </fieldset>
                <div class="sm:col-span-2">
                    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Criar e abrir no editor</button>
                </div>
            </form>

            <div class="mt-3 grid sm:grid-cols-2 gap-3 [&>*]:min-w-0">
                @forelse ($variants as $variant)
                    <div class="bg-white rounded-xl border border-gray-200 p-4 hover:border-emerald-400 transition flex flex-col">
                        <a href="{{ route('variants.edit', $variant) }}" class="flex-1 min-w-0">
                            @if ($variant->category)
                                <span class="inline-block px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-xs border border-indigo-200">{{ $variant->category }}</span>
                            @endif
                            <span class="block mt-1 font-medium text-gray-900 truncate">{{ $variant->name }}</span>
                            @if ($variant->description)
                                <span class="block text-sm text-gray-500 line-clamp-2">{{ $variant->description }}</span>
                            @endif
                            <span class="block mt-1 text-xs text-gray-400">{{ $variant->updated_at->diffForHumans() }}</span>
                        </a>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between text-xs">
                            <a href="{{ route('variants.edit', $variant) }}" class="text-gray-500 hover:text-gray-800">Abrir e editar</a>
                            <form method="POST" action="{{ route('variants.destroy', $variant) }}" onsubmit="return confirm('Excluir esta variação? O principal não é afetado.')">
                                @csrf @method('DELETE')
                                <button class="text-red-500 hover:underline">Excluir</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="sm:col-span-2 text-center bg-white rounded-xl border border-dashed border-gray-300 p-8 text-sm text-gray-500">
                        Nenhuma ainda. Crie, por exemplo, uma variação <strong>Back-end PHP</strong> e outra <strong>Suporte</strong>, cada uma com o foco certo.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Currículos por vaga --}}
        <div class="mt-8 flex items-end justify-between gap-2">
            <h2 class="font-semibold text-gray-900">Currículos para vagas <span class="text-gray-400 font-normal">({{ $tailorings->count() }})</span></h2>
            <a href="{{ route('jobs.index') }}" class="text-sm text-emerald-700 hover:underline">+ Gerar para outra vaga</a>
        </div>

        <div class="mt-3 grid sm:grid-cols-2 gap-3 [&>*]:min-w-0">
            @forelse ($tailorings as $item)
                @php($s = $item['score'])
                <div class="bg-white rounded-xl border border-gray-200 p-4 hover:border-emerald-400 transition flex flex-col">
                    <a href="{{ route('jobs.resume', $item['job']) }}" class="flex items-start gap-3 flex-1 min-w-0">
                        <span @class(['shrink-0 grid place-items-center w-12 h-12 rounded-full text-sm font-bold',
                            'bg-emerald-100 text-emerald-700' => $s >= 70, 'bg-amber-100 text-amber-700' => $s >= 40 && $s < 70, 'bg-red-100 text-red-700' => $s < 40])>{{ $s }}%</span>
                        <span class="min-w-0">
                            <span class="block font-medium text-gray-900 truncate">{{ $item['job']->title }}</span>
                            <span class="block text-sm text-gray-500 truncate">{{ $item['job']->company ?? 'Empresa não informada' }}</span>
                            <span class="block mt-1 text-xs text-gray-400">
                                {{ $item['tailoring']->updated_at->diffForHumans() }}
                                @if ($item['pending']) · 🖍 {{ $item['pending'] }} para completar @endif
                            </span>
                        </span>
                    </a>
                    <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between text-xs">
                        <a href="{{ route('jobs.show', $item['job']) }}" class="text-gray-500 hover:text-gray-800">Ver vaga</a>
                        <form method="POST" action="{{ route('jobs.resume.destroy', $item['job']) }}" onsubmit="return confirm('Excluir este currículo? O principal não é afetado.')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:underline">Excluir</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 text-center bg-white rounded-xl border border-dashed border-gray-300 p-10 text-sm text-gray-500">
                    Nenhum ainda. Abra uma vaga e clique em <strong>✨ Gerar currículo para esta vaga</strong>.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
