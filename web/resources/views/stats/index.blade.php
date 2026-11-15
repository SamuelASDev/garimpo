@php
    $palette = ['#059669', '#0284c7', '#d97706', '#7c3aed', '#e11d48', '#0d9488', '#4f46e5', '#ca8a04', '#64748b', '#db2777', '#16a34a', '#9333ea', '#ea580c', '#2563eb', '#475569'];
    $labels = \App\Services\JobClassifier::LABELS;
@endphp

<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900">📊 Estatísticas das vagas</h1>
        <p class="text-gray-500 text-sm mt-1">O que o mercado de TI está pedindo, a partir das vagas que o Garimpo coletou.</p>

        <form class="mt-5 flex flex-wrap gap-2 text-sm">
            <select name="days" class="rounded-lg border-gray-300 text-sm">
                @foreach (\App\Http\Controllers\StatsController::PERIODS as $value => $text)
                    <option value="{{ $value }}" @selected($days === $value)>Últimos {{ $text }}</option>
                @endforeach
            </select>
            <select name="seniority" class="rounded-lg border-gray-300 text-sm">
                <option value="">Toda senioridade</option>
                @foreach ($options['seniorities'] as $s)
                    <option value="{{ $s }}" @selected(($filters['seniority'] ?? '') === $s)>{{ $labels[$s] }}</option>
                @endforeach
            </select>
            <select name="area" class="rounded-lg border-gray-300 text-sm">
                <option value="">Toda área</option>
                @foreach ($options['areas'] as $a)
                    <option value="{{ $a }}" @selected(($filters['area'] ?? '') === $a)>{{ $labels[$a] ?? $a }}</option>
                @endforeach
            </select>
            <select name="tech" class="rounded-lg border-gray-300 text-sm">
                <option value="">Toda tecnologia</option>
                @foreach ($options['techs'] as $t)
                    <option @selected(($filters['tech'] ?? '') === $t)>{{ $t }}</option>
                @endforeach
            </select>
            <select name="mode" class="rounded-lg border-gray-300 text-sm">
                <option value="">Toda modalidade</option>
                @foreach (\App\Services\JobClassifier::WORK_MODES as $value => $text)
                    <option value="{{ $value }}" @selected(($filters['mode'] ?? '') === $value)>{{ $text }}</option>
                @endforeach
            </select>
            <x-city-picker :city="$filters['city'] ?? null" :state="$filters['state'] ?? null" />
            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Aplicar</button>
        </form>

        <p class="mt-4 text-sm text-gray-600"><strong class="text-gray-900 text-lg">{{ $total }}</strong> vagas publicadas nos últimos {{ $days }} dias com esses filtros.</p>

        @if ($total === 0)
            <div class="mt-6 p-6 bg-white rounded-xl border border-gray-200 text-gray-500 text-sm">Nenhuma vaga com esses filtros. Tente um período maior ou menos filtros.</div>
        @else
            {{-- Vagas por dia (colunas) --}}
            @php $maxDay = max(1, max($perDay)); @endphp
            <div class="mt-6 bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="font-semibold text-gray-900">Vagas publicadas por dia</h2>
                <div class="mt-4 flex items-end gap-px h-40">
                    @foreach ($perDay as $date => $n)
                        <div class="flex-1 h-full flex items-end group relative" title="{{ \Illuminate\Support\Carbon::parse($date)->format('d/m') }}: {{ $n }} vagas">
                            <div class="w-full rounded-t bg-emerald-500 group-hover:bg-emerald-700" style="height: {{ max($n ? 2 : 0, $n / $maxDay * 100) }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex justify-between text-xs text-gray-400">
                    <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($perDay))->format('d/m') }}</span>
                    <span>hoje</span>
                </div>
            </div>

            <div class="mt-6 grid md:grid-cols-2 gap-6">
                @include('stats._bars', ['title' => 'Tecnologias mais pedidas', 'data' => $techs, 'color' => '#059669', 'link' => 'tech'])
                @include('stats._bars', ['title' => 'Competências mais pedidas', 'data' => $skills, 'color' => '#0284c7'])
                @include('stats._donut', ['title' => 'Senioridade', 'data' => $seniorities])
                @include('stats._donut', ['title' => 'Área', 'data' => $areas])
                @include('stats._donut', ['title' => 'Modalidade', 'data' => $modes])
                @include('stats._donut', ['title' => 'Sites de origem', 'data' => $sources])
                @include('stats._bars', ['title' => 'Empresas que mais contratam', 'data' => $companies, 'color' => '#7c3aed'])
                @include('stats._bars', ['title' => 'Comportamentais mais citadas', 'data' => $softSkills, 'color' => '#d97706'])
            </div>
        @endif
    </div>
</x-app-layout>
