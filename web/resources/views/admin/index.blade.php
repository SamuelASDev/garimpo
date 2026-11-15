<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @include('admin._nav')

        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([['Vagas', $jobs], ['Novas nas últimas 24h', $jobsToday], ['Contas', $users.' ('.$verified.' confirmadas)'], ['Administradores', $admins]] as [$label, $value])
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-6 bg-white rounded-xl border border-gray-200 p-4">
            <h2 class="font-semibold text-gray-900">Vagas por site</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-gray-500"><tr><th class="py-1">Site</th><th>Vagas</th><th>Última vaga nova</th></tr></thead>
                <tbody>
                    @forelse ($sources as $s)
                        <tr class="border-t border-gray-100">
                            <td class="py-1.5"><a class="text-emerald-700 hover:underline" href="{{ route('admin.jobs', ['source' => $s->source]) }}">{{ $s->source }}</a></td>
                            <td>{{ $s->total }}</td>
                            <td class="text-gray-500">{{ \Illuminate\Support\Carbon::parse($s->last)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-gray-500">Nenhuma vaga ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.jobs.destroyAll') }}" class="mt-6 bg-red-50 border border-red-200 rounded-xl p-4 text-sm"
              onsubmit="return confirm('Apagar as vagas? Não dá para desfazer.')">
            @csrf @method('DELETE')
            <h2 class="font-semibold text-red-800">Apagar todas as vagas</h2>
            <p class="mt-1 text-red-700">Use depois de mudar as regras do coletor: a próxima coleta recomeça do zero (até 30 dias para trás). Candidaturas dos usuários continuam, só perdem o link para a vaga.</p>
            <label class="mt-3 flex items-center gap-2 text-red-800">
                <input type="checkbox" name="keep_used" value="1" checked class="rounded border-red-300 text-red-600">
                Manter as vagas que têm currículo adaptado ou descrição colada por algum usuário (senão esses currículos também são apagados)
            </label>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <input name="confirm" placeholder="Digite APAGAR" autocomplete="off" class="rounded-lg border-red-300 text-sm">
                <button class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Apagar vagas</button>
            </div>
        </form>
    </div>
</x-app-layout>
