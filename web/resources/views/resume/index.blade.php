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

        {{-- Currículos por vaga --}}
        <div class="mt-8 flex items-end justify-between gap-2">
            <h2 class="font-semibold text-gray-900">Currículos para vagas <span class="text-gray-400 font-normal">({{ $tailorings->count() }})</span></h2>
            <a href="{{ route('jobs.index') }}" class="text-sm text-emerald-700 hover:underline">+ Gerar para outra vaga</a>
        </div>

        <div class="mt-3 grid sm:grid-cols-2 gap-3">
            @forelse ($tailorings as $item)
                @php($s = $item['score'])
                <div class="bg-white rounded-xl border border-gray-200 p-4 hover:border-emerald-400 transition flex flex-col">
                    <a href="{{ route('jobs.resume', $item['job']) }}" class="flex items-start gap-3 flex-1">
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
