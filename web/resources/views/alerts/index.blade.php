<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900">🔔 Alertas de vagas</h1>
        <p class="text-gray-500 text-sm mt-1">Todo dia de manhã você recebe <strong>um único e-mail</strong> com as vagas novas de todos os seus alertas. Sem vaga nova, sem e-mail.</p>

        @foreach (['status' => 'bg-emerald-50 text-emerald-700', 'error' => 'bg-red-50 text-red-700'] as $key => $classes)
            @if (session($key))
                <div class="mt-4 p-3 rounded-lg text-sm {{ $classes }}">{{ session($key) === 'verification-link-sent' ? 'Enviamos o link de confirmação. Confira sua caixa de entrada (e o spam).' : session($key) }}</div>
            @endif
        @endforeach

        @unless (auth()->user()->hasVerifiedEmail())
            <div class="mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900">
                <p><strong>Confirme seu e-mail para receber os alertas.</strong> Só enviamos para e-mails confirmados.</p>
                <form method="POST" action="{{ route('verification.send') }}" class="mt-2">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg bg-amber-600 text-white hover:bg-amber-700">Enviar link de confirmação para {{ auth()->user()->email }}</button>
                </form>
            </div>
        @endunless

        <div class="mt-6 space-y-3">
            @forelse ($alerts as $alert)
                <div @class(['bg-white rounded-xl border p-4 flex flex-wrap items-center justify-between gap-3', 'border-gray-200' => $alert->active, 'border-dashed border-gray-300 opacity-70' => ! $alert->active])>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $alert->name }}</p>
                        <p class="text-sm text-gray-500">{{ $alert->describe() }}</p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ $alert->active ? 'Ativo' : 'Pausado' }} ·
                            {{ $today[$alert->id] }} {{ $today[$alert->id] === 1 ? 'vaga nova' : 'vagas novas' }} nas últimas 24h ·
                            último e-mail: {{ $alert->last_sent_at?->diffForHumans() ?? 'ainda nenhum' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <a href="{{ route('jobs.index', $alert->filters) }}" class="text-emerald-700 hover:underline">Ver vagas</a>
                        <form method="POST" action="{{ route('alerts.update', $alert) }}">
                            @csrf @method('PATCH')
                            <button class="text-gray-600 hover:underline">{{ $alert->active ? 'Pausar' : 'Reativar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('alerts.destroy', $alert) }}" onsubmit="return confirm('Apagar este alerta?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Apagar</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="text-center bg-white rounded-xl border border-dashed border-gray-300 p-10 text-sm text-gray-500">
                    Nenhum alerta ainda. Vá em <a href="{{ route('jobs.index') }}" class="text-emerald-700 underline">Vagas</a>, aplique os filtros que quiser
                    (senioridade, área, tecnologias, remoto) e clique em <strong>🔔 Me avise de vagas assim</strong>.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
