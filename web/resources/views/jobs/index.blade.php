@php
    $labels = ['estagio' => 'Estágio / Trainee', 'junior' => 'Júnior', 'pleno' => 'Pleno', 'senior' => 'Sênior', 'especialista' => 'Especialista',
        'dev' => 'Desenvolvimento', 'suporte' => 'Suporte', 'infra' => 'Infra/DevOps', 'dados' => 'Dados', 'qa' => 'QA/Testes',
        'seguranca' => 'Segurança', 'mobile' => 'Mobile', 'design' => 'UX/UI', 'gestao' => 'Gestão/Produto'];
@endphp
<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900">Vagas de TI</h1>
        <p class="text-gray-500 mt-1">{{ $jobs->total() }} vagas reunidas de vários sites. Filtre e veja o quanto seu currículo combina com cada uma.</p>

        <form method="GET" class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 bg-white p-4 rounded-xl border border-gray-200">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cargo ou empresa" class="sm:col-span-2 rounded-lg border-gray-300 text-sm">
            <input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="📍 Cidade ou estado" class="rounded-lg border-gray-300 text-sm">
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
            {{-- Várias tecnologias: a vaga precisa ter todas as marcadas --}}
            <div class="relative" x-data="{ open: false, busca: '', marcadas: @js(array_values(array_intersect((array) ($filters['tech'] ?? []), $techs))) }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" @click="open = !open" class="w-full h-full min-h-[38px] flex items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm text-left">
                    <span class="truncate" :class="marcadas.length ? 'text-gray-900' : 'text-gray-500'" x-text="marcadas.length ? marcadas.join(' + ') : 'Tecnologias'"></span>
                    <span x-show="marcadas.length" x-cloak class="shrink-0 px-1.5 rounded-full bg-emerald-600 text-white text-xs" x-text="marcadas.length"></span>
                </button>
                <div x-show="open" x-cloak x-transition class="absolute z-20 mt-1 w-64 max-w-[calc(100vw-2rem)] bg-white border border-gray-200 rounded-xl shadow-lg p-2">
                    <input x-model="busca" placeholder="Buscar tecnologia…" class="w-full mb-2 rounded-lg border-gray-300 text-sm">
                    <div class="max-h-64 overflow-y-auto">
                        @foreach ($techs as $t)
                            <label x-show="@js(Str::lower($t)).includes(busca.toLowerCase())" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="tech[]" value="{{ $t }}" x-model="marcadas" class="rounded border-gray-300 text-emerald-600"> {{ $t }}
                            </label>
                        @endforeach
                    </div>
                    <div class="flex justify-between gap-2 border-t border-gray-100 mt-2 pt-2">
                        <button type="button" @click="marcadas = []" class="text-xs text-gray-500 hover:text-gray-800">Limpar</button>
                        <button class="px-3 py-1 rounded-md bg-emerald-600 text-white text-xs hover:bg-emerald-700">Aplicar</button>
                    </div>
                </div>
            </div>
            <select name="mode" class="rounded-lg border-gray-300 text-sm">
                <option value="">Modalidade</option>
                @foreach ($modes as $value => $text)
                    <option value="{{ $value }}" @selected(($filters['mode'] ?? (! empty($filters['remote']) ? 'remoto' : '')) === $value)>{{ $text }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Filtrar</button>
        </form>

        {{-- Alerta por e-mail com os filtros aplicados --}}
        @auth
            <form method="POST" action="{{ route('alerts.store') }}" class="mt-3 flex flex-wrap items-center justify-end gap-2 text-sm" x-data="{ nome: false }">
                @csrf
                @foreach (array_filter($filters) as $key => $value)
                    @foreach ((array) $value as $v)
                        <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $v }}">
                    @endforeach
                @endforeach
                <input x-show="nome" x-cloak name="name" maxlength="80" placeholder="Nome do alerta (opcional)" class="rounded-lg border-gray-300 text-sm py-1.5">
                <button type="button" x-show="!nome" @click="nome = true" class="text-gray-500 hover:text-gray-800">dar um nome</button>
                <button class="px-3 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 bg-white hover:bg-emerald-50">🔔 Me avise de vagas assim</button>
            </form>
        @else
            <p class="mt-3 text-right text-sm"><a href="{{ route('register') }}" class="text-emerald-700 hover:underline">🔔 Crie uma conta para receber vagas assim por e-mail</a></p>
        @endauth

        <div class="mt-6 space-y-3">
            @forelse ($jobs as $job)
                <a href="{{ route('jobs.show', $job) }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-emerald-400 transition">
                    <h2 class="font-semibold text-gray-900">{{ $job->title }}</h2>
                    <p class="text-sm text-gray-500">{{ $job->company ?? 'Empresa não informada' }} · 📍 {{ $job->location ?: ($job->work_mode === 'remoto' ? 'Qualquer lugar' : 'Local não informado') }}</p>
                    <x-job-badges :job="$job" class="mt-3" />
                    @if ($job->techs)
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach (array_slice($job->techs, 0, 8) as $t)
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">{{ $t }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <p class="text-xs text-gray-500">
                            <span class="font-medium text-gray-700">{{ ucfirst($job->source) }}</span>
                            @if ($job->posted_at) · publicada em <span class="font-medium text-gray-700">{{ $job->posted_at->format('d/m/Y') }}</span> ({{ $job->posted_at->diffForHumans() }})@endif
                        </p>
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
