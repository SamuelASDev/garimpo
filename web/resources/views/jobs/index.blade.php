@php
    $labels = ['estagio' => 'Estágio / Trainee', 'junior' => 'Júnior', 'pleno' => 'Pleno', 'senior' => 'Sênior', 'especialista' => 'Especialista',
        'dev' => 'Desenvolvimento', 'suporte' => 'Suporte', 'infra' => 'Infra/DevOps', 'dados' => 'Dados', 'qa' => 'QA/Testes',
        'seguranca' => 'Segurança', 'mobile' => 'Mobile', 'design' => 'UX/UI', 'gestao' => 'Gestão/Produto'];
@endphp
<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900">Vagas de TI</h1>
        <p class="text-gray-500 mt-1">{{ $jobs->total() }} vagas reunidas de vários sites. Filtre e veja o quanto seu currículo combina com cada uma.</p>

        <form method="GET" class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto] bg-white p-4 rounded-xl border border-gray-200">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cargo ou empresa" class="sm:col-span-2 lg:col-span-1 rounded-lg border-gray-300 text-sm">
            <select name="seniority" class="rounded-lg border-gray-300 text-sm">
                <option value="">Senioridade</option>
                @foreach ($seniorities as $s)
                    <option value="{{ $s }}" @selected(($filters['seniority'] ?? '') === $s)>{{ $labels[$s] }}</option>
                @endforeach
            </select>
            <select name="area" class="rounded-lg border-gray-300 text-sm">
                <option value="">Área</option>
                @foreach ($areas as $a)
                    <option value="{{ $a }}" @selected(($filters['area'] ?? '') === $a)>{{ $labels[$a] }}</option>
                @endforeach
            </select>
            <select name="tech" class="rounded-lg border-gray-300 text-sm">
                <option value="">Tecnologia</option>
                @foreach ($techs as $t)
                    <option value="{{ $t }}" @selected(($filters['tech'] ?? '') === $t)>{{ $t }}</option>
                @endforeach
            </select>
            <div class="flex items-center justify-between sm:justify-end gap-3 sm:col-span-2 lg:col-span-1">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remote" value="1" @checked($filters['remote'] ?? false) class="rounded border-gray-300 text-emerald-600"> Remoto
                </label>
                <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Filtrar</button>
            </div>
        </form>

        <div class="mt-6 space-y-3">
            @forelse ($jobs as $job)
                <a href="{{ route('jobs.show', $job) }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-emerald-400 transition">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="font-semibold text-gray-900">{{ $job->title }}</h2>
                            <p class="text-sm text-gray-500">{{ $job->company ?? 'Empresa não informada' }} · {{ $job->remote ? 'Remoto' : ($job->location ?? 'Local não informado') }}</p>
                        </div>
                        <div class="flex gap-2 text-xs">
                            @if ($job->seniority)<span class="px-2 py-1 rounded-full bg-amber-50 text-amber-700">{{ $labels[$job->seniority] }}</span>@endif
                            @if ($job->area)<span class="px-2 py-1 rounded-full bg-sky-50 text-sky-700">{{ $labels[$job->area] }}</span>@endif
                        </div>
                    </div>
                    @if ($job->techs)
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach (array_slice($job->techs, 0, 8) as $t)
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">{{ $t }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <p class="text-xs text-gray-400">{{ ucfirst($job->source) }} · {{ $job->posted_at?->diffForHumans() }}</p>
                        @isset($scores[$job->id])
                            @php($s = $scores[$job->id])
                            <span title="Compatibilidade com o seu currículo" @class(['px-2 py-0.5 rounded-full text-xs font-semibold',
                                'bg-emerald-100 text-emerald-700' => $s >= 70, 'bg-amber-100 text-amber-700' => $s >= 40 && $s < 70, 'bg-red-100 text-red-700' => $s < 40])>
                                {{ $s >= 70 ? '🎯' : ($s >= 40 ? '💪' : '🔧') }} {{ $s }}% compatível
                            </span>
                        @endisset
                    </div>
                </a>
            @empty
                <p class="text-center text-gray-500 py-16">Nenhuma vaga encontrada com esses filtros.</p>
            @endforelse
        </div>

        <div class="mt-6">{{ $jobs->links() }}</div>
    </div>
</x-app-layout>
