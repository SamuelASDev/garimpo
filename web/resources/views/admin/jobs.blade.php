<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @include('admin._nav')

        <form class="mt-6 flex flex-wrap gap-2 text-sm">
            <input name="q" value="{{ request('q') }}" placeholder="Cargo ou empresa" class="rounded-lg border-gray-300 text-sm">
            <select name="source" class="rounded-lg border-gray-300 text-sm">
                <option value="">Todos os sites</option>
                @foreach ($sources as $source)
                    <option @selected(request('source') === $source)>{{ $source }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 rounded-lg bg-gray-900 text-white">Filtrar</button>
            <span class="self-center text-gray-500">{{ $jobs->total() }} vagas</span>
        </form>

        <div class="mt-4 bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @forelse ($jobs as $job)
                <div class="p-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div class="min-w-0">
                        <a href="{{ route('jobs.show', $job) }}" class="font-medium text-gray-900 hover:underline">{{ $job->title }}</a>
                        <p class="text-gray-500">{{ $job->company ?: 'Empresa não informada' }} · {{ $job->source }} · publicada {{ $job->posted_at?->diffForHumans() }} · {{ mb_strlen((string) $job->description) }} caracteres</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.jobs.edit', $job) }}" class="text-emerald-700 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('admin.jobs.destroy', $job) }}" onsubmit="return confirm('Apagar esta vaga?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Apagar</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500">Nenhuma vaga encontrada.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $jobs->links() }}</div>
    </div>
</x-app-layout>
