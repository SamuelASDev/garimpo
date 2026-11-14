<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ url()->previous() === url()->current() ? route('jobs.index') : url()->previous() }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Voltar</a>

        @foreach (['status' => 'bg-emerald-50 text-emerald-700', 'error' => 'bg-red-50 text-red-700'] as $key => $classes)
            @if (session($key))
                <div class="mt-4 p-4 rounded-lg text-sm {{ $classes }}">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="mt-4 grid lg:grid-cols-[1fr_340px] gap-6 items-start">
            <article class="bg-white rounded-xl border border-gray-200 p-6 min-w-0">
                <h1 class="text-2xl font-bold text-gray-900">{{ $job->title }}</h1>
                <p class="text-gray-500 mt-1">{{ $job->company ?? 'Empresa não informada' }} · {{ $job->remote ? 'Remoto' : ($job->location ?? 'Local não informado') }} · {{ ucfirst($job->source) }}</p>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @foreach ($job->techs ?? [] as $t)
                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">{{ $t }}</span>
                    @endforeach
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ $job->url }}" target="_blank" rel="noopener nofollow" class="px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Candidatar-se em {{ ucfirst($job->source) }} &nearr;</a>
                    @auth
                        <form method="POST" action="{{ route('jobs.apply', $job) }}">
                            @csrf
                            <button class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">✓ Me candidatei</button>
                        </form>
                    @endauth
                </div>

                <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-400">Descrição da vaga</h2>
                <div class="mt-2 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $job->description ?: $job->summary }}</div>
                @if (mb_strlen((string) $job->description) < 400)
                    <p class="mt-4 text-xs text-gray-400">O site de origem só mostra um resumo. Veja a descrição completa no link da vaga.</p>
                @endif
            </article>

            <aside class="bg-white rounded-xl border border-gray-200 p-6 lg:sticky lg:top-4">
                <h2 class="font-semibold text-gray-900">Seu currículo x esta vaga</h2>

                @guest
                    <p class="mt-2 text-sm text-gray-600"><a href="{{ route('register') }}" class="text-emerald-700 underline">Crie uma conta</a> e importe seu currículo para ver a compatibilidade.</p>
                @elseif (! $resume)
                    <p class="mt-2 text-sm text-gray-600"><a href="{{ route('resume.edit') }}" class="text-emerald-700 underline">Monte ou importe seu currículo</a> para ver a compatibilidade.</p>
                @else
                    <x-score-ring :score="$match['score']" class="mt-4">das tecnologias da vaga estão no seu currículo</x-score-ring>

                    <p class="mt-5 text-sm font-medium text-gray-800">✅ Você já tem</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        @forelse ($match['matched'] as $t)
                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs border border-emerald-200">{{ $t }}</span>
                        @empty
                            <span class="text-xs text-gray-400">nenhuma ainda</span>
                        @endforelse
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-800">❌ Falta no currículo</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        @forelse ($match['missing'] as $t)
                            <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-xs border border-red-200">{{ $t }}</span>
                        @empty
                            <span class="text-xs text-emerald-700">nada faltando 🎉</span>
                        @endforelse
                    </div>

                    <ul class="mt-4 space-y-1.5 text-xs text-gray-500">
                        @foreach ($match['tips'] as $tip)
                            <li>💡 {{ $tip }}</li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('jobs.tailor', $job) }}" class="mt-5" x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <button class="w-full px-4 py-2.5 rounded-lg bg-gray-900 text-white text-sm hover:bg-gray-700 disabled:opacity-60" :disabled="loading">
                            <span x-text="loading ? 'A IA está montando seu currículo…' : '{{ $tailoring ? '✨ Gerar de novo para esta vaga' : '✨ Gerar currículo para esta vaga' }}'"></span>
                        </button>
                    </form>
                    @if ($tailoring)
                        <a href="{{ route('jobs.resume', $job) }}" class="mt-2 block text-center px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">Abrir currículo desta vaga no editor</a>
                    @endif
                @endguest
            </aside>
        </div>
    </div>
</x-app-layout>
