<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <a href="{{ url()->previous() === url()->current() ? route('jobs.index') : url()->previous() }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Voltar</a>

        @if (session('error'))
            <div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ $job->title }}</h1>
            <p class="text-gray-500 mt-1">{{ $job->company ?? 'Empresa não informada' }} · {{ $job->remote ? 'Remoto' : ($job->location ?? 'Local não informado') }}</p>
            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach ($job->techs ?? [] as $t)
                    <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">{{ $t }}</span>
                @endforeach
            </div>
            <p class="mt-4 text-gray-700 text-sm leading-relaxed">{{ $job->summary }}</p>
            <a href="{{ $job->url }}" target="_blank" rel="noopener nofollow" class="mt-5 inline-block px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                Ver vaga completa e se candidatar em {{ ucfirst($job->source) }} &nearr;
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900">Seu currículo x esta vaga (ATS)</h2>

            @guest
                <p class="mt-2 text-sm text-gray-600"><a href="{{ route('register') }}" class="text-emerald-700 underline">Crie uma conta</a> e cadastre seu currículo para ver a compatibilidade.</p>
            @elseif (! $resume)
                <p class="mt-2 text-sm text-gray-600"><a href="{{ route('resume.edit') }}" class="text-emerald-700 underline">Cadastre seu currículo</a> para ver a compatibilidade com esta vaga.</p>
            @else
                <div class="mt-4 flex items-center gap-4">
                    <div class="text-4xl font-bold {{ $match['score'] >= 70 ? 'text-emerald-600' : ($match['score'] >= 40 ? 'text-amber-500' : 'text-red-500') }}">{{ $match['score'] }}%</div>
                    <p class="text-sm text-gray-500">das palavras-chave técnicas da vaga aparecem no seu currículo.</p>
                </div>

                <div class="mt-4 grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <h3 class="font-medium text-gray-800">Você já tem</h3>
                        <p class="mt-1 text-emerald-700">{{ implode(', ', $match['matched']) ?: '—' }}</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-800">Falta no currículo</h3>
                        <p class="mt-1 text-red-600">{{ implode(', ', $match['missing']) ?: 'Nada! 🎉' }}</p>
                    </div>
                </div>

                <ul class="mt-4 list-disc ps-5 text-sm text-gray-600 space-y-1">
                    @foreach ($match['tips'] as $tip)
                        <li>{{ $tip }}</li>
                    @endforeach
                </ul>

                <div class="mt-6 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('jobs.tailor', $job) }}">
                        @csrf
                        <button class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-gray-700">
                            {{ $tailoring ? 'Gerar de novo com IA' : 'Gerar currículo adaptado com IA' }}
                        </button>
                    </form>
                    @if ($tailoring)
                        <a href="{{ route('jobs.resume', $job) }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">Ver currículo adaptado</a>
                    @endif
                </div>
            @endguest
        </div>
    </div>
</x-app-layout>
